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
        if ($setting && $setting->value !== null && $setting->value !== '') {
            return $setting->value;
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
            'moodle_url' => '',
            'moodle_token' => '',
            'moodle_template_course_id' => '',
            'moodle_category_id' => '1',
            'semester_start_date' => date('Y-m-d'),
            'gdrive_root_folder_id' => '',
            'gdrive_auth_type' => 'public',
            'gdrive_service_account_json' => '',
            'gdrive_api_key' => '',
            'doc_pattern_student_handbook' => '',
            'doc_pattern_instructor_handbook' => '',
            'doc_pattern_test_bank' => '',
            'doc_pattern_slides' => '',
            'doc_custom_mappings' => '[]',
            'ai_provider' => 'gemini',
            'ai_system_prompt' => static::getDefaultAiPrompt(),
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

        // Strict Multi-Tenant Isolation: Do NOT leak University 1 (NextGen) settings to other institutions
        return array_merge($defaults, $saved);
    }

    public static function getDefaultAiPrompt(): string
    {
        return <<<EOT
You are an expert university curriculum instructional designer and LMS content architect.
Create a comprehensive, realistic 16-week course curriculum for: {course_name}.

University Guidelines & Pedagogical Standards:
1. Extract authentic, verbatim information from course handbooks and syllabus materials where available.
2. Adhere to Bloom's Taxonomy standards for student learning objectives and weekly assignments.
3. Every week must include:
   - Module Title and Comprehensive Overview
   - 3-5 Specific Student Learning Objectives
   - Key Academic Terms & Definitions
   - Engaging Discussion Prompt for the Weekly Forum
   - Practical Assignment Task with Evaluation Criteria & Rubric
   - 5-10 Knowledge Check Multiple-Choice Questions (MCQs) with options A-D, correct answer, and explanation.
4. Week 08 is designated as the Midterm Examination.
5. Week 16 is designated as the Final Comprehensive Examination & Capstone Assessment.

Return ONLY strict valid JSON matching this schema:
{
  "weeks": {
    "1": {
      "week_num": 1,
      "title": "Module Title",
      "overview": "Module Overview text...",
      "objectives": ["Objective 1", "Objective 2", "Objective 3"],
      "key_terms": ["Term 1: definition", "Term 2: definition"],
      "discussion": "Weekly discussion topic and prompt for students...",
      "assignment": {
        "task": "Assignment task instructions...",
        "blooms_level": "Analyze, Apply, Evaluate",
        "rubric_raw": "Evaluation Criteria & Grading Rubric..."
      },
      "mcqs": [
        {
          "question": "Question text?",
          "options": ["A) Option 1", "B) Option 2", "C) Option 3", "D) Option 4"],
          "answer": "Correct answer text",
          "explanation": "Detailed explanation..."
        }
      ]
    }
  }
}
EOT;
    }
}
