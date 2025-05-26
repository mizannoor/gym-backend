<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Payment;

class User extends Authenticatable implements JWTSubject {
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'status_id',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // JWT
    public function getJWTIdentifier() {
        return $this->getKey();
    }
    public function getJWTCustomClaims() {
        return [];
    }

    // Relations
    public function status() {
        return $this->belongsTo(Status::class);
    }
    public function roles() {
        return $this->belongsToMany(Role::class);
    }
    public function memberships() {
        return $this->hasMany(Membership::class);
    }
    /**
     * A user can have many payments.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function createdBy() {
        return $this->belongsTo(self::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(self::class, 'updated_by');
    }
    
}
