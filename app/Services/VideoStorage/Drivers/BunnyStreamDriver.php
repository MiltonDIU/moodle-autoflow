<?php

namespace App\Services\VideoStorage\Drivers;

use App\Services\VideoStorage\Contracts\VideoStorageDriverInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class BunnyStreamDriver implements VideoStorageDriverInterface
{
    protected ?string $apiKey;
    protected ?string $libraryId;
    protected ?string $pullZone;
    protected array $collectionCache = [];

    public function __construct()
    {
        $this->apiKey = Setting::get('bunny_api_key');
        $this->libraryId = Setting::get('bunny_library_id');
        $this->pullZone = Setting::get('bunny_hostname', 'iframe.mediadelivery.net');
    }

    /**
     * Find or create a Collection (Folder) by Course Name in Bunny Stream.
     */
    public function getOrCreateCollection(string $courseName): ?string
    {
        $cleanName = trim($courseName);
        if (empty($cleanName)) {
            return null;
        }

        if (isset($this->collectionCache[$cleanName])) {
            return $this->collectionCache[$cleanName];
        }

        try {
            // 1. Search existing collections in Bunny
            $res = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'accept' => 'application/json'
            ])->get("https://video.bunnycdn.com/library/{$this->libraryId}/collections", [
                'search' => $cleanName,
                'itemsPerPage' => 50
            ]);

            if ($res->successful()) {
                $items = $res->json('items') ?? [];
                foreach ($items as $item) {
                    if (trim($item['name']) === $cleanName) {
                        $this->collectionCache[$cleanName] = $item['guid'];
                        return $item['guid'];
                    }
                }
            }

            // 2. Create new collection if not found
            $createRes = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Content-Type' => 'application/json',
                'accept' => 'application/json'
            ])->post("https://video.bunnycdn.com/library/{$this->libraryId}/collections", [
                'name' => $cleanName
            ]);

            if ($createRes->successful()) {
                $guid = $createRes->json('guid');
                $this->collectionCache[$cleanName] = $guid;
                return $guid;
            }
        } catch (\Exception $e) {
            // Fail silently, proceed with root upload
        }

        return null;
    }

    public function processVideo(array $fileInfo): array
    {
        $fileId = $fileInfo['file_id'] ?? '';
        $filename = $fileInfo['filename'];
        $courseCode = $fileInfo['course_code'] ?? 'Course';
        $courseName = $fileInfo['course_name'] ?? $courseCode;
        $weekNum = $fileInfo['week_num'] ?? 1;

        if (!$this->apiKey || !$this->libraryId) {
            // Fallback to Drive if not configured
            $fallback = new GoogleDriveDriver();
            $data = $fallback->processVideo($fileInfo);
            $data['driver'] = 'bunny_fallback_gdrive';
            return $data;
        }

        try {
            // Ensure Collection (Course Folder) exists
            $collectionId = $this->getOrCreateCollection($courseName);
            $videoTitle = "{$courseCode} - Week {$weekNum} - {$filename}";

            // 1. Check if video already exists in Bunny Library to prevent duplicate re-encoding
            $searchQuery = [
                'search' => $videoTitle,
                'itemsPerPage' => 10
            ];
            if ($collectionId) {
                $searchQuery['collection'] = $collectionId;
            }

            $searchRes = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'accept' => 'application/json'
            ])->get("https://video.bunnycdn.com/library/{$this->libraryId}/videos", $searchQuery);

            $existingVideo = null;
            if ($searchRes->successful()) {
                $items = $searchRes->json('items') ?? [];
                foreach ($items as $item) {
                    if (strcasecmp(trim($item['title'] ?? ''), trim($videoTitle)) === 0) {
                        // Prefer finished video (status 2 = Finished) or existing video
                        if (($item['status'] ?? 0) === 2 || empty($existingVideo)) {
                            $existingVideo = $item;
                        }
                    }
                }
            }

            $videoId = $existingVideo['guid'] ?? null;

            // 2. Only if video does NOT exist in Bunny, trigger video fetch / encoding
            if (!$videoId) {
                $downloadUrl = "https://drive.usercontent.google.com/download?id={$fileId}&export=download&confirm=t";
                $fetchPayload = [
                    'title' => $videoTitle,
                    'url' => $downloadUrl
                ];
                if ($collectionId) {
                    $fetchPayload['collectionId'] = $collectionId;
                }

                $res = Http::withHeaders([
                    'AccessKey' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'accept' => 'application/json'
                ])->post("https://video.bunnycdn.com/library/{$this->libraryId}/videos/fetch", $fetchPayload);

                if ($res->successful()) {
                    $data = $res->json();
                    $videoId = $data['id'] ?? ($data['guid'] ?? null);
                }
            }

            if ($videoId) {
                $embedUrl = "https://{$this->pullZone}/embed/{$this->libraryId}/{$videoId}";
                $streamUrl = "https://{$this->pullZone}/{$videoId}/playlist.m3u8";

                $embedCode = sprintf(
                    '<div style="position: relative; padding-top: 56.25%%; border-radius: 10px; overflow: hidden; margin-bottom: 16px; border: 1px solid #cbd5e1; background: #000;">' .
                    '<iframe src="%s" loading="lazy" style="border: none; position: absolute; top: 0; height: 100%%; width: 100%%;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>' .
                    '</div>',
                    $embedUrl
                );

                return [
                    'driver' => 'bunny',
                    'file_id' => $fileId,
                    'video_id' => $videoId,
                    'collection_id' => $collectionId,
                    'filename' => $filename,
                    'embed_code' => $embedCode,
                    'stream_url' => $streamUrl,
                    'direct_url' => $embedUrl
                ];
            }
        } catch (\Exception $e) {
            // If bunny call fails, fallback gracefully
        }

        $fallback = new GoogleDriveDriver();
        $fallbackRes = $fallback->processVideo($fileInfo);
        $fallbackRes['driver'] = 'bunny_fallback_gdrive';
        return $fallbackRes;
    }

    public function testConnection(): array
    {
        if (!$this->apiKey || !$this->libraryId) {
            return [
                'success' => false,
                'message' => 'Bunny API Key or Library ID is missing in settings.'
            ];
        }

        try {
            $res = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'accept' => 'application/json'
            ])->get("https://video.bunnycdn.com/library/{$this->libraryId}");

            if ($res->successful()) {
                $data = $res->json();
                return [
                    'success' => true,
                    'message' => "Connected successfully to Bunny Library: " . ($data['Name'] ?? $this->libraryId)
                ];
            }

            return [
                'success' => false,
                'message' => "Bunny API error: HTTP " . $res->status() . " - " . $res->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
}
