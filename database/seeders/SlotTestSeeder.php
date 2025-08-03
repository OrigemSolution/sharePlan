<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Slot;
use App\Models\Service;
use App\Models\User;
use App\Models\SlotMember;
use Carbon\Carbon;

class SlotTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some services first
        $services = Service::factory(5)->active()->create();

        // Create some users
        $users = User::factory(10)->create();

        // Create trending slots (recent, active, with members, not full)
        foreach ($services as $service) {
            // Create 2-3 trending slots per service
            for ($i = 0; $i < rand(2, 3); $i++) {
                $slot = Slot::factory()
                    ->trending()
                    ->for($service)
                    ->for($users->random())
                    ->create([
                        'current_members' => rand(2, $service->max_members - 1), // Not full
                        'created_at' => Carbon::now()->subDays(rand(1, 7)), // Recent
                    ]);

                // Create paid members for this slot
                $memberCount = $slot->current_members;
                for ($j = 0; $j < $memberCount; $j++) {
                    SlotMember::factory()
                        ->paid()
                        ->forSlot($slot)
                        ->create([
                            'created_at' => Carbon::now()->subDays(rand(1, 7)), // Recent members
                        ]);
                }
            }
        }

        // Create some full slots (should be excluded from trending)
        foreach ($services as $service) {
            $slot = Slot::factory()
                ->open()
                ->paid()
                ->for($service)
                ->for($users->random())
                ->create([
                    'current_members' => $service->max_members, // Full
                    'created_at' => Carbon::now()->subDays(rand(1, 7)),
                ]);

            // Create paid members up to max capacity
            for ($j = 0; $j < $service->max_members; $j++) {
                SlotMember::factory()
                    ->paid()
                    ->forSlot($slot)
                    ->create([
                        'created_at' => Carbon::now()->subDays(rand(1, 7)),
                    ]);
            }
        }

        // Create some completed slots (should be excluded)
        foreach ($services as $service) {
            Slot::factory()
                ->completed()
                ->for($service)
                ->for($users->random())
                ->create([
                    'current_members' => rand(1, $service->max_members),
                    'created_at' => Carbon::now()->subDays(rand(8, 30)), // Older
                ]);
        }

        // Create some inactive slots (should be excluded)
        foreach ($services as $service) {
            Slot::factory()
                ->for($service)
                ->for($users->random())
                ->create([
                    'is_active' => false,
                    'status' => 'open',
                    'payment_status' => 'paid',
                    'current_members' => rand(1, $service->max_members),
                    'created_at' => Carbon::now()->subDays(rand(1, 7)),
                ]);
        }

        // Create some slots with pending payments (should be excluded)
        foreach ($services as $service) {
            Slot::factory()
                ->pendingPayment()
                ->for($service)
                ->for($users->random())
                ->create([
                    'current_members' => rand(1, $service->max_members),
                    'created_at' => Carbon::now()->subDays(rand(1, 7)),
                ]);
        }

        $this->command->info('Slot test data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . Slot::where('is_active', true)->where('status', 'open')->where('payment_status', 'paid')->count() . ' active open slots');
        $this->command->info('- ' . Slot::where('current_members', '>=', \DB::raw('(SELECT max_members FROM services WHERE services.id = slots.service_id)'))->count() . ' full slots');
        $this->command->info('- ' . Slot::where('status', 'completed')->count() . ' completed slots');
        $this->command->info('- ' . Slot::where('is_active', false)->count() . ' inactive slots');
        $this->command->info('- ' . Slot::where('payment_status', 'pending')->count() . ' pending payment slots');
    }
} 