<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'vehicle_id',
        'returned_at',
        'odometer',
        'fuel_level',
        'condition_notes',
        'images',
        'late_fee',
        'damage_fee',
        'cleaning_fee',
    ];

    protected $casts = [
        'images' => 'array',
        'returned_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
