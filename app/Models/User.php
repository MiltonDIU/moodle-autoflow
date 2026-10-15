<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'university_id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'avatar'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isUniversityAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'university_admin']);
    }

    public function isInstructor(): bool
    {
        return in_array($this->role, ['super_admin', 'university_admin', 'instructor']);
    }

    public function getRoleBadgeClass(): string
    {
        return match ($this->role) {
            'super_admin' => 'bg-danger text-white',
            'university_admin' => 'bg-primary text-white',
            'instructor' => 'bg-success text-white',
            default => 'bg-secondary text-white'
        };
    }

    public function getRoleDisplayName(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'university_admin' => 'University Admin',
            'instructor' => 'Instructor / Course Creator',
            default => ucfirst(str_replace('_', ' ', $this->role))
        };
    }
}
