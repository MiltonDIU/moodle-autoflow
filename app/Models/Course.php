<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'university_id',
        'name',
        'course_code',
        'gdrive_folder_id',
        'status',
        'progress',
        'storage_driver',
        'moodle_course_id',
        'extracted_data',
        'video_map',
        'error_message'
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'video_map' => 'array',
        'progress' => 'integer'
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}
