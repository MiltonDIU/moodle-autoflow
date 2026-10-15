<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleDriveService
{
    protected string $baseUrl = 'https://drive.google.com/drive/folders/';
    protected array $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ];

    /**
     * Fetch raw HTML for a Google Drive Folder ID
     */
    public function fetchFolderHtml(string $folderId): string
    {
        $response = Http::withHeaders($this->headers)->get($this->baseUrl . $folderId);
        return $response->successful() ? $response->body() : '';
    }

    /**
     * Discover course subfolders from a root folder
     */
    public function discoverCourses(string $rootFolderId): array
    {
        $html = $this->fetchFolderHtml($rootFolderId);
        
        // Find subfolders: ["<subfolder_id>",["<root_folder_id>"],"<name>","application/vnd.google-apps.folder"
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

        // If no subfolders found, the root folder itself might be the course!
        if (empty($courses)) {
            preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch);
            $title = $titleMatch ? trim(str_replace('- Google Drive', '', str_replace('- Google ড্রাইভ', '', $titleMatch[1]))) : 'Course Folder';
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
    public function scanCourseFolder(string $courseFolderId): array
    {
        $html = $this->fetchFolderHtml($courseFolderId);

        // 1. Find root files (PDF, PPTX, DOCX)
        $filePattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($courseFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22((?:(?!\\\\x22|").)+?\.(?:pdf|pptx|docx|xlsx))\\\\x22/i';
        preg_match_all($filePattern, $html, $fileMatches, PREG_SET_ORDER);

        $documents = [];
        foreach ($fileMatches as $fm) {
            $fname = stripslashes($fm[2]);
            $documents[] = [
                'file_id' => $fm[1],
                'filename' => $fname,
                'view_url' => "https://drive.google.com/file/d/{$fm[1]}/view?usp=sharing",
                'type' => $this->classifyDocType($fname)
            ];
        }

        // 2. Find Videos folder
        $videoFolderPattern = '/\\\\x5b\\\\x22([a-zA-Z0-9_\-]+)\\\\x22,\\\\x5b\\\\x22' . preg_quote($courseFolderId, '/') . '\\\\x22\\\\x5d,\\\\x22Videos\\\\x22,\\\\x22application\\\\\/vnd\.google-apps\.folder\\\\x22/i';
        preg_match($videoFolderPattern, $html, $vfMatch);
        $videosFolderId = $vfMatch ? $vfMatch[1] : null;

        $weeksVideos = [];
        if ($videosFolderId) {
            $weeksVideos = $this->scanVideosFolder($videosFolderId);
        }

        return [
            'course_folder_id' => $courseFolderId,
            'documents' => $documents,
            'videos_folder_id' => $videosFolderId,
            'weeks_videos' => $weeksVideos
        ];
    }

    /**
     * Scan the Videos folder for 16 weeks
     */
    public function scanVideosFolder(string $videosFolderId): array
    {
        $html = $this->fetchFolderHtml($videosFolderId);

        // Find Week 1..16 subfolders
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
                    'videos' => $this->scanVideosInWeek($wid)
                ];
            }
        }

        ksort($weekMap);
        return $weekMap;
    }

    /**
     * Scan video files inside a week folder
     */
    protected function scanVideosInWeek(string $weekFolderId): array
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

    protected function classifyDocType(string $filename): string
    {
        $lower = strtolower($filename);
        if (str_contains($lower, 'student handbo')) return 'student_handbook';
        if (str_contains($lower, 'instructor handbo')) return 'instructor_handbook';
        if (str_contains($lower, 'slide')) return 'slides';
        if (str_contains($lower, 'test bank')) return 'test_bank';
        return 'other';
    }

    protected function guessCourseCode(string $name): string
    {
        if (preg_match('/([A-Z]{2,4}\s*\d{2,4})/i', $name, $matches)) {
            return strtoupper(str_replace(' ', '', $matches[1]));
        }
        return 'COURSE';
    }
}
