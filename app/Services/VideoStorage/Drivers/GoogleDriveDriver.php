<?php

namespace App\Services\VideoStorage\Drivers;

use App\Services\VideoStorage\Contracts\VideoStorageDriverInterface;

class GoogleDriveDriver implements VideoStorageDriverInterface
{
    public function processVideo(array $fileInfo): array
    {
        $fileId = $fileInfo['file_id'];
        $filename = $fileInfo['filename'];
        $embedUrl = "https://drive.google.com/file/d/{$fileId}/preview";
        $viewUrl = "https://drive.google.com/file/d/{$fileId}/view?usp=sharing";

        $embedCode = sprintf(
            '<div class="embed-responsive" style="position: relative; padding-bottom: 56.25%%; height: 0; overflow: hidden; border-radius: 10px; border: 1px solid #cbd5e1; margin-bottom: 16px; background: #000;">' .
            '<iframe src="%s" style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%; border: 0;" allow="autoplay" allowfullscreen></iframe>' .
            '</div>',
            $embedUrl
        );

        return [
            'driver' => 'gdrive',
            'file_id' => $fileId,
            'filename' => $filename,
            'embed_code' => $embedCode,
            'stream_url' => $embedUrl,
            'direct_url' => $viewUrl
        ];
    }

    public function testConnection(): array
    {
        return [
            'success' => true,
            'message' => 'Google Drive direct links are active and ready.'
        ];
    }
}
