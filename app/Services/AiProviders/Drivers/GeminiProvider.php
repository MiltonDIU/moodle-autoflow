<?php

namespace App\Services\AiProviders\Drivers;

use App\Services\AiProviders\Contracts\AiProviderInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = Setting::get('gemini_api_key', '');
        $this->model = Setting::get('gemini_model', 'gemini-2.5-flash');
    }

    public function extractCurriculum(array $documents, string $courseName): array
    {
        if (!$this->apiKey) {
            $local = new LocalProvider();
            return $local->extractCurriculum($documents, $courseName);
        }

        $prompt = $this->buildPrompt($courseName, $documents);

        $response = Http::timeout(300)->retry(2, 2000)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ]);

        if ($response->successful()) {
            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $parsed = json_decode($text, true);
            if (isset($parsed['weeks'])) {
                return $parsed['weeks'];
            }
        }

        $local = new LocalProvider();
        return $local->extractCurriculum($documents, $courseName);
    }

    public function testConnection(): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'message' => 'Google Gemini API key is missing.'];
        }

        try {
            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    ['parts' => [['text' => 'Ping. Return {"status": "ok"}']]]
                ],
                'generationConfig' => ['response_mime_type' => 'application/json']
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Successfully connected to Google Gemini ({$this->model})!"];
            }

            return ['success' => false, 'message' => "Gemini API error: HTTP " . $response->status() . " - " . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => "Gemini connection error: " . $e->getMessage()];
        }
    }

    protected function buildPrompt(string $courseName, array $documents): string
    {
        $template = Setting::get('ai_system_prompt') ?: Setting::getDefaultAiPrompt();
        $prompt = str_replace('{course_name}', $courseName, $template);

        if (!empty($documents)) {
            $prompt .= "\n\nAvailable Course Handbook Documents:\n";
            foreach ($documents as $doc) {
                $prompt .= "- " . ($doc['filename'] ?? 'Course Document') . "\n";
            }
        }
        return $prompt;
    }
}
