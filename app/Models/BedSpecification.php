<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BedSpecification extends Model
{
    protected $fillable = ['specification'];

    public function rooms()
    {
        return $this->belongsToMany(\App\Models\Room::class, 'bed_specification_room');
    }
}