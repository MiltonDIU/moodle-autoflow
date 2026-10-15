<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['university_id', 'key', 'value'];

    public static function resolveUniversityId(?int $explicitId = null): int
    {
        if ($explicitId !== null) {
            return $explicitId;
        }

        if (session()->has('active_university_id')) {
            return (int) session('active_university_id');
        }

        if (auth()->check() && auth()->user()->university_id) {
            return (int) auth()->user()->university_id;
        }

        $first = University::first();
        return $first ? $first->id : 1;
    }

    public static function get($key, $default = null, ?int $universityId = null)
    {
        $uniId = static::resolveUniversityId($universityId);
        $setting = static::where('university_id', $uniId)->where('key', $key)->first();
        if ($setting) {
            return $setting->value;
        }

        // Fallback to global / university 1 if not set for new university
        if ($uniId !== 1) {
            $defaultSetting = static::where('university_id', 1)->where('key', $key)->first();
            if ($defaultSetting) {
                return $defaultSetting->value;
            }
        }

        return $default;
    }

    public static function set($key, $value, ?int $universityId = null)
    {
        $uniId = static::resolveUniversityId($universityId);
        return static::updateOrCreate(
            ['university_id' => $uniId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getAllForUniversity(?int $universityId = null): array
    {
        $uniId = static::resolveUniversityId($universityId);
        $defaults = [
            'moodle_url' => 'https://devlms.daffodilvarsity.edu.bd',
            'moodle_token' => '1e10dad970723823f70753bf73cfef41',
            'moodle_template_course_id' => '4',
            'moodle_category_id' => '1',
            'semester_start_date' => date('Y-m-d'),
            'gdrive_root_folder_id' => '1BcFVmPVoN9HMPSUNw1vqnOuPYKkDPcnR',
            'ai_provider' => 'gemini',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'claude_api_key' => '',
            'claude_model' => 'claude-3-5-haiku-20241022',
            'video_storage_driver' => 'gdrive',
            'bunny_api_key' => '',
            'bunny_library_id' => '',
            'bunny_hostname' => 'iframe.mediadelivery.net',
            'r2_account_id' => '',
            'r2_access_key_id' => '',
            'r2_secret_access_key' => '',
            'r2_bucket' => '',
            'r2_custom_domain' => '',
            's3_key' => '',
            's3_secret' => '',
            's3_region' => 'us-east-1',
            's3_bucket' => ''
        ];

        $saved = static::where('university_id', $uniId)->pluck('value', 'key')->toArray();

        if ($uniId !== 1) {
            $uni1Saved = static::where('university_id', 1)->pluck('value', 'key')->toArray();
            $defaults = array_merge($defaults, $uni1Saved);
        }

        return array_merge($defaults, $saved);
    }
}
