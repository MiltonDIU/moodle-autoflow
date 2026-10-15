<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class MoodleApiService
{
    protected ?string $moodleUrl;
    protected ?string $token;
    protected ?int $templateCourseId;

    public function __construct()
    {
        $this->moodleUrl = rtrim(Setting::get('moodle_url', 'http://elearn.local'), '/');
        $this->token = Setting::get('moodle_token', '');
        $this->templateCourseId = (int) Setting::get('moodle_template_course_id', 130);
    }

    /**
     * Test Moodle connection prioritizing remote Web Services REST API
     */
    public function testConnection(?string $customUrl = null, ?string $customToken = null): array
    {
        $url = rtrim($customUrl ?: $this->moodleUrl, '/');
        $token = $customToken !== null ? $customToken : $this->token;

        // 1. If Token is provided or URL is a remote web domain, test via Web Services REST API
        if (!empty($token) || (!str_contains($url, 'localhost') && !str_contains($url, 'elearn.local'))) {
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => "Moodle URL is set to '{$url}', but Web Services REST Token is missing! Please provide the token."
                ];
            }

            try {
                $endpoint = "{$url}/webservice/rest/server.php";
                $response = Http::timeout(15)->withoutVerifying()->get($endpoint, [
                    'wstoken' => $token,
                    'wsfunction' => 'core_webservice_get_site_info',
                    'moodlewsrestformat' => 'json'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['exception']) || isset($data['errorcode'])) {
                        $errMsg = $data['message'] ?? $data['errorcode'];
                        return [
                            'success' => false,
                            'message' => "Moodle API Error: {$errMsg} (URL: {$url})"
                        ];
                    }

                    $sitename = $data['sitename'] ?? 'Moodle LMS';
                    $user = $data['fullname'] ?? ($data['username'] ?? 'Admin');
                    $release = $data['release'] ?? 'Standard';

                    return [
                        'success' => true,
                        'message' => "Connected to Remote Moodle: \"{$sitename}\" | User: {$user} | Release: {$release} (Host: {$url})"
                    ];
                }

                return [
                    'success' => false,
                    'message' => "Failed to reach Moodle server (HTTP {$response->status()}). Please verify the URL: {$endpoint}"
                ];

            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => "Connection failed: " . $e->getMessage()
                ];
            }
        }

        // 2. Fallback to local CLI if URL is elearn.local/localhost
        $cliCheck = Process::run("php /home/milton/Projects/elearn/admin/cli/cfg.php --name=release");
        if ($cliCheck->successful()) {
            return [
                'success' => true,
                'message' => 'Connected to Local Moodle CLI: ' . trim($cliCheck->output()) . ' (' . $url . ')'
            ];
        }

        return [
            'success' => false,
            'message' => "Cannot connect to Moodle. Please verify URL: {$url}"
        ];
    }

    /**
     * Duplicate or sync course with dynamic discovery and auto-creation
     */
    public function provisionCourse(string $courseName, string $courseCode, array $curriculum, array $processedVideos, array $generalDocs, ?int $existingCourseId = null): array
    {
        // Save payload to temp json for the PHP CLI runner
        $payload = [
            'course_id' => $existingCourseId,
            'fullname' => $courseName,
            'shortname' => $courseCode,
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
