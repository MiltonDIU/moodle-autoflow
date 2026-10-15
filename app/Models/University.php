<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'contact_email',
        'logo_url',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function settings()
    {
        return $this->hasMany(Setting::class);
    }

    public function getSetting(string $key, $default = null)
    {
        return Setting::get($key, $default, $this->id);
    }

    public function setSetting(string $key, $value)
    {
        return Setting::set($key, $value, $this->id);
    }
}
