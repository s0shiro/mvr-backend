<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Booking;
use App\Models\VehicleImage;
use App\Models\VehicleMaintenance;

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
        'rental_rate_with_driver',
        'deposit',
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
        'deposit' => 'decimal:2',
    ];

    /**
     * Get all bookings for this vehicle
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all images for this vehicle
     */
    public function images()
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    /**
     * Get the primary image for this vehicle
     */
    public function primaryImage()
    {
        return $this->hasOne(VehicleImage::class)->where('is_primary', true);
    }

    public function maintenances()
    {
        return $this->hasMany(VehicleMaintenance::class)->orderByDesc('maintenance_date')->orderByDesc('id');
    }

    /**
     * Append primary image URL to the vehicle data
     */
    protected $appends = ['primary_image_url'];

    /**
     * Get the primary image URL
     */
    public function getPrimaryImageUrlAttribute()
    {
        $primaryImage = $this->primaryImage;
        return $primaryImage ? $primaryImage->image_url : null;
    }
}
