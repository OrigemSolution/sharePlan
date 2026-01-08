<?php

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $query = Payment::query();
    $search = 'test';

    $query->where(function ($query) use ($search) {
        $query->whereHas('slot.service', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        })
        ->orWhereHas('passwordSharingSlot.passwordService', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    });

    echo "Query SQL: " . $query->toSql() . PHP_EOL;
    echo "Query built successfully." . PHP_EOL;

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
