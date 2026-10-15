<?php

namespace App\Services\AiProviders\Drivers;

use App\Services\AiProviders\Contracts\AiProviderInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class ClaudeProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?: Setting::get('claude_api_key', '');
        $this->model = $model ?: Setting::get('claude_model', 'claude-3-5-haiku-20241022');
    }

    public function extractCurriculum(array $documents, string $courseName): array
    {
        if (!$this->apiKey) {
            $local = new LocalProvider();
            return $local->extractCurriculum($documents, $courseName);
        }

        $template = Setting::get('ai_system_prompt') ?: Setting::getDefaultAiPrompt();
        $prompt = str_replace('{course_name}', $courseName, $template);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json'
        ])->timeout(90)->post("https://api.anthropic.com/v1/messages", [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '{}';
            // clean markdown code fences if any
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
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
            return ['success' => false, 'message' => 'Anthropic Claude API key is missing.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ])->timeout(15)->post("https://api.anthropic.com/v1/messages", [
                'model' => $this->model,
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'Ping']
                ]
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => "Successfully connected to Anthropic Claude ({$this->model})!"];
            }

            return ['success' => false, 'message' => "Claude error: HTTP " . $response->status() . " - " . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => "Claude connection error: " . $e->getMessage()];
        }
    }
}
