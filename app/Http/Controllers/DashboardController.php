<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Course;
use App\Models\University;
use App\Services\GoogleDriveService;
use App\Services\AiCourseExtractorService;
use App\Services\AiProviders\AiProviderManager;
use App\Services\VideoStorage\VideoStorageManager;
use App\Services\MoodleApiService;

class DashboardController extends Controller
{
    protected GoogleDriveService $driveService;
    protected AiCourseExtractorService $aiExtractor;
    protected MoodleApiService $moodleService;

    public function __construct(
        GoogleDriveService $driveService,
        AiCourseExtractorService $aiExtractor,
        MoodleApiService $moodleService
    ) {
        $this->driveService = $driveService;
        $this->aiExtractor = $aiExtractor;
        $this->moodleService = $moodleService;
    }

    protected function getActiveUniversity(): University
    {
        $uniId = session('active_university_id', auth()->user()?->university_id ?: 1);
        $uni = University::find($uniId);

        if (!$uni) {
            $uni = University::firstOrCreate(
                ['slug' => 'nextgen'],
                ['name' => 'NextGen University', 'status' => 'active']
            );
        }

        return $uni;
    }

    public function index()
    {
        $university = $this->getActiveUniversity();
        $courses = Course::where('university_id', $university->id)->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_courses' => $courses->count(),
            'synced_courses' => $courses->where('status', 'completed')->count(),
            'active_driver' => Setting::get('video_storage_driver', 'gdrive', $university->id),
            'active_ai' => Setting::get('ai_provider', 'gemini', $university->id),
            'moodle_url' => Setting::get('moodle_url', '', $university->id),
            'root_folder' => Setting::get('gdrive_root_folder_id', '', $university->id),
            'view_all' => request('view') === 'all',
            'total_stored' => Course::where('university_id', $university->id)->count(),
        ];

