<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'type',
        'brand',
        'model',
        'year',
        'plate_number',
        'capacity',
        'rental_rate',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'capacity' => 'integer',
        'rental_rate' => 'decimal:2',
    ];

    /**
     * Get all bookings for this vehicle
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
