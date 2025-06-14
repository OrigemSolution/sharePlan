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
            'name' => 'Admin User',
            'whatsapp_phone' => '07012343212',
            'phone' => '07012343212',
            'email' => 'adminuser@gmail.com',
            'bank' => 'Admin Bank',
            'account_name' => 'Admin User',
            'account_no' => '43215678901',
            'status' => 'verified',
            'email_verified_at' => now(),
            'role_id' => 2,
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'name' => 'User One',
            'whatsapp_phone' => '0701234333',
            'phone' => '0701234333',
            'email' => 'userone@gmail.com',
            'bank' => 'User Bank',
            'account_name' => 'User One',
            'account_no' => '43232678901',
            'role_id' => 1,
            'status' => 'verified',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