        return view('dashboard', compact('courses', 'stats', 'university'));
    }

    public function settings()
    {
        $university = $this->getActiveUniversity();
        $settings = Setting::getAllForUniversity($university->id);

        return view('settings', compact('settings', 'university'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->canManageSettings()) {
            return redirect()->route('settings')->with('error', 'Access Denied: Instructors do not have permission to modify institutional settings.');
        }

        $university = $this->getActiveUniversity();

        // Process custom extra document mappings
        $customDocs = [];
        $extraLabels = $request->input('custom_doc_labels', []);
        $extraPatterns = $request->input('custom_doc_patterns', []);
        if (is_array($extraLabels)) {
            foreach ($extraLabels as $idx => $label) {
                $pat = $extraPatterns[$idx] ?? '';
                if (!empty(trim($label)) && !empty(trim($pat))) {
                    $customDocs[] = [
                        'label' => trim($label),
                        'pattern' => trim($pat)
                    ];
                }
            }
        }
        Setting::set('doc_custom_mappings', json_encode($customDocs), $university->id);

        foreach ($request->except(['_token', 'custom_doc_labels', 'custom_doc_patterns']) as $key => $val) {
            Setting::set($key, $val, $university->id);
        }

        return redirect()->route('settings')->with('success', "Configurations for '{$university->name}' updated successfully.");
    }

    public function testConnection(Request $request)
    {
        $target = $request->input('target') ?: $request->input('driver');
        $university = $this->getActiveUniversity();

        if ($target === 'moodle') {
            $moodleUrl = $request->input('moodle_url') ?: Setting::get('moodle_url', 'http://elearn.local', $university->id);
            $moodleToken = $request->input('moodle_token') ?: Setting::get('moodle_token', '', $university->id);
            $res = $this->moodleService->testConnection($moodleUrl, $moodleToken);
        } elseif ($target === 'gdrive' || $target === 'google_drive') {
            $folderId = $request->input('folder_id') ?: $request->input('gdrive_root_folder_id') ?: Setting::get('gdrive_root_folder_id', '', $university->id);
            $serviceAccountJson = $request->input('service_account_json') ?: $request->input('gdrive_service_account_json') ?: Setting::get('gdrive_service_account_json', '', $university->id);
            $apiKey = $request->input('api_key') ?: $request->input('gdrive_api_key') ?: Setting::get('gdrive_api_key', '', $university->id);
            $authType = $request->input('auth_type') ?: $request->input('gdrive_auth_type') ?: Setting::get('gdrive_auth_type', 'public', $university->id);
            $res = $this->driveService->testConnection($folderId, $serviceAccountJson, $apiKey, $university->id, $authType);
        } elseif (in_array($target, ['gemini', 'openai', 'claude', 'local', 'ai'])) {
            $provider = ($target === 'ai') ? Setting::get('ai_provider', 'gemini', $university->id) : $target;
            $apiKey = $request->input('api_key') ?: $request->input($provider . '_api_key') ?: Setting::get($provider . '_api_key', '', $university->id);
            $model = $request->input('model') ?: $request->input($provider . '_model') ?: Setting::get($provider . '_model', null, $university->id);
            $driver = AiProviderManager::getDriver($provider, $apiKey, $model);
            $res = $driver->testConnection();
        } else {
            $storage = VideoStorageManager::getDriver($target);
            $res = $storage->testConnection();
        }

        return response()->json($res);
    }

    public function scanFolder(Request $request)
    {
        $university = $this->getActiveUniversity();
        $folderId = trim($request->input('folder_id') ?: Setting::get('gdrive_root_folder_id', '', $university->id));

        if (empty($folderId)) {
            return redirect()->route('dashboard')->with('error', "Please enter a valid Google Drive Folder ID to scan courses for '{$university->name}'.");
        }

        // Save folder ID if not set
        if (!Setting::get('gdrive_root_folder_id', null, $university->id)) {
            Setting::set('gdrive_root_folder_id', $folderId, $university->id);
        }

        $discovered = $this->driveService->discoverCourses($folderId, $university->id);

        $savedCount = 0;
        foreach ($discovered as $c) {
            $course = Course::firstOrCreate(
                [
                    'university_id' => $university->id,
                    'gdrive_folder_id' => $c['folder_id']
                ],
                [
                    'name' => $c['name'],
                    'course_code' => $c['course_code'],
                    'status' => 'discovered',
                    'storage_driver' => Setting::get('video_storage_driver', 'gdrive', $university->id)
                ]
            );
            if ($course->wasRecentlyCreated) $savedCount++;
        }

        return redirect()->route('dashboard')->with('success', "Discovered " . count($discovered) . " course folder(s) for {$university->name}.");
    }

    public function processCourse(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        $uniId = $course->university_id ?: 1;

        $startDate = $request->input('start_date') 
            ?: ($course->start_date ? $course->start_date->format('Y-m-d') : Setting::get('semester_start_date', date('Y-m-d'), $uniId));

        $course->update([
            'status' => 'processing',
            'progress' => 15,
            'start_date' => $startDate
        ]);

        try {
            // 1. Scan Drive for files & videos
            $scanData = $this->driveService->scanCourseFolder($course->gdrive_folder_id, $uniId);
            $course->update(['progress' => 35]);

            // 2. Extract Curriculum using selected AI Provider (Smart Cache: reuse if already extracted)
            $existingCurriculum = (is_array($course->extracted_data) && count($course->extracted_data) >= 14) 
                ? $course->extracted_data 
                : null;

            if ($existingCurriculum && !$request->boolean('force_ai')) {
                $curriculum = $existingCurriculum;
            } else {
                $aiProviderName = Setting::get('ai_provider', 'gemini', $uniId);
                $curriculum = $this->aiExtractor->extractCurriculum($scanData['documents'], $course->name, $aiProviderName);
                $course->update(['extracted_data' => $curriculum]);
            }
            $course->update(['progress' => 60]);

            // 3. Process Videos via Selected Driver (Smart Incremental: do not re-encode existing videos)
            $driverName = Setting::get('video_storage_driver', 'gdrive', $uniId);
            $driver = VideoStorageManager::getDriver($driverName);

            $existingVideoMap = is_array($course->video_map) ? $course->video_map : [];

            // Index existing processed videos by file_id and week + filename
            $existingLookup = [];
            foreach ($existingVideoMap as $w => $vList) {
                if (is_array($vList)) {
                    foreach ($vList as $existingV) {
                        $fId = $existingV['file_id'] ?? null;
                        $fName = $existingV['filename'] ?? null;
                        $vDriver = $existingV['driver'] ?? null;

                        $hasVideo = !empty($existingV['video_id']) || !empty($existingV['stream_url']) || !empty($existingV['embed_code']);
                        $isSameDriver = ($vDriver === $driverName) || ($vDriver === 'bunny_fallback_gdrive' && $driverName === 'bunny');

                        if ($hasVideo && $isSameDriver) {
                            if ($fId) {
                                $existingLookup['id_' . $fId] = $existingV;
                            }
                            if ($fName) {
                                $cleanName = strtolower(trim($fName));
                                $existingLookup['fn_' . $w . '_' . $cleanName] = $existingV;
                                $existingLookup['fn_any_' . $cleanName] = $existingV;
                            }
                        }
                    }
                }
            }

            $processedVideos = [];
            foreach ($scanData['weeks_videos'] as $wnum => $wdata) {
                $processedVideos[$wnum] = [];
                foreach ($wdata['videos'] as $v) {
                    $fId = $v['file_id'] ?? '';
                    $fName = strtolower(trim($v['filename'] ?? ''));

                    $matchedExisting = null;
                    if ($fId && isset($existingLookup['id_' . $fId])) {
                        $matchedExisting = $existingLookup['id_' . $fId];
                    } elseif ($fName && isset($existingLookup['fn_' . $wnum . '_' . $fName])) {
                        $matchedExisting = $existingLookup['fn_' . $wnum . '_' . $fName];
                    } elseif ($fName && isset($existingLookup['fn_any_' . $fName])) {
                        $matchedExisting = $existingLookup['fn_any_' . $fName];
                    }

                    if ($matchedExisting) {
                        // Already processed with active driver: reuse existing metadata!
                        $matchedExisting['file_id'] = $fId ?: ($matchedExisting['file_id'] ?? '');
                        $matchedExisting['filename'] = $v['filename'] ?? ($matchedExisting['filename'] ?? '');
                        $processedVideos[$wnum][] = $matchedExisting;
                    } else {
                        // Brand new video: process / encode via driver
                        $res = $driver->processVideo([
                            'file_id' => $v['file_id'],
                            'filename' => $v['filename'],
                            'course_code' => $course->course_code,
                            'course_name' => $course->name,
                            'week_num' => $wnum
                        ]);
                        $res['file_id'] = $v['file_id'];
                        $res['filename'] = $v['filename'];
                        $processedVideos[$wnum][] = $res;
                    }
                }
            }
            $course->update(['video_map' => $processedVideos, 'storage_driver' => $driverName, 'progress' => 80]);

            // 4. Provision / Sync with Moodle
            $moodleRes = $this->moodleService->provisionCourse(
                $course->name,
                $course->course_code,
                $curriculum,
                $processedVideos,
                $scanData['documents'],
                $course->moodle_course_id,
                $startDate
            );

            $course->update([
                'status' => 'completed',
                'progress' => 100,
                'moodle_course_id' => $moodleRes['moodle_course_id'],
                'error_message' => null
            ]);

            return redirect()->route('dashboard')->with('success', "Course '{$course->name}' successfully processed & synced to Moodle!");

        } catch (\Exception $e) {
            $course->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            return redirect()->route('dashboard')->with('error', "Processing failed: " . $e->getMessage());
        }
    }

    public function preview($id)
    {
        $course = Course::findOrFail($id);
        return view('preview', compact('course'));
    }
}
