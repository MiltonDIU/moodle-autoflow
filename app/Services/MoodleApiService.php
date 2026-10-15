<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class MoodleApiService
{
    protected string $moodleUrl;
    protected ?string $token = null;

    public function __construct()
    {
        $this->moodleUrl = rtrim(Setting::get('moodle_url', 'http://elearn.local'), '/');
        $this->token = Setting::get('moodle_token') ?: '';
    }

    /**
     * Test connection to Moodle Web Services with strict validation
     */
    public function testConnection(?string $customUrl = null, ?string $customToken = null): array
    {
        $url = rtrim($customUrl ?: $this->moodleUrl, '/');
        $token = $customToken ?: $this->token;

        if (empty($url)) {
            return [
                'success' => false,
                'message' => 'Moodle URL is empty. Please enter a valid URL (e.g., http://elearn.local).'
            ];
        }

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Moodle Web Service Token is empty. Please generate a token in Moodle Site Administration.'
            ];
        }

        $endpoint = "{$url}/webservice/rest/server.php";

        try {
            $response = Http::timeout(8)
                ->asForm()
                ->post($endpoint, [
                    'wstoken' => $token,
                    'wsfunction' => 'core_webservice_get_site_info',
                    'moodlewsrestformat' => 'json'
                ]);

            if ($response->status() === 403) {
                return [
                    'success' => false,
                    'message' => "Failed to reach Moodle server (HTTP 403 Forbidden). Please check server permissions, web server rules, or reverse proxy settings."
                ];
            }

            if ($response->status() === 404) {
                return [
                    'success' => false,
                    'message' => "Moodle Web Service endpoint not found (HTTP 404). Verify that {$endpoint} is accessible."
                ];
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => "Failed to connect to Moodle (HTTP {$response->status()}). Please verify the URL and server accessibility."
                ];
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                $errorMsg = $data['message'] ?? 'Invalid token or web service permission denied.';
                return [
                    'success' => false,
                    'message' => "Moodle Error: {$errorMsg}"
                ];
            }

            if (!empty($data['sitename'])) {
                $siteName = $data['sitename'];
                $release = $data['release'] ?? 'Unknown version';
                return [
                    'success' => true,
                    'message' => "Connected successfully to {$siteName} (Moodle {$release})!",
                    'site_info' => $data
                ];
            }

            return [
                'success' => false,
                'message' => 'Received unexpected response format from Moodle server.'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Duplicate or sync course with dynamic discovery, auto-creation, and semester start date
     */
    public function provisionCourse(
        string $courseName,
        string $courseCode,
        array $curriculum,
        array $processedVideos,
        array $generalDocs,
        ?int $existingCourseId = null,
        ?string $startDate = null
    ): array {
        // Save payload to temp json for the PHP CLI runner
        $payload = [
            'course_id' => $existingCourseId,
            'fullname' => $courseName,
            'shortname' => $courseCode,
            'start_date' => $startDate,
            'category_id' => (int) Setting::get('moodle_category_id', 2),
            'curriculum' => $curriculum,
            'processed_videos' => $processedVideos,
            'general_docs' => $generalDocs
        ];

        $tmpFile = '/tmp/moodle_sync_payload.json';
        file_put_contents($tmpFile, json_encode($payload));

        // Run sync script on Moodle
        $syncScript = '/home/milton/Projects/NextGen/moodle_sync_runner.php';
        $cmd = "php {$syncScript}";
        $result = Process::timeout(600)->run($cmd);

        $output = $result->output();

        // Extract matched or created course_id from output
        $moodleCourseId = $existingCourseId;
        if (preg_match('/FINAL_MOODLE_COURSE_ID:(\d+)/', $output, $m)) {
            $moodleCourseId = (int) $m[1];
        }

        return [
            'success' => $result->successful(),
            'output' => $output,
            'moodle_course_id' => $moodleCourseId,
            'course_url' => $moodleCourseId ? "{$this->moodleUrl}/course/view.php?id={$moodleCourseId}" : $this->moodleUrl
        ];
    }
}
