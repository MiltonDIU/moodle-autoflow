<?php

namespace App\Services\AiProviders\Contracts;

interface AiProviderInterface
{
    /**
     * Extract full 16-week structured course curriculum from documents
     *
     * @param array $documents List of document metadata from Google Drive
     * @param string $courseName
     * @return array 16-week structured curriculum
     */
    public function extractCurriculum(array $documents, string $courseName): array;

    /**
     * Test the API credentials
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;
}
