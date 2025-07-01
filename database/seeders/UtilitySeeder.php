<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utility;

class UtilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Utility::truncate(); // Ensure only one row exists
        Utility::create([
            'creator_percentage' => 10.00, // 10%
            'flat_fee' => 500.00, // flat fee
        ]);
    }
} 