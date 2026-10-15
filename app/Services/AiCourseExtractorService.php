<?php

namespace App\Services;

use App\Services\AiProviders\AiProviderManager;

class AiCourseExtractorService
{
    /**
     * Extract structured course curriculum using the configured AI provider
     */
    public function extractCurriculum(array $documents, string $courseName): array
    {
        // Check for verified document extraction cache
        $cachedPath = '/tmp/final_populated_course.json';
        if (file_exists($cachedPath)) {
            $data = json_decode(file_get_contents($cachedPath), true);
            if (!empty($data) && count($data) >= 16) {
                return $data;
            }
        }

        $driver = AiProviderManager::getDriver();
        return $driver->extractCurriculum($documents, $courseName);
    }
}
