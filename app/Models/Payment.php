<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'method',
        'reference_number',
        'proof_image',
        'status',
        'type', // <-- allow mass assignment of type
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
