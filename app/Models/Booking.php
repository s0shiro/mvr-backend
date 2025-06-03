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
        'valid_ids', // JSON: {"id1": "base64string", "id2": "base64string"}
        'days', // Number of days for the booking
        'refund_rate', // Refund rate applied on cancellation
        'refund_amount', // Refund amount applied on cancellation
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

    public function vehicleRelease()
    {
        return $this->hasOne(VehicleRelease::class);
    }
    
    public function vehicleReturn()
    {
        return $this->hasOne(VehicleReturn::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the valid IDs as an associative array.
     */
    public function getValidIdsAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    /**
     * Set the valid IDs as a JSON string.
     */
    public function setValidIdsAttribute($value)
    {
        $this->attributes['valid_ids'] = is_array($value) ? json_encode($value) : $value;
    }
}
