<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Slot;
use App\Models\Service;
use App\Models\User;
use App\Models\SlotMember;
use Carbon\Carbon;

class TestTrendingSlots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:trending-slots {--days=7 : Number of days to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the trending slots functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        
        $this->info("Testing trending slots for the last {$days} days...");
        
        // Get trending slots using the same logic as the controller
        $trendingSlots = Slot::with([
                'service',
                'user',
                'members' => function ($query) {
                    $query->where('payment_status', 'paid');
                }
            ])
            ->withCount(['members as recent_paid_members_count' => function ($query) use ($days) {
                $query->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->subDays($days));
            }])
            ->where('is_active', true)
            ->orderByDesc('recent_paid_members_count')
            ->get();

        // Filter out full slots
        $availableSlots = $trendingSlots->filter(function ($slot) {
            $paidMembersCount = $slot->members->count();
            $maxMembers = $slot->service->max_members;
            return $paidMembersCount < $maxMembers;
        })->take(6);

        $this->info("\n📊 Trending Slots Results:");
        $this->info("==========================");
        
        if ($availableSlots->count() === 0) {
            $this->warn("No trending slots found!");
            return;
        }

        foreach ($availableSlots as $index => $slot) {
            $paidMembersCount = $slot->members->count();
            $maxMembers = $slot->service->max_members;
            $availableSpots = $maxMembers - $paidMembersCount;
            
            $this->info("\n" . ($index + 1) . ". {$slot->service->name}");
            $this->info("   Creator: {$slot->user->name}");
            $this->info("   Duration: {$slot->duration} month(s)");
            $this->info("   Members: {$paidMembersCount}/{$maxMembers} ({$availableSpots} spots available)");
            $this->info("   Recent members (last {$days} days): {$slot->recent_paid_members_count}");
            $this->info("   Status: {$slot->status}");
            $this->info("   Created: " . $slot->created_at->diffForHumans());
        }

        $this->info("\n📈 Statistics:");
        $this->info("==============");
        $this->info("Total active slots: " . Slot::where('is_active', true)->count());
        $this->info("Total trending slots (before filtering): " . $trendingSlots->count());
        $this->info("Available trending slots (after filtering): " . $availableSlots->count());
        
        // Count full slots
        $fullSlots = $trendingSlots->filter(function ($slot) {
            $paidMembersCount = $slot->members->count();
            $maxMembers = $slot->service->max_members;
            return $paidMembersCount >= $maxMembers;
        });
        $this->info("Full slots excluded: " . $fullSlots->count());
    }
} 