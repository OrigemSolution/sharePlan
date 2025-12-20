<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Check if test user exists
$testUser = User::where('email', 'test@example.com')->first();

if (!$testUser) {
    // Create test user
    $testUser = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'whatsapp_phone' => '+1234567890',
    ]);
    echo "Test user created successfully!\n";
    echo "Email: test@example.com\n";
    echo "Password: password\n";
} else {
    echo "Test user already exists!\n";
    echo "Email: test@example.com\n";
    echo "Password: password\n";
}

echo "You can now test the password-sharing/add endpoint.\n";