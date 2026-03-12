<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BedModifier extends Model
{
    protected $fillable = ['name'];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'bed_modifier_room');
    }
}