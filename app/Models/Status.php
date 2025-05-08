<?php
// app/Models/Status.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model {
    protected $fillable = ['name', 'description', 'created_by', 'updated_by'];
    public function users() {
        return $this->hasMany(User::class);
    }
    public function memberships() {
        return $this->hasMany(Membership::class);
    }
    public function payments() {
        return $this->hasMany(Payment::class);
    }
}
