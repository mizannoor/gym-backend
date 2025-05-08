<?php
// app/Models/Status.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Role extends Model {
    protected $fillable = ['name', 'created_by', 'updated_by'];
    public function users() {
        return $this->belongsToMany(User::class);
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedBy() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
