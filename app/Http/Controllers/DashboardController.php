<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Setting;
use App\Services\GoogleDriveService;
use App\Services\AiCourseExtractorService;
use App\Services\MoodleApiService;
use App\Services\VideoStorage\VideoStorageManager;
use App\Services\AiProviders\AiProviderManager;

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

    public function index(Request $request)
    {
        $rootFolder = $request->query('folder_id') ?: Setting::get('gdrive_root_folder_id', '1ujsR7VA16xA5lGfV-pS4xFzq6DQGT22c');
        $viewAll = $request->query('view') === 'all';
        $totalStored = Course::count();

        if ($viewAll) {
            $courses = Course::orderBy('created_at', 'desc')->get();
        } else {
            // Strictly show courses discovered under this root folder
            $courses = Course::where('root_folder_id', $rootFolder)->orderBy('created_at', 'desc')->get();
            if ($courses->isEmpty()) {
                // If the root folder itself was entered as a course folder
                $courses = Course::where('gdrive_folder_id', $rootFolder)->get();
            }
        }

        $stats = [
            'total_courses' => $courses->count(),
            'total_stored' => $totalStored,
            'synced_courses' => $courses->where('status', 'completed')->count(),
            'active_driver' => Setting::get('video_storage_driver', 'gdrive'),
            'active_ai' => Setting::get('ai_provider', 'gemini'),
            'moodle_url' => Setting::get('moodle_url', 'http://elearn.local'),
            'root_folder' => $rootFolder,
            'view_all' => $viewAll,
        ];

        return view('dashboard', compact('courses', 'stats'));
    }

    public function settings()
    {
        $settings = [
            // Moodle
            'moodle_url' => Setting::get('moodle_url', 'http://elearn.local'),
            'moodle_token' => Setting::get('moodle_token', ''),
            'moodle_template_course_id' => Setting::get('moodle_template_course_id', 130),
            'moodle_category_id' => Setting::get('moodle_category_id', 2),

            // AI Providers
            'ai_provider' => Setting::get('ai_provider', 'gemini'),
            'gemini_api_key' => Setting::get('gemini_api_key', ''),
            'gemini_model' => Setting::get('gemini_model', 'gemini-2.5-flash'),
            'openai_api_key' => Setting::get('openai_api_key', ''),
            'openai_model' => Setting::get('openai_model', 'gpt-4o-mini'),
            'claude_api_key' => Setting::get('claude_api_key', ''),
            'claude_model' => Setting::get('claude_model', 'claude-3-5-haiku-20241022'),

            // Google Drive
            'gdrive_root_folder_id' => Setting::get('gdrive_root_folder_id', '1ujsR7VA16xA5lGfV-pS4xFzq6DQGT22c'),

            // Video Storage Engines
            'video_storage_driver' => Setting::get('video_storage_driver', 'gdrive'),
            'bunny_api_key' => Setting::get('bunny_api_key', ''),
            'bunny_library_id' => Setting::get('bunny_library_id', ''),
            'bunny_hostname' => Setting::get('bunny_hostname', 'iframe.mediadelivery.net'),
            'r2_account_id' => Setting::get('r2_account_id', ''),
            'r2_access_key_id' => Setting::get('r2_access_key_id', ''),
            'r2_secret_access_key' => Setting::get('r2_secret_access_key', ''),
            'r2_bucket' => Setting::get('r2_bucket', ''),
            'r2_custom_domain' => Setting::get('r2_custom_domain', ''),
            's3_key' => Setting::get('s3_key', ''),
            's3_secret' => Setting::get('s3_secret', ''),
            's3_region' => Setting::get('s3_region', 'us-east-1'),
            's3_bucket' => Setting::get('s3_bucket', ''),
        ];

        return view('settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        foreach ($request->except('_token') as $key => $val) {
            Setting::set($key, $val);
        }

        return redirect()->route('settings')->with('success', 'Settings updated successfully.');
    }

    public function testConnection(Request $request)
    {
        $target = $request->input('target') ?: $request->input('driver');

        if ($target === 'moodle') {
            $moodleUrl = $request->input('moodle_url');
            $moodleToken = $request->input('moodle_token');
            $res = $this->moodleService->testConnection($moodleUrl, $moodleToken);
        } elseif (in_array($target, ['gemini', 'openai', 'claude', 'local', 'ai'])) {
            $provider = ($target === 'ai') ? Setting::get('ai_provider', 'gemini') : $target;
            $apiKey = $request->input('api_key') ?: $request->input($provider . '_api_key');
            $model = $request->input('model') ?: $request->input($provider . '_model');
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
        $folderId = trim($request->input('folder_id') ?: Setting::get('gdrive_root_folder_id', '1ujsR7VA16xA5lGfV-pS4xFzq6DQGT22c'));
        Setting::set('gdrive_root_folder_id', $folderId);
        $discovered = $this->driveService->discoverCourses($folderId);

        $savedCount = 0;
        foreach ($discovered as $c) {
            $course = Course::updateOrCreate(
                ['gdrive_folder_id' => $c['folder_id']],
                [
                    'name' => $c['name'],
                    'course_code' => $c['course_code'],
                    'root_folder_id' => $folderId,
                    'status' => 'discovered',
                    'storage_driver' => Setting::get('video_storage_driver', 'gdrive')
                ]
            );
            if ($course->wasRecentlyCreated) $savedCount++;
        }

        return redirect()->route('dashboard', ['folder_id' => $folderId])->with('success', "Discovered " . count($discovered) . " course folder(s) under root folder.");
    }

    public function processCourse($id)
    {
        set_time_limit(600);
        $course = Course::findOrFail($id);
        $course->update(['status' => 'processing', 'progress' => 15]);

        try {
            // 1. Scan Drive for files & videos
            $scanData = $this->driveService->scanCourseFolder($course->gdrive_folder_id);
            $course->update(['progress' => 35]);

            // 2. Extract Curriculum using selected AI Provider
            $curriculum = $this->aiExtractor->extractCurriculum($scanData['documents'], $course->name);
            $course->update(['extracted_data' => $curriculum, 'progress' => 60]);

            // 3. Process Videos via Selected Driver
            $driverName = Setting::get('video_storage_driver', 'gdrive');
            $driver = VideoStorageManager::getDriver($driverName);

            $processedVideos = [];
            foreach ($scanData['weeks_videos'] as $wnum => $wdata) {
                $processedVideos[$wnum] = [];
                foreach ($wdata['videos'] as $v) {
                    $res = $driver->processVideo([
                        'file_id' => $v['file_id'],
                        'filename' => $v['filename'],
                        'course_code' => $course->course_code,
                        'course_name' => $course->name,
                        'week_num' => $wnum
                    ]);
                    $processedVideos[$wnum][] = $res;
                }
            }
            $course->update(['video_map' => $processedVideos, 'storage_driver' => $driverName, 'progress' => 80]);

            // 4. Provision / Sync with Moodle (Dynamic Matching & Auto-Creation)
            $moodleRes = $this->moodleService->provisionCourse(
                $course->name,
                $course->course_code,
                $curriculum,
                $processedVideos,
                $scanData['documents'],
                $course->moodle_course_id
            );

            if (!$moodleRes['success']) {
                throw new \Exception("Moodle Provisioning failed: " . ($moodleRes['output'] ?? 'Unknown error'));
            }

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
