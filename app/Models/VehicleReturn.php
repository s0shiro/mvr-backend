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
        'customer_images',
        'customer_condition_notes',
        'status',
        'customer_submitted_at',
        'admin_processed_at',
        'late_fee',
        'damage_fee',
        'cleaning_fee',
        'deposit_status',
        'deposit_refund_amount',
        'deposit_refund_notes',
        'deposit_refund_proof',
        'deposit_refunded_at',
        'refund_method',
        // Customer refund account information
        'customer_refund_method',
        'customer_account_name',
        'customer_account_number',
        'customer_bank_name',
        'customer_refund_notes',
    ];

    protected $casts = [
        'images' => 'array',
        'customer_images' => 'array',
        'deposit_refund_proof' => 'array',
        'returned_at' => 'datetime',
        'customer_submitted_at' => 'datetime',
        'admin_processed_at' => 'datetime',
        'deposit_refunded_at' => 'datetime',
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
