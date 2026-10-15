<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class GoogleDriveService
{
    protected string $baseUrl = 'https://drive.google.com/drive/folders/';
    protected array $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ];

    /**
     * Extract client_email from a Service Account JSON string
     */
    public static function getServiceAccountEmail(?string $json): ?string
    {
        if (empty($json)) return null;
        $data = json_decode(trim($json), true);
        return is_array($data) ? ($data['client_email'] ?? null) : null;
    }

    /**
     * Check if credentials (Service Account JSON or API Key) are configured
     */
    public function hasCredentials(?int $universityId = null, ?string $explicitJson = null, ?string $explicitApiKey = null): bool
    {
        $uniId = Setting::resolveUniversityId($universityId);
        $saJson = $explicitJson !== null ? $explicitJson : (string) Setting::get('gdrive_service_account_json', '', $uniId);
        $apiKey = $explicitApiKey !== null ? $explicitApiKey : (string) Setting::get('gdrive_api_key', '', $uniId);

        return !empty(trim($saJson)) || !empty(trim($apiKey));
    }

    /**
     * Generate OAuth2 Access Token from Google Service Account JSON
     */
    public function getAccessToken(string $serviceAccountJson): string
    {
        $data = json_decode(trim($serviceAccountJson), true);
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \Exception("Invalid Service Account JSON: Must contain 'client_email' and 'private_key'.");
        }

        $cacheKey = 'gdrive_sa_token_' . md5($data['client_email']);
        $cachedToken = cache($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $b64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $b64Claims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($claims)));
        $signatureInput = $b64Header . '.' . $b64Claims;

        $privateKey = openssl_pkey_get_private($data['private_key']);
        if (!$privateKey) {
            throw new \Exception("Invalid RSA private key in Service Account JSON. Ensure the key was downloaded directly from Google Cloud.");
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception("Failed to sign OAuth assertion token using OpenSSL.");
        }

        $b64Sig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $signatureInput . '.' . $b64Sig;

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);

        if (!$response->successful()) {
            $errData = $response->json();
            $errDesc = $errData['error_description'] ?? ($errData['error'] ?? $response->body());
            throw new \Exception("Google OAuth token request failed: " . $errDesc);
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;
        if (!$accessToken) {
            throw new \Exception("Google OAuth did not return an access token.");
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
        cache([$cacheKey => $accessToken], now()->addSeconds(max(60, $expiresIn - 300)));

        return $accessToken;
    }

    /**
     * Perform an authenticated Google Drive v3 REST API call
     */
    public function fetchDriveApi(string $endpoint, array $queryParams = [], ?int $universityId = null, ?string $explicitJson = null, ?string $explicitApiKey = null): array
    {
        $uniId = Setting::resolveUniversityId($universityId);
        $saJson = $explicitJson !== null ? $explicitJson : (string) Setting::get('gdrive_service_account_json', '', $uniId);
        $apiKey = $explicitApiKey !== null ? $explicitApiKey : (string) Setting::get('gdrive_api_key', '', $uniId);

        $url = "https://www.googleapis.com/drive/v3/{$endpoint}";
        $queryParams['supportsAllDrives'] = 'true';
        $queryParams['includeItemsFromAllDrives'] = 'true';

        $req = Http::timeout(25);

        if (!empty(trim($saJson))) {
            $token = $this->getAccessToken(trim($saJson));
            $req = $req->withToken($token);
        } elseif (!empty(trim($apiKey))) {
            $queryParams['key'] = trim($apiKey);
        } else {
            throw new \Exception("No Google Drive credentials configured.");
        }

        $res = $req->get($url, $queryParams);
        return [
            'status' => $res->status(),
            'successful' => $res->successful(),
            'data' => $res->json() ?: [],
            'body' => $res->body()
        ];
    }

    /**
     * Fetch raw HTML for a Google Drive Folder ID (Public Scraper Mode)
     */
    public function fetchFolderHtml(string $folderId): string
    {
        try {
            $response = Http::withHeaders($this->headers)->timeout(20)->get($this->baseUrl . $folderId);
            return $response->successful() ? $response->body() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Discover course subfolders from a root folder
     */
    public function discoverCourses(string $rootFolderId, ?int $universityId = null): array
    {
        $uniId = Setting::resolveUniversityId($universityId);

        $authType = Setting::get('gdrive_auth_type', 'public', $uniId);

        // 1. If Private mode (or credentials configured), use Authenticated Google Drive API v3
        if ($authType === 'private' || ($authType !== 'public' && $this->hasCredentials($uniId))) {
            try {
                $params = [
                    'q' => "'{$rootFolderId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                    'fields' => 'files(id, name, mimeType)',
                    'pageSize' => 1000
                ];
                $apiRes = $this->fetchDriveApi('files', $params, $uniId);
                if ($apiRes['successful']) {
                    $files = $apiRes['data']['files'] ?? [];
                    $courses = [];
                    $ignoredNames = ['videos', 'video', 'recordings', 'recording', 'weeks', 'week', 'docs', 'documents', 'slides', 'materials', 'assets', 'quizzes'];

                    foreach ($files as $f) {
                        $folderName = trim($f['name']);
                        if (in_array(strtolower($folderName), $ignoredNames) || preg_match('/^week\s*\d+/i', $folderName)) {
                            continue;
                        }

                        $courses[] = [
                            'folder_id' => $f['id'],
                            'name' => $folderName,
                            'course_code' => $this->guessCourseCode($folderName)
                        ];
                    }

                    // If no subfolders found, the root folder itself might be the course
                    if (empty($courses)) {
                        $metaRes = $this->fetchDriveApi("files/{$rootFolderId}", ['fields' => 'id, name, mimeType'], $uniId);
                        $title = ($metaRes['successful'] && !empty($metaRes['data']['name'])) ? trim($metaRes['data']['name']) : 'Course Folder';
                        $courses[] = [
                            'folder_id' => $rootFolderId,
                            'name' => $title,
                            'course_code' => $this->guessCourseCode($title)
                        ];
                    }

                    if (!empty($courses)) {
                        return $courses;
                    }
                }
            } catch (\Throwable $e) {
                // If API failed, fall through to public scraper
            }
        }

        // 2. Fallback: Public web scraper
        return $this->discoverCoursesPublic($rootFolderId);
    }

    /**
     * Public scraper implementation for course discovery
     */
    protected function discoverCoursesPublic(string $rootFolderId): array
    {
        $html = $this->fetchFolderHtml($rootFolderId);
        
        $pattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($rootFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22((?:(?!\\\\x22|\").)+?)\\\\x22,\\\\x22application\\\\\/vnd\.google-apps\.folder\\\\x22/';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $courses = [];
        $ignoredNames = ['videos', 'video', 'recordings', 'recording', 'weeks', 'week', 'docs', 'documents', 'slides', 'materials', 'assets', 'quizzes'];

        foreach ($matches as $m) {
            $folderName = stripslashes($m[2]);
            if (in_array(strtolower(trim($folderName)), $ignoredNames) || preg_match('/^week\s*\d+/i', trim($folderName))) {
                continue;
            }

            $courses[] = [
                'folder_id' => $m[1],
                'name' => $folderName,
                'course_code' => $this->guessCourseCode($folderName)
            ];
        }

        // If no subfolders found, the root folder itself might be the course
        if (empty($courses)) {
            preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch);
            $title = $titleMatch ? trim(str_replace(['- Google Drive', '- Google ড্রাইভ'], '', $titleMatch[1])) : 'Course Folder';
            $courses[] = [
                'folder_id' => $rootFolderId,
                'name' => $title,
                'course_code' => $this->guessCourseCode($title)
            ];
        }

        return $courses;
    }

    /**
     * Scan a specific course folder for documents and video subfolders
     */
    public function scanCourseFolder(string $courseFolderId, ?int $universityId = null): array
    {
        $uniId = Setting::resolveUniversityId($universityId);

        $authType = Setting::get('gdrive_auth_type', 'public', $uniId);

        // 1. If Private mode (or credentials configured), use Authenticated Google Drive API v3
        if ($authType === 'private' || ($authType !== 'public' && $this->hasCredentials($uniId))) {
            try {
                $params = [
                    'q' => "'{$courseFolderId}' in parents and trashed = false",
                    'fields' => 'files(id, name, mimeType, webViewLink, webContentLink)',
                    'pageSize' => 1000
                ];
                $apiRes = $this->fetchDriveApi('files', $params, $uniId);
                if ($apiRes['successful']) {
                    $items = $apiRes['data']['files'] ?? [];
                    $documents = [];
                    $videosFolderId = null;
                    $directVideos = [];

                    foreach ($items as $item) {
                        $mime = $item['mimeType'] ?? '';
                        $fname = trim($item['name'] ?? '');
                        $fid = $item['id'];
                        $lower = strtolower($fname);

                        if ($mime === 'application/vnd.google-apps.folder') {
                            if ($lower === 'videos' || $lower === 'video') {
                                $videosFolderId = $fid;
                            }
                        } elseif (preg_match('/\.(pdf|pptx|docx|xlsx)$/i', $fname)) {
                            $documents[] = [
                                'file_id' => $fid,
                                'filename' => $fname,
                                'view_url' => "https://drive.google.com/file/d/{$fid}/view?usp=sharing",
                                'download_url' => "https://drive.usercontent.google.com/download?id={$fid}&export=download&confirm=t",
                                'type' => $this->classifyDocType($fname, $uniId)
                            ];
                        } elseif (preg_match('/\.mp4$/i', $fname)) {
                            $directVideos[] = [
                                'file_id' => $fid,
                                'filename' => $fname,
                                'view_url' => "https://drive.google.com/file/d/{$fid}/view?usp=sharing",
                                'embed_url' => "https://drive.google.com/file/d/{$fid}/preview"
                            ];
                        }
                    }

                    $weeksVideos = [];
                    if ($videosFolderId) {
                        $weeksVideos = $this->scanVideosFolderApi($videosFolderId, $uniId);
                    } elseif (!empty($directVideos)) {
                        $weeksVideos[1] = [
                            'folder_id' => $courseFolderId,
                            'week_num' => 1,
                            'week_name' => 'Week 1',
                            'videos' => $directVideos
                        ];
                    }

                    return [
                        'course_folder_id' => $courseFolderId,
                        'documents' => $documents,
                        'videos_folder_id' => $videosFolderId,
                        'weeks_videos' => $weeksVideos
                    ];
                }
            } catch (\Throwable $e) {
                // Fallback to public scraper
            }
        }

        // 2. Fallback: Public web scraper
        return $this->scanCourseFolderPublic($courseFolderId, $uniId);
    }

    /**
     * Scan Videos subfolder via Google Drive API v3
     */
    protected function scanVideosFolderApi(string $videosFolderId, ?int $universityId = null): array
    {
        $params = [
            'q' => "'{$videosFolderId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields' => 'files(id, name)',
            'pageSize' => 1000
        ];
        $apiRes = $this->fetchDriveApi('files', $params, $universityId);
        $weekFolders = $apiRes['successful'] ? ($apiRes['data']['files'] ?? []) : [];

        $weekMap = [];
        foreach ($weekFolders as $wf) {
            $wid = $wf['id'];
            $wname = trim($wf['name']);
            preg_match('/\d+/', $wname, $numMatch);
            $wnum = $numMatch ? (int) $numMatch[0] : 0;
            if ($wnum > 0) {
                $weekMap[$wnum] = [
                    'folder_id' => $wid,
                    'week_num' => $wnum,
                    'week_name' => $wname,
                    'videos' => $this->scanVideosInWeekApi($wid, $universityId)
                ];
            }
        }

        ksort($weekMap);
        return $weekMap;
    }

    /**
     * Scan video files inside a week folder via Google Drive API v3
     */
    protected function scanVideosInWeekApi(string $weekFolderId, ?int $universityId = null): array
    {
        $params = [
            'q' => "'{$weekFolderId}' in parents and trashed = false",
            'fields' => 'files(id, name, mimeType)',
            'pageSize' => 1000
        ];
        $apiRes = $this->fetchDriveApi('files', $params, $universityId);
        $items = $apiRes['successful'] ? ($apiRes['data']['files'] ?? []) : [];

        $videos = [];
        $seen = [];
        foreach ($items as $item) {
            $fid = $item['id'];
            $fname = trim($item['name']);
            if (!isset($seen[$fid]) && preg_match('/\.mp4$/i', $fname)) {
                $seen[$fid] = true;
                $videos[] = [
                    'file_id' => $fid,
                    'filename' => $fname,
                    'view_url' => "https://drive.google.com/file/d/{$fid}/view?usp=sharing",
                    'embed_url' => "https://drive.google.com/file/d/{$fid}/preview"
                ];
            }
        }
        return $videos;
    }

    /**
     * Public scraper implementation for course folder scanning
     */
    protected function scanCourseFolderPublic(string $courseFolderId, ?int $universityId = null): array
    {
        $html = $this->fetchFolderHtml($courseFolderId);

        // 1. Find root files (PDF, PPTX, DOCX, XLSX)
        $filePattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($courseFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22((?:(?!\\\\x22|").)+?\.(?:pdf|pptx|docx|xlsx))\\\\x22/i';
        preg_match_all($filePattern, $html, $fileMatches, PREG_SET_ORDER);

        $documents = [];
        foreach ($fileMatches as $fm) {
            $fname = stripslashes($fm[2]);
            $documents[] = [
                'file_id' => $fm[1],
                'filename' => $fname,
                'view_url' => "https://drive.google.com/file/d/{$fm[1]}/view?usp=sharing",
                'download_url' => "https://drive.usercontent.google.com/download?id={$fm[1]}&export=download&confirm=t",
                'type' => $this->classifyDocType($fname, $universityId)
            ];
        }

        // 2. Find Videos folder
        $videoFolderPattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($courseFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22Videos\\\\x22,\\\\x22application\\\\\/vnd\.google-apps\.folder\\\\x22/i';
        preg_match($videoFolderPattern, $html, $vfMatch);
        $videosFolderId = $vfMatch ? $vfMatch[1] : null;

        $weeksVideos = [];
        if ($videosFolderId) {
            $weeksVideos = $this->scanVideosFolderPublic($videosFolderId);
        }

        return [
            'course_folder_id' => $courseFolderId,
            'documents' => $documents,
            'videos_folder_id' => $videosFolderId,
            'weeks_videos' => $weeksVideos
        ];
    }

    /**
     * Scan Videos folder via public scraper
     */
    public function scanVideosFolder(string $videosFolderId): array
    {
        return $this->scanVideosFolderPublic($videosFolderId);
    }

    protected function scanVideosFolderPublic(string $videosFolderId): array
    {
        $html = $this->fetchFolderHtml($videosFolderId);

        $pattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($videosFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22(Week\s*\d+)\\\\x22/i';
        preg_match_all($pattern, $html, $weekMatches, PREG_SET_ORDER);

        $weekMap = [];
        foreach ($weekMatches as $wm) {
            $wid = $wm[1];
            $wname = trim($wm[2]);
            preg_match('/\d+/', $wname, $numMatch);
            $wnum = $numMatch ? (int)$numMatch[0] : 0;
            if ($wnum > 0) {
                $weekMap[$wnum] = [
                    'folder_id' => $wid,
                    'week_num' => $wnum,
                    'week_name' => $wname,
                    'videos' => $this->scanVideosInWeekPublic($wid)
                ];
            }
        }

        ksort($weekMap);
        return $weekMap;
    }

    /**
     * Scan video files inside a week folder via public scraper
     */
    protected function scanVideosInWeekPublic(string $weekFolderId): array
    {
        $html = $this->fetchFolderHtml($weekFolderId);
        $pattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($weekFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22((?:(?!\\\\x22|").)+?\.mp4)\\\\x22/i';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $videos = [];
        $seen = [];
        foreach ($matches as $m) {
            $fid = $m[1];
            if (!isset($seen[$fid])) {
                $seen[$fid] = true;
                $fname = stripslashes($m[2]);
                $videos[] = [
                    'file_id' => $fid,
                    'filename' => $fname,
                    'view_url' => "https://drive.google.com/file/d/{$fid}/view?usp=sharing",
                    'embed_url' => "https://drive.google.com/file/d/{$fid}/preview"
                ];
            }
        }
        return $videos;
    }

    /**
     * Document classification with exact filename match, custom mappings, and smart heuristic fallback
     */
    public function classifyDocType(string $filename, ?int $universityId = null): array
    {
        $cleanFn = trim($filename);
        $lower = strtolower($cleanFn);
        $lowerNoExt = strtolower(trim(pathinfo($cleanFn, PATHINFO_FILENAME)));
        $uniId = Setting::resolveUniversityId($universityId);

        $isMatch = function(string $cfgName) use ($lower, $lowerNoExt) {
            $cfg = strtolower(trim($cfgName));
            if ($cfg === '') return false;
            $cfgNoExt = strtolower(trim(pathinfo($cfg, PATHINFO_FILENAME)));

            if ($lower === $cfg) return true;
            if ($lowerNoExt === $cfg || $lowerNoExt === $cfgNoExt) return true;

            $normFile = preg_replace('/[^a-z0-9]/', '', $lowerNoExt);
            $normCfg = preg_replace('/[^a-z0-9]/', '', $cfgNoExt);
            if ($normFile !== '' && $normCfg !== '' && ($normFile === $normCfg || str_contains($normFile, $normCfg))) {
                return true;
            }

            return str_contains($lower, $cfg) || str_contains($lower, $cfgNoExt);
        };

        // 1. User-configured standard exact filenames / patterns for 4 core files
        $shPattern = (string) Setting::get('doc_pattern_student_handbook', '', $uniId);
        $ihPattern = (string) Setting::get('doc_pattern_instructor_handbook', '', $uniId);
        $tbPattern = (string) Setting::get('doc_pattern_test_bank', '', $uniId);
        $slidePattern = (string) Setting::get('doc_pattern_slides', '', $uniId);

        if ($isMatch($shPattern)) {
            return ['type' => 'student_handbook', 'label' => 'Course Student Handbook'];
        }
        if ($isMatch($ihPattern)) {
            return ['type' => 'instructor_handbook', 'label' => 'Course Instructor Handbook'];
        }
        if ($isMatch($tbPattern)) {
            return ['type' => 'test_bank', 'label' => 'Course Test / Question Bank'];
        }
        if ($isMatch($slidePattern)) {
            return ['type' => 'slides', 'label' => 'Course Lecture Slides'];
        }

        // 2. User-configured custom extra documents
        $customJson = Setting::get('doc_custom_mappings', '[]', $uniId);
        $customMappings = json_decode($customJson, true);
        if (is_array($customMappings)) {
            foreach ($customMappings as $mapping) {
                $pat = $mapping['pattern'] ?? '';
                if ($isMatch($pat)) {
                    $lbl = trim($mapping['label'] ?? '') ?: pathinfo($cleanFn, PATHINFO_FILENAME);
                    return ['type' => 'custom', 'label' => $lbl];
                }
            }
        }

        // 3. Fallback to smart heuristic detection
        if (str_contains($lower, 'student handbo') || str_contains($lower, 'student_handbo') || str_contains($lower, 'student-handbo') || str_contains($lower, 'syllabus')) {
            return ['type' => 'student_handbook', 'label' => 'Course Student Handbook'];
        }
        if (str_contains($lower, 'instructor handbo') || str_contains($lower, 'instructor_handbo') || str_contains($lower, 'faculty handbo')) {
            return ['type' => 'instructor_handbook', 'label' => 'Course Instructor Handbook'];
        }
        if (str_contains($lower, 'slide') || str_contains($lower, 'lecture') || str_contains($lower, 'presentation') || str_contains($lower, 'powerpoint')) {
            return ['type' => 'slides', 'label' => 'Course Lecture Slides'];
        }
        if (str_contains($lower, 'testbank') || str_contains($lower, 'test bank') || str_contains($lower, 'test-bank') || str_contains($lower, 'question') || str_contains($lower, 'quiz')) {
            return ['type' => 'test_bank', 'label' => 'Course Test / Question Bank'];
        }

        return ['type' => 'other', 'label' => pathinfo($cleanFn, PATHINFO_FILENAME)];
    }

    /**
     * Test connection to Google Drive root folder & validate credentials
     */
    public function testConnection(?string $folderId = null, ?string $serviceAccountJson = null, ?string $apiKey = null, ?int $universityId = null, ?string $authType = null): array
    {
        $uniId = Setting::resolveUniversityId($universityId);
        $targetFolderId = trim($folderId ?: Setting::get('gdrive_root_folder_id', '', $uniId));
        $resolvedAuthType = $authType ?: (string) Setting::get('gdrive_auth_type', 'public', $uniId);
        $saJson = $serviceAccountJson !== null ? trim($serviceAccountJson) : trim(Setting::get('gdrive_service_account_json', '', $uniId));
        $key = $apiKey !== null ? trim($apiKey) : trim(Setting::get('gdrive_api_key', '', $uniId));

        if (empty($targetFolderId)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid Google Drive Root Folder ID to test.'
            ];
        }

        // Case A: Private / Restricted Access Mode
        if ($resolvedAuthType === 'private') {
            if (empty($saJson)) {
                return [
                    'success' => false,
                    'message' => 'Private Mode Selected: Please paste your Google Service Account JSON key below to authenticate.'
                ];
            }

            $clientEmail = static::getServiceAccountEmail($saJson);
            if (empty($clientEmail)) {
                return [
                    'success' => false,
                    'message' => 'Invalid Service Account JSON format: Missing "client_email" property.'
                ];
            }

            try {
                $res = $this->fetchDriveApi("files/{$targetFolderId}", [
                    'fields' => 'id, name, mimeType, capabilities'
                ], $uniId, $saJson, $key);

                if ($res['successful']) {
                    $name = $res['data']['name'] ?? 'Root Folder';
                    return [
                        'success' => true,
                        'message' => "Google Drive API v3 connected! Found private folder: \"{$name}\" (Authenticated as: {$clientEmail}).",
                        'auth_mode' => 'service_account',
                        'client_email' => $clientEmail,
                        'folder_name' => $name
                    ];
                }

                $status = $res['status'];
                if ($status === 404) {
                    return [
                        'success' => false,
                        'message' => "Authentication succeeded for '{$clientEmail}', but folder ID '{$targetFolderId}' was not found. Please ensure the private folder or shared drive is shared with '{$clientEmail}' (Viewer permission).",
                        'auth_mode' => 'service_account',
                        'client_email' => $clientEmail
                    ];
                }

                if ($status === 403) {
                    return [
                        'success' => false,
                        'message' => "Access denied for '{$clientEmail}'. Please open Google Drive, click Share on the folder, and grant Viewer access to '{$clientEmail}'.",
                        'auth_mode' => 'service_account',
                        'client_email' => $clientEmail
                    ];
                }

                $errMsg = $res['data']['error']['message'] ?? "HTTP {$status}";
                return [
                    'success' => false,
                    'message' => "Google Drive API error: {$errMsg}"
                ];

            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => "Google Service Account Error: " . $e->getMessage()
                ];
            }
        }

        // Case B: Public Access Mode (Zero Credentials)
        try {
            $response = Http::withHeaders($this->headers)->timeout(15)->get($this->baseUrl . $targetFolderId);
            $status = $response->status();
            $body = $response->body();

            if ($status === 404) {
                return [
                    'success' => false,
                    'message' => "Google Drive folder '{$targetFolderId}' was not found (HTTP 404). Please verify the Folder ID or check link sharing."
                ];
            }

            if (str_contains($body, 'accounts.google.com/ServiceLogin') || str_contains($body, 'Sign in - Google Accounts') || !preg_match('/\\x5b\\x22[a-zA-Z0-9_\-]+\\x22/', $body)) {
                preg_match('/<title>([^<]+)<\/title>/', $body, $titleMatch);
                $title = $titleMatch ? trim(str_replace(['- Google Drive', '- Google ড্রাইভ'], '', $titleMatch[1])) : '';

                if (!empty($title) && !str_contains($title, 'Sign in') && !str_contains($title, 'Error')) {
                    return [
                        'success' => true,
                        'message' => "Connected successfully to public folder: \"{$title}\" (Public access ready).",
                        'auth_mode' => 'public',
                        'folder_name' => $title
                    ];
                }

                return [
                    'success' => false,
                    'message' => "This Google Drive folder is private or requires Google login. Please change Google Drive Access Type to 'Private / Restricted Folder' above and provide a Service Account JSON key, or change folder sharing in Google Drive to 'Anyone with the link can view'.",
                    'auth_mode' => 'restricted_detected'
                ];
            }

            preg_match('/<title>([^<]+)<\/title>/', $body, $titleMatch);
            $title = $titleMatch ? trim(str_replace(['- Google Drive', '- Google ড্রাইভ'], '', $titleMatch[1])) : 'Course Root Folder';

            return [
                'success' => true,
                'message' => "Connected successfully to public folder: \"{$title}\" (Public access ready).",
                'auth_mode' => 'public',
                'folder_name' => $title
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Network error connecting to Google Drive: " . $e->getMessage()
            ];
        }
    }

    protected function guessCourseCode(string $name): string
    {
        if (preg_match('/([A-Z]{2,4}\s*\d{2,4})/i', $name, $matches)) {
            return strtoupper(str_replace(' ', '', $matches[1]));
        }
        return 'COURSE';
    }
}
