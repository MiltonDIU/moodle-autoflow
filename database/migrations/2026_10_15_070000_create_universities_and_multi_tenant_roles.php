<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Universities Table
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });

        // Insert Default University: NextGen University
        $now = now();
        $universityId = DB::table('universities')->insertGetId([
            'name' => 'NextGen University',
            'slug' => 'nextgen',
            'domain' => 'nextgen.edu',
            'contact_email' => 'admin@nextgen.edu',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // 2. Add university_id and role to users table
        Schema::table('users', function (Blueprint $table) use ($universityId) {
            $table->foreignId('university_id')->nullable()->after('id')->constrained('universities')->nullOnDelete();
            $table->string('role')->default('university_admin')->after('password'); // super_admin, university_admin, instructor
            $table->string('status')->default('active')->after('role');
            $table->string('avatar')->nullable()->after('status');
        });

        // 3. Add university_id to courses table
        Schema::table('courses', function (Blueprint $table) use ($universityId) {
            $table->foreignId('university_id')->default($universityId)->after('id')->constrained('universities')->cascadeOnDelete();
        });

        // Assign any existing courses to NextGen University
        DB::table('courses')->whereNull('university_id')->orWhere('university_id', 0)->update([
            'university_id' => $universityId
        ]);

        // 4. Update settings table for multi-tenancy
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('university_id')->nullable()->after('id')->constrained('universities')->cascadeOnDelete();
            $table->dropUnique(['key']);
            $table->unique(['university_id', 'key']);
        });

        // Assign any existing settings to NextGen University
        DB::table('settings')->whereNull('university_id')->update([
            'university_id' => $universityId
        ]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['university_id', 'key']);
            $table->dropForeign(['university_id']);
            $table->dropColumn('university_id');
            $table->unique('key');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropColumn('university_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropColumn(['university_id', 'role', 'status', 'avatar']);
        });

        Schema::dropIfExists('universities');
    }
};
