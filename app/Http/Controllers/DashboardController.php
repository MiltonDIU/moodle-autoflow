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
            'moodle_url' => Setting::get('moodle_url', 'https://devlms.daffodilvarsity.edu.bd', $university->id),
            'root_folder' => Setting::get('gdrive_root_folder_id', '1BcFVmPVoN9HMPSUNw1vqnOuPYKkDPcnR', $university->id),
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
        $university = $this->getActiveUniversity();

        foreach ($request->except(['_token']) as $key => $val) {
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
        $folderId = $request->input('folder_id') ?: Setting::get('gdrive_root_folder_id', '1BcFVmPVoN9HMPSUNw1vqnOuPYKkDPcnR', $university->id);
        $discovered = $this->driveService->discoverCourses($folderId);

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

    public function processCourse($id)
    {
        $course = Course::findOrFail($id);
        $uniId = $course->university_id ?: 1;

        $course->update(['status' => 'processing', 'progress' => 15]);

        try {
            // 1. Scan Drive for files & videos
            $scanData = $this->driveService->scanCourseFolder($course->gdrive_folder_id);
            $course->update(['progress' => 35]);

            // 2. Extract Curriculum using selected AI Provider
            $aiProviderName = Setting::get('ai_provider', 'gemini', $uniId);
            $curriculum = $this->aiExtractor->extractCurriculum($scanData['documents'], $course->name, $aiProviderName);
            $course->update(['extracted_data' => $curriculum, 'progress' => 60]);

            // 3. Process Videos via Selected Driver
            $driverName = Setting::get('video_storage_driver', 'gdrive', $uniId);
            $driver = VideoStorageManager::getDriver($driverName);

            $processedVideos = [];
            foreach ($scanData['weeks_videos'] as $wnum => $wdata) {
                $processedVideos[$wnum] = [];
                foreach ($wdata['videos'] as $v) {
                    $res = $driver->processVideo([
                        'file_id' => $v['file_id'],
                        'filename' => $v['filename'],
                        'course_code' => $course->course_code,
                        'week_num' => $wnum
                    ]);
                    $processedVideos[$wnum][] = $res;
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
                $course->moodle_course_id
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
