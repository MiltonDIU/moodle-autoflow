<?php

namespace App\Services\VideoStorage;

use App\Models\Setting;
use App\Services\VideoStorage\Contracts\VideoStorageDriverInterface;
use App\Services\VideoStorage\Drivers\GoogleDriveDriver;
use App\Services\VideoStorage\Drivers\BunnyStreamDriver;
use App\Services\VideoStorage\Drivers\CloudflareR2Driver;
use App\Services\VideoStorage\Drivers\AwsS3Driver;

class VideoStorageManager
{
    public static function getDriver(?string $driverName = null): VideoStorageDriverInterface
    {
        $driver = $driverName ?: Setting::get('video_storage_driver', 'gdrive');

        return match ($driver) {
            'bunny' => new BunnyStreamDriver(),
            'cloudflare_r2' => new CloudflareR2Driver(),
            'aws_s3' => new AwsS3Driver(),
            default => new GoogleDriveDriver(),
        };
    }
}
