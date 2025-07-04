<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'date',
        'amount',
        'type',
        'note',
        'details',
        'created_by',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
