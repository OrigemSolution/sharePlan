<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

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
            'email' => 'adminuser@gmail.com',
            'bank' => 'Admin Bank',
            'account_name' => 'Admin User',
            'account_no' => '43215678901',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'name' => 'User One',
            'whatsapp_phone' => '0701234333',
            'email' => 'userone@gmail.com',
            'bank' => 'User Bank',
            'account_name' => 'User One',
            'account_no' => '43232678901',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }
}
