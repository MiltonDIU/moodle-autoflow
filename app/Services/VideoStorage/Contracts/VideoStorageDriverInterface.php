<?php

namespace App\Services\VideoStorage\Contracts;

interface VideoStorageDriverInterface
{
    /**
     * Process a video file and return playback/embed metadata
     *
     * @param array $fileInfo ['file_id' => string, 'filename' => string, 'download_url' => string, 'course_code' => string, 'week_num' => int]
     * @return array ['driver' => string, 'embed_code' => string, 'stream_url' => string, 'filename' => string]
     */
    public function processVideo(array $fileInfo): array;

    /**
     * Test connection credentials
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;
}
