<?php
// app/Models/Status.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Payment extends Model {
    protected $fillable = [
        'user_id',
        'membership_id',
        'provider_payment_id',
        'amount',
        'status_id',
        'paid_at',
        'created_by',
        'updated_by'
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function membership() {
        return $this->belongsTo(Membership::class);
    }
    public function status() {
        return $this->belongsTo(Status::class);
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
