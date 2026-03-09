<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Model
{
    use HasFactory;

    /* ================= GENDER ================= */
    const GENDER_MALE = 'male';
    const GENDER_FEMALE = 'female';
    const GENDER_OTHER = 'other';

    public static function genderOptions(): array
    {
        return [
            self::GENDER_MALE => 'Male',
            self::GENDER_FEMALE => 'Female',
            self::GENDER_OTHER => 'Other',
        ];
    }

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'contact_num',
        'gender',

        'is_international',
        'country',
        'region',
        'province',
        'municipality',
        'barangay',

    ];

    // Cast fields
    protected $casts = [
        'is_international' => 'boolean',
    ];

     /**
     * Get the full name of the guest
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? " {$this->middle_name} " : " ";
        return "{$this->first_name}{$middle}{$this->last_name}";
    }

    /**
     * Relationships
     */


    /**
     * Scope for international guests
     */
    public function scopeInternational($query)
    {
        return $query->where('is_international', true);
    }

    /**
     * Scope for local guests
     */
    public function scopeLocal($query)
    {
        return $query->where('is_international', false);
    }


    // Link to the ID photo in the images table
    public function identification()
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'identification');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public static function store($request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:100',
            'middle_name'      => 'nullable|string|max:100',
            'last_name'        => 'required|string|max:100',
            'email'            => 'required|email',
            'contact_num'      => 'required|string|max:20',
            'gender'           => 'nullable|in:Male,Female,Other',
            'is_international' => 'required|boolean',
            'country'          => 'nullable|string|max:100',
            'region'           => 'nullable|string|max:100',
            'province'         => 'nullable|string|max:100',
            'municipality'     => 'nullable|string|max:100',
            'barangay'         => 'nullable|string|max:100',
        ]);

        return self::create($validated);
    }
}