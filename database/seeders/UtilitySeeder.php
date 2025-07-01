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
            'creator_percentage' => 10.00, // Set your default percentage here
            'flat_fee' => 500.00, // Set your default flat fee here
        ]);
    }
} 