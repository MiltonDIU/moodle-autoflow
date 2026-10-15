<?php

namespace App\Http\Controllers;

use App\Models\University;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UniversityController extends Controller
{
    protected function checkSuperAdmin()
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Access Denied: Only System Super Admins have permission to manage institutions.');
        }
    }

    public function index()
    {
        $this->checkSuperAdmin();
        $universities = University::withCount(['courses', 'users'])->latest()->get();
        $activeUniId = session('active_university_id', auth()->user()->university_id ?: 1);

        return view('universities.index', compact('universities', 'activeUniId'));
    }

    public function store(Request $request)
    {
        $this->checkSuperAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (University::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $uni = University::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'domain' => $validated['domain'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'status' => 'active',
        ]);

        // Copy default template settings from NextGen University (ID 1)
        $defaultSettings = Setting::where('university_id', 1)->get();
        foreach ($defaultSettings as $s) {
            Setting::create([
                'university_id' => $uni->id,
                'key' => $s->key,
                'value' => $s->value,
            ]);
        }

        return redirect()->route('universities.index')
            ->with('success', "University '{$uni->name}' has been created with default course provisioning templates!");
    }

    public function switchUniversity(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $uni = University::findOrFail($id);
        session(['active_university_id' => $uni->id]);

        return redirect()->back()->with('success', "Active workspace switched to '{$uni->name}'.");
    }
}
