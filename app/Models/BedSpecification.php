<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BedSpecification extends Model
{
    use SoftDeletes;

    protected $fillable = ['specification'];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'bed_specification_room');
    }
}
