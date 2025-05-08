<?php
// app/Models/Status.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class MembershipPlan extends Model {
    protected $fillable = [
        'name',
        'price',
        'duration_months',
        'created_by',
        'updated_by'
    ];
    public function memberships() {
        return $this->hasMany(Membership::class, 'plan_id');
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
