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
        return <<<EOT
You are an expert university curriculum instructional designer. Create a comprehensive, realistic 16-week course curriculum for: {$courseName}.
Return strict JSON:
{
  "weeks": {
    "1": {
      "week_num": 1,
      "title": "Module Title",
      "overview": "Overview text",
      "objectives": ["Objective 1", "Objective 2", "Objective 3"],
      "key_terms": ["Term 1: definition", "Term 2: definition"],
      "discussion": "Weekly discussion prompt",
      "assignment": "Written assignment task description",
      "mcqs": [
        {
          "question": "Question text?",
          "options": ["A) opt 1", "B) opt 2", "C) opt 3", "D) opt 4"],
          "answer": "Correct answer",
          "explanation": "Explanation"
        }
      ]
    }
  }
}
Generate for all 16 weeks (Week 8 is Midterm Exam, Week 16 is Final Comprehensive Exam & Project).
EOT;
    }
}
