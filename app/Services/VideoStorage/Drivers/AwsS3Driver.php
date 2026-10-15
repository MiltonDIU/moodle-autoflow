<?php

namespace App\Services\VideoStorage\Drivers;

use App\Services\VideoStorage\Contracts\VideoStorageDriverInterface;
use App\Models\Setting;

class AwsS3Driver implements VideoStorageDriverInterface
{
    protected ?string $key;
    protected ?string $secret;
    protected ?string $region;
    protected ?string $bucket;
    protected ?string $cloudFrontUrl;

    public function __construct()
    {
        $this->key = Setting::get('s3_key');
        $this->secret = Setting::get('s3_secret');
        $this->region = Setting::get('s3_region', 'us-east-1');
        $this->bucket = Setting::get('s3_bucket');
        $this->cloudFrontUrl = Setting::get('s3_cloudfront_url');
    }

    public function processVideo(array $fileInfo): array
    {
        $filename = $fileInfo['filename'];
        $courseCode = $fileInfo['course_code'] ?? 'courses';
        $weekNum = $fileInfo['week_num'] ?? 1;

        if (!$this->key || !$this->bucket) {
            $fallback = new GoogleDriveDriver();
            $data = $fallback->processVideo($fileInfo);
            $data['driver'] = 's3_fallback_gdrive';
            return $data;
        }

        $baseUrl = $this->cloudFrontUrl ? rtrim($this->cloudFrontUrl, '/') : "https://{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $videoUrl = "{$baseUrl}/courses/{$courseCode}/week_{$weekNum}/{$filename}";

        $embedCode = sprintf(
            '<div style="position: relative; padding-top: 56.25%%; border-radius: 10px; overflow: hidden; margin-bottom: 16px; border: 1px solid #cbd5e1; background: #000;">' .
            '<video controls style="position: absolute; top: 0; left: 0; width: 100%%; height: 100%%;"><source src="%s" type="video/mp4">Your browser does not support HTML5 video.</video>' .
            '</div>',
            $videoUrl
        );

        return [
            'driver' => 'aws_s3',
            'filename' => $filename,
            'embed_code' => $embedCode,
            'stream_url' => $videoUrl,
            'direct_url' => $videoUrl
        ];
    }

    public function testConnection(): array
    {
        if (!$this->key || !$this->secret || !$this->bucket) {
            return [
                'success' => false,
                'message' => 'AWS S3 Access Key, Secret, or Bucket is missing.'
            ];
        }

        return [
            'success' => true,
            'message' => 'AWS S3 settings validated.'
        ];
    }
}
