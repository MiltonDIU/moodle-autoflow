<?php

namespace App\Services\AiProviders;

use App\Models\Setting;
use App\Services\AiProviders\Contracts\AiProviderInterface;
use App\Services\AiProviders\Drivers\GeminiProvider;
use App\Services\AiProviders\Drivers\OpenAiProvider;
use App\Services\AiProviders\Drivers\ClaudeProvider;
use App\Services\AiProviders\Drivers\LocalProvider;

class AiProviderManager
{
    public static function getDriver(?string $providerName = null, ?string $apiKey = null, ?string $model = null): AiProviderInterface
    {
        $provider = $providerName ?: Setting::get('ai_provider', 'gemini');

        return match ($provider) {
            'openai' => new OpenAiProvider($apiKey, $model),
            'claude' => new ClaudeProvider($apiKey, $model),
            'local' => new LocalProvider(),
            default => new GeminiProvider($apiKey, $model),
        };
    }
}
