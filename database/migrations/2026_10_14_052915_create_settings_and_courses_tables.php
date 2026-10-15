<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('course_code')->nullable();
            $table->string('gdrive_folder_id');
            $table->string('status')->default('discovered'); // discovered, extracting_ai, uploading_videos, syncing_moodle, completed, failed
            $table->integer('progress')->default(0);
            $table->string('storage_driver')->default('gdrive');
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->json('extracted_data')->nullable(); // full 16-week structured json from AI/Drive
            $table->json('video_map')->nullable(); // list of videos and their storage URLs
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('settings');
    }
};
