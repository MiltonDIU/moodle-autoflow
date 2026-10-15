<?php

namespace App\Services\AiProviders\Drivers;

use App\Services\AiProviders\Contracts\AiProviderInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?: Setting::get('openai_api_key', '');
        $this->model = $model ?: Setting::get('openai_model', 'gpt-4o-mini');
    }

    public function extractCurriculum(array $documents, string $courseName): array
    {
        if (!$this->apiKey) {
            $local = new LocalProvider();
            return $local->extractCurriculum($documents, $courseName);
        }

        $template = Setting::get('ai_system_prompt') ?: Setting::getDefaultAiPrompt();
        $prompt = str_replace('{course_name}', $courseName, $template);

        $response = Http::withToken($this->apiKey)->timeout(90)->post("https://api.openai.com/v1/chat/completions", [
            'model' => $this->model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are an instructional designer that generates structured curriculum in JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($content, true);
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
            return ['success' => false, 'message' => 'OpenAI API key is missing.'];
        }

        try {
            $response = Http::withToken($this->apiKey)->timeout(15)->post("https://api.openai.com/v1/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Ping']
                ],
                'max_tokens' => 5
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Successfully connected to OpenAI ({$this->model})!"];
            }

            return ['success' => false, 'message' => "OpenAI error: HTTP " . $response->status() . " - " . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => "OpenAI connection error: " . $e->getMessage()];
        }
    }
}
