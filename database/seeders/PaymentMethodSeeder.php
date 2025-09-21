<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
        PaymentMethod::firstOrCreate(
            ['key' => 'cash'],
            [
                'label' => 'Cash',
                'account_name' => 'N/A',
                'account_number' => 'N/A',
                'bank_name' => 'N/A',
            ]
        );
    }
}
