<?php

namespace App\Services\VideoStorage\Drivers;

use App\Services\VideoStorage\Contracts\VideoStorageDriverInterface;
use App\Models\Setting;

class CloudflareR2Driver implements VideoStorageDriverInterface
{
    protected ?string $accountId;
    protected ?string $accessKey;
    protected ?string $secretKey;
    protected ?string $bucket;
    protected ?string $domain;

    public function __construct()
    {
        $this->accountId = Setting::get('r2_account_id');
        $this->accessKey = Setting::get('r2_access_key_id');
        $this->secretKey = Setting::get('r2_secret_access_key');
        $this->bucket = Setting::get('r2_bucket');
        $this->domain = Setting::get('r2_custom_domain');
    }

    public function processVideo(array $fileInfo): array
    {
        $filename = $fileInfo['filename'];
        $courseCode = $fileInfo['course_code'] ?? 'courses';
        $weekNum = $fileInfo['week_num'] ?? 1;

        if (!$this->accessKey || !$this->bucket) {
            $fallback = new GoogleDriveDriver();
            $data = $fallback->processVideo($fileInfo);
            $data['driver'] = 'r2_fallback_gdrive';
            return $data;
        }

        // R2 Public URL
        $baseUrl = $this->domain ? rtrim($this->domain, '/') : "https://{$this->bucket}.r2.cloudflarestorage.com";
        $objectPath = "courses/{$courseCode}/week_{$weekNum}/{$filename}";
        $videoUrl = "{$baseUrl}/{$objectPath}";

        $embedCode = sprintf(
            '<div style="position: relative; padding-top: 56.25%%; border-radius: 10px; overflow: hidden; margin-bottom: 16px; border: 1px solid #cbd5e1; background: #000;">' .
            '<video controls style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%;"><source src="%s" type="video/mp4">Your browser does not support HTML5 video.</video>' .
            '</div>',
            $videoUrl
        );

        return [
            'driver' => 'cloudflare_r2',
            'filename' => $filename,
            'embed_code' => $embedCode,
            'stream_url' => $videoUrl,
            'direct_url' => $videoUrl
        ];
    }

    public function testConnection(): array
    {
        if (!$this->accessKey || !$this->secretKey || !$this->bucket) {
            return [
                'success' => false,
                'message' => 'Cloudflare R2 Access Key, Secret, or Bucket is missing.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Cloudflare R2 configuration is valid.'
        ];
    }
}
