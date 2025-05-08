<?php
// app/Models/Status.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Membership extends Model {
    protected $fillable = [
        'user_id',
        'plan_id',
        'status_id',
        'starts_at',
        'expires_at',
        'created_by',
        'updated_by'
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function plan() {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }
    public function status() {
        return $this->belongsTo(Status::class);
    }
    public function payments() {
        return $this->hasMany(Payment::class);
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
