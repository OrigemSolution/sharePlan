<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'SharePlan Admin',
            'whatsapp_phone' => '09055622226',
            'phone' => '09055622226',
            'email' => 'theshareplanos@gmail.com',
            'bank' => 'Guaranty Trust Bank',
            'account_name' => 'Origem Business Solutions',
            'account_no' => '3000662886',
            'status' => 'verified',
            'email_verified_at' => now(),
            'role_id' => 2,
            'password' => Hash::make('sHa12re0Pl@n#'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
