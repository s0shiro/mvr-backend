<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'start_date',
        'end_date',
        'status',
        'total_price',
        'notes',
        'driver_requested',
        'pickup_type',
        'delivery_location',
        'delivery_details',
        'delivery_fee',
    ];

    const DELIVERY_FEES = [
        'Boac' => 300,
        'Gasan' => 300,
        'Gasan Port' => 300,
        'Balanacan' => 300,
        'Buenavista' => 300, // Added default price
        'Sta. Cruz' => 500,
        'Sta. Cruz Port' => 500,
        'Torrijos' => 700,
        'Maniwaya' => 500,
        'Mogpog' => 150,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    
    public function payments()
    {
        return $this->hasMany(Payment::class)->orderByDesc('created_at');
    }

    public function latestDepositPayment()
    {
        return $this->hasOne(Payment::class)
            ->where('type', 'deposit')
            ->latest();
    }

    public function latestRentalPayment()
    {
        return $this->hasOne(Payment::class)
            ->where('type', 'rental')
            ->latest();
    }

    // Keeping old relationships for backward compatibility
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function depositPayment()
    {
        return $this->latestDepositPayment();
    }

    public function rentalPayment()
    {
        return $this->latestRentalPayment();
    }
}
