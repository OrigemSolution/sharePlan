<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slot;
use App\Models\Service;
use App\Models\SlotMember;
use App\Models\Payment;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Models\Utility;

class SlotController extends Controller
{
    public function index()
    {
        try {
            $query = Slot::with(['service', 'user', 'members' => function($query) {
                $query->where('payment_status', 'paid');
            }]);

            // If user is authenticated, show their slots (all, including inactive)
            if (auth()->check()) {
                $query->where(function($q) {
                    $q->where('user_id', auth()->id())
                      ->orWhere('is_active', true);
                });
            } else {
                // For guests, only show active slots
                $query->where('is_active', true);
            }

            $slots = $query->latest()->get();

            // Transform the slots to include creator and service name
            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $data = $slots->map(function ($slot) use ($flatFee) {
                $service = $slot->service;
                $servicePrice = $service->price;
                $maxMembers = (int) $service->max_members;
                $duration = (int) $slot->duration;
                $perMemberPrice = $servicePrice / $maxMembers;
                $guestAmount = ($perMemberPrice + $flatFee) * $duration;
                return [
                    'id' => $slot->id,
                    'user_id' => $slot->user_id,
                    'creator_name' => $slot->user ? $slot->user->name : null,
                    'status' => $slot->status,
                    'duration' => $slot->duration,
                    'current_members' => $slot->members->count(), // Count of paid members only
                    'payment_status' => $slot->payment_status,
                    'service' => $slot->service,
                    'members' => $slot->members, 
                    'guest_price' => $guestAmount
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create(Request $request)
    {
        // Check if user is verified
        if ($request->user()->status !== 'verified') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only verified users can create slots'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'duration' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get the service
            $service = Service::findOrFail($request->service_id);
            
            if (!$service->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service is currently not available'
                ], 400);
            }

            // Get creator percentage from Utility
            $utility = Utility::first();
            $creatorPercentage = $utility ? $utility->creator_percentage : 0;
            $servicePrice = $service->price;
            $maxMembers = (int) $service->max_members;
            $duration = (int) $request->duration;
            // New creator formula: (service price / max_members - creator_percentage%) * duration
            $perMemberPrice = $servicePrice / $maxMembers;
            $creatorAmount = ($perMemberPrice - ($perMemberPrice * ($creatorPercentage / 100))) * $duration;

            // Create the slot
            $slot = Slot::create([
                'service_id' => $service->id,
                'user_id' => $request->user()->id,
                'current_members' => 1, // Creator is the first member
                'duration' => $duration,
                'status' => 'open',
                'payment_status' => 'pending',
                'is_active' => false // Slot is not active until payment is confirmed
            ]);

            // Generate Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Create payment record (creator pays discounted amount)
            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'slot_id' => $slot->id,
                'amount' => $creatorAmount * 100, // Paystack amount in kobo
                'reference' => $reference,
                'status' => 'pending',
                'currency' => 'NGN',
            ]);

            // Update slot with payment reference
            $slot->update(['payment_reference' => $reference]);

            // Add creator as first slot member (pending payment)
            $slotMember = SlotMember::create([
                'slot_id' => $slot->id,
                'user_id' => $request->user()->id,
                'member_name' => $request->user()->name,
                'member_email' => $request->user()->email,
                'member_phone' => $request->user()->phone,
                'payment_status' => 'pending',
                'payment_id' => $payment->id
            ]);

            // Initialize Paystack transaction
            $paymentData = [
                "amount" => $payment->amount,
                "reference" => $payment->reference,
                "email" => $request->user()->email,
                "currency" => "NGN",
                "metadata" => [
                    "service_id" => $service->id,
                    "user_id" => $request->user()->id,
                    "payment_id" => $payment->id,
                    "slot_id" => $slot->id,
                    "slot_member_id" => $slotMember->id
                ]
            ];

            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Slot created successfully. Please complete payment.',
                'data' => [
                    'slot' => $slot,
                    'payment' => [
                        'authorization_url' => $authorizationUrl,
                        'reference' => $reference,
                        'amount' => $creatorAmount
                    ],
                    'service' => $service
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'slot_id' => 'required|exists:slots,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // First check if payment exists in our database
            $payment = Payment::where('reference', $request->reference)
                            ->where('status', 'pending')
                            ->first();

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found or already processed'
                ], 404);
            }

            try {
                // Manual Paystack verification using Guzzle
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($request->reference), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . config('paystack.secretKey'),
                        'Cache-Control' => 'no-cache',
                    ]
                ]);
                
                $paymentDetails = json_decode($response->getBody(), true);

                if (!$paymentDetails['status'] || $paymentDetails['data']['status'] !== 'success') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment verification failed. Status: ' . ($paymentDetails['data']['status'] ?? 'unknown')
                    ], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification error',
                    'error' => $e->getMessage()
                ], 400);
            }

            // Update payment status
            $payment->update([
                'status' => 'success',
                'payment_channel' => $paymentDetails['data']['channel'] ?? 'unknown',
                'metadata' => $paymentDetails['data']['metadata'] ?? null
            ]);

            // Update slot payment status
            $slot = Slot::findOrFail($request->slot_id);
            $slot->update([
                'payment_status' => 'paid',
                // Activate slot if the payer is the creator
                'is_active' => ($slot->user_id === $request->user()->id) ? true : $slot->is_active
            ]);

            // Update slot member payment status
            $slotMember = SlotMember::where([
                'slot_id' => $request->slot_id,
                'user_id' => $request->user()->id,
                'payment_id' => $payment->id
            ])->first();

            if (!$slotMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Slot member not found'
                ], 404);
            }

            $slotMember->update([
                'payment_status' => 'paid'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'slot' => $slot,
                    'slot_member' => $slotMember,
                    'payment' => $payment
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $slot = Slot::with(['service', 'user', 'members' => function($query) {
                $query->where('payment_status', 'paid');
            }])
                ->findOrFail($id);

            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $service = $slot->service;
            $servicePrice = $service->price;
            $maxMembers = (int) $service->max_members;
            $duration = (int) $slot->duration;
            $perMemberPrice = $servicePrice / $maxMembers;
            $guestAmount = ($perMemberPrice + $flatFee) * $duration;
            $data = [
                'id' => $slot->id,
                'user_id' => $slot->user_id,
                'creator_name' => $slot->user ? $slot->user->name : null,
                'status' => $slot->status,
                'duration' => $slot->duration,
                'current_members' => $slot->current_members,
                'payment_status' => $slot->payment_status,
                'service' => $slot->service,
                'members' => $slot->members, 
                'guest_price' => $guestAmount
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Slot fetched successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $slot = Slot::where('user_id', auth()->id())->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'duration' => 'sometimes|required|integer|min:1',
                'status' => 'sometimes|required|in:open,completed,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Only allow updates if slot is not completed and payment is confirmed
            if ($slot->status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update a completed slot'
                ], 400);
            }

            if ($slot->payment_status !== 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot update slot before payment is confirmed'
                ], 400);
            }

            $slot->update($request->only(['duration', 'status']));
            $slot->refresh();
            $slot->load(['service', 'user', 'members' => function($query) {
                $query->where('payment_status', 'paid');
            }]);

            $data = [
                'id' => $slot->id,
                'user_id' => $slot->user_id,
                'creator_name' => $slot->user ? $slot->user->name : null,
                'status' => $slot->status,
                'duration' => $slot->duration,
                'current_members' => $slot->current_members,
                'payment_status' => $slot->payment_status,
                'members' => $slot->members, 
                'service' => $slot->service,
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Slot updated successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $slot = Slot::where('user_id', auth()->id())->findOrFail($id);

            // Only allow deletion if slot has no members and is not completed
            if ($slot->current_members > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete slot with members'
                ], 400);
            }

            if ($slot->status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete a completed slot'
                ], 400);
            }

            $slot->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Slot deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function joinAsGuest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get the slot and check if it's available
            $slot = Slot::with('service')->findOrFail($id);

            if ($slot->status !== 'open') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slot is not available for joining'
                ], 400);
            }

            // Check if the slot is not full
            $paidMembersCount = SlotMember::where('slot_id', $slot->id)
                ->where('payment_status', 'paid')
                ->count();
            if ($paidMembersCount >= $slot->service->max_members) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slot is already full'
                ], 400);
            }

            // Check if the email is not already in this slot
            $existingMember = SlotMember::where('slot_id', $slot->id)
                                      ->where('member_email', $request->email)
                                      ->first();
            
            // Get flat fee from Utility
            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $servicePrice = $slot->service->price;
            $maxMembers = (int) $slot->service->max_members;
            $duration = (int) $slot->duration;
            // New guest formula: (service price / max_members + flat_fee) * duration
            $perMemberPrice = $servicePrice / $maxMembers;
            $guestAmount = ($perMemberPrice + $flatFee) * $duration;

            if ($existingMember) {
                // If member exists with paid status, block them
                if ($existingMember->payment_status === 'paid') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This email is already registered and paid in this slot'
                    ], 400);
                }
                
                // If member exists with pending payment, allow them to rejoin
                if ($existingMember->payment_status === 'pending') {
                    // Update their name in case it changed
                    $existingMember->update([
                        'member_name' => $request->full_name
                    ]);
                    
                    // Generate new Paystack payment
                    $reference = Paystack::genTranxRef();
                    
                    // Create new payment record (guest pays with flat fee)
                    $payment = Payment::create([
                        'user_id' => null, // Guest payment
                        'service_id' => $slot->service_id,
                        'slot_id' => $slot->id,
                        'amount' => $guestAmount * 100, // Paystack amount in kobo
                        'reference' => $reference,
                        'status' => 'pending',
                        'currency' => 'NGN',
                    ]);
                    
                    // Update the existing member with new payment ID
                    $existingMember->update([
                        'payment_id' => $payment->id
                    ]);
                    
                    // Initialize Paystack transaction
                    $paymentData = [
                        "amount" => $payment->amount,
                        "reference" => $payment->reference,
                        "email" => $request->email,
                        "currency" => "NGN",
                        "metadata" => [
                            "service_id" => $slot->service_id,
                            "payment_id" => $payment->id,
                            "slot_id" => $slot->id,
                            "slot_member_id" => $existingMember->id,
                            "is_guest" => true
                        ]
                    ];

                    $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Welcome back! Please complete your payment.',
                        'data' => [
                            'slot' => $slot,
                            'payment' => [
                                'authorization_url' => $authorizationUrl,
                                'reference' => $reference,
                                'amount' => $guestAmount
                            ],
                            'service' => $slot->service
                        ]
                    ], 200);
                }
            }

            // Generate Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Create payment record (guest pays with flat fee)
            $payment = Payment::create([
                'user_id' => null, // Guest payment
                'service_id' => $slot->service_id,
                'slot_id' => $slot->id,
                'amount' => $guestAmount * 100, // Paystack amount in kobo
                'reference' => $reference,
                'status' => 'pending',
                'currency' => 'NGN',
            ]);

            // Add guest as slot member (pending payment)
            $slotMember = SlotMember::create([
                'slot_id' => $slot->id,
                'user_id' => null, // Guest member
                'member_name' => $request->full_name,
                'member_email' => $request->email,
                'member_phone' => $request->phone,
                'payment_status' => 'pending',
                'payment_id' => $payment->id
            ]);

            // Initialize Paystack transaction
            $paymentData = [
                "amount" => $payment->amount,
                "reference" => $payment->reference,
                "email" => $request->email,
                "currency" => "NGN",
                "metadata" => [
                    "service_id" => $slot->service_id,
                    "payment_id" => $payment->id,
                    "slot_id" => $slot->id,
                    "slot_member_id" => $slotMember->id,
                    "is_guest" => true
                ]
            ];

            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

            // Increment current members count
            $slot->increment('current_members');

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Slot joined successfully. Please complete payment.',
                'data' => [
                    'slot' => $slot,
                    'payment' => [
                        'authorization_url' => $authorizationUrl,
                        'reference' => $reference,
                        'amount' => $guestAmount
                    ],
                    'service' => $slot->service
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to join slot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmGuestPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'slot_id' => 'required|exists:slots,id',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // First check if payment exists in our database
            $payment = Payment::where('reference', $request->reference)
                            ->where('status', 'pending')
                            ->first();

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found or already processed'
                ], 404);
            }

            try {
                // Manual Paystack verification using Guzzle
                $client = new Client();
                $response = $client->request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($request->reference), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . config('paystack.secretKey'),
                        'Cache-Control' => 'no-cache',
                    ]
                ]);
                
                $paymentDetails = json_decode($response->getBody(), true);

                if (!$paymentDetails['status'] || $paymentDetails['data']['status'] !== 'success') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment verification failed. Status: ' . ($paymentDetails['data']['status'] ?? 'unknown')
                    ], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed: ' . $e->getMessage()
                ], 400);
            }

            // Update payment status
            $payment->update([
                'status' => 'success',
                'payment_channel' => $paymentDetails['data']['channel'] ?? 'unknown',
                'metadata' => $paymentDetails['data']['metadata'] ?? null
            ]);

            // Update slot member payment status
            $slotMember = SlotMember::where([
                'slot_id' => $request->slot_id,
                'member_email' => $request->email,
                'payment_id' => $payment->id
            ])->first();

            if (!$slotMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Slot member not found'
                ], 404);
            }

            $slotMember->update([
                'payment_status' => 'paid'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'slot_member' => $slotMember,
                    'payment' => $payment
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up expired pending payments and members
     * This can be called via a scheduled job or manually
     */
    public function cleanupExpiredPayments()
    {
        try {
            DB::beginTransaction();

            // Find pending payments older than 24 hours
            $expiredPayments = Payment::where('status', 'pending')
                                    ->where('created_at', '<', now()->subHours(24))
                                    ->get();

            $cleanedCount = 0;

            foreach ($expiredPayments as $payment) {
                // Find associated slot member
                $slotMember = SlotMember::where('payment_id', $payment->id)->first();
                
                if ($slotMember) {
                    // Decrement slot member count
                    $slot = Slot::find($payment->slot_id);
                    if ($slot && $slot->current_members > 0) {
                        $slot->decrement('current_members');
                    }
                    
                    // Delete the slot member
                    $slotMember->delete();
                }
                
                // Delete the payment
                $payment->delete();
                $cleanedCount++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Cleaned up {$cleanedCount} expired pending payments"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cleanup expired payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume payment for a guest with pending payment
     */
    public function resumeGuestPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get the slot
            $slot = Slot::with('service')->findOrFail($id);

            if ($slot->status !== 'open') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slot is not available'
                ], 400);
            }

            // Find existing member with pending payment
            $existingMember = SlotMember::where('slot_id', $slot->id)
                                      ->where('member_email', $request->email)
                                      ->where('payment_status', 'pending')
                                      ->first();

            if (!$existingMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No pending payment found for this email'
                ], 404);
            }

            // Generate new Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Get flat fee from Utility
            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $servicePrice = $slot->service->price;
            $maxMembers = (int) $slot->service->max_members;
            $duration = (int) $slot->duration;
            // New guest formula: (service price / max_members + flat_fee) * duration
            $perMemberPrice = $servicePrice / $maxMembers;
            $guestAmount = ($perMemberPrice + $flatFee) * $duration;

            // Create new payment record
            $payment = Payment::create([
                'user_id' => null, // Guest payment
                'service_id' => $slot->service_id,
                'slot_id' => $slot->id,
                'amount' => $guestAmount * 100, // Paystack amount in kobo
                'reference' => $reference,
                'status' => 'pending',
                'currency' => 'NGN',
            ]);
            
            // Update the existing member with new payment ID
            $existingMember->update([
                'payment_id' => $payment->id
            ]);
            
            // Initialize Paystack transaction
            $paymentData = [
                "amount" => $payment->amount,
                "reference" => $payment->reference,
                "email" => $request->email,
                "currency" => "NGN",
                "metadata" => [
                    "service_id" => $slot->service_id,
                    "payment_id" => $payment->id,
                    "slot_id" => $slot->id,
                    "slot_member_id" => $existingMember->id,
                    "is_guest" => true
                ]
            ];

            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment resumed successfully. Please complete your payment.',
                'data' => [
                    'slot' => $slot,
                    'payment' => [
                        'authorization_url' => $authorizationUrl,
                        'reference' => $reference,
                        'amount' => $guestAmount
                    ],
                    'service' => $slot->service
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resume payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume payment for a creator (auth user) with pending payment
     */
    public function resumeCreatorPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'slot_id' => 'required|exists:slots,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get the slot
            $slot = Slot::with('service')->where('user_id', auth()->id())->findOrFail($request->slot_id);

            if ($slot->status !== 'open') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slot is not available'
                ], 400);
            }

            // Find existing member with pending payment (creator)
            $existingMember = SlotMember::where('slot_id', $slot->id)
                                      ->where('user_id', auth()->id())
                                      ->where('payment_status', 'pending')
                                      ->first();

            if (!$existingMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No pending payment found for this user'
                ], 404);
            }

            // Generate new Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Get creator percentage from Utility
            $utility = Utility::first();
            $creatorPercentage = $utility ? $utility->creator_percentage : 0;
            $servicePrice = $slot->service->price;
            $maxMembers = (int) $slot->service->max_members;
            $duration = (int) $slot->duration;
            // Creator formula: (service price / max_members - creator_percentage%) * duration
            $perMemberPrice = $servicePrice / $maxMembers;
            $creatorAmount = ($perMemberPrice - ($perMemberPrice * ($creatorPercentage / 100))) * $duration;

            // Create new payment record
            $payment = Payment::create([
                'user_id' => auth()->id(),
                'service_id' => $slot->service_id,
                'slot_id' => $slot->id,
                'amount' => $creatorAmount * 100, // Paystack amount in kobo
                'reference' => $reference,
                'status' => 'pending',
                'currency' => 'NGN',
            ]);
            
            // Update the existing member with new payment ID
            $existingMember->update([
                'payment_id' => $payment->id
            ]);
            
            // Initialize Paystack transaction
            $paymentData = [
                "amount" => $payment->amount,
                "reference" => $payment->reference,
                "email" => auth()->user()->email,
                "currency" => "NGN",
                "metadata" => [
                    "service_id" => $slot->service_id,
                    "payment_id" => $payment->id,
                    "slot_id" => $slot->id,
                    "slot_member_id" => $existingMember->id,
                    "is_creator" => true
                ]
            ];

            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment resumed successfully. Please complete your payment.',
                'data' => [
                    'slot' => $slot,
                    'payment' => [
                        'authorization_url' => $authorizationUrl,
                        'reference' => $reference,
                        'amount' => $creatorAmount
                    ],
                    'service' => $slot->service
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resume payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function trending(Request $request)
    {
        $days = $request->input('days', 7); // Allow override via query param

        $trendingSlots = Slot::with([
                'service',
                'user',
                'members' => function ($query) {
                    $query->where('payment_status', 'paid'); // Load only paid members
                }
            ])
            ->withCount(['members as recent_paid_members_count' => function ($query) use ($days) {
                $query->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->subDays($days));
            }])
            ->where('is_active', true)
            ->orderByDesc('recent_paid_members_count')
            ->take(6)
            ->get();

        // Add guest_price for each slot
        $utility = Utility::first();
        $flatFee = $utility ? $utility->flat_fee : 0;

        $trendingSlots = $trendingSlots->map(function ($slot) use ($flatFee) {
            $service = $slot->service;
            $servicePrice = $service->price;
            $maxMembers = (int) $service->max_members;
            $duration = (int) $slot->duration;
            $perMemberPrice = $servicePrice / $maxMembers;
            $guestAmount = ($perMemberPrice + $flatFee) * $duration;

            return [
                'id' => $slot->id,
                'user_id' => $slot->user_id,
                'creator_name' => $slot->user ? $slot->user->name : null,
                'status' => $slot->status,
                'duration' => $slot->duration,
                'current_members' => $slot->members->count(), // Now only paid members
                'payment_status' => $slot->payment_status,
                'service' => $slot->service,
                // 'members' => $slot->members, // Optional: include if needed
                'guest_price' => $guestAmount
            ];
        })->values(); // take(6) already applied above, values() reindexes

        return response()->json([
            'status' => 'success',
            'data' => $trendingSlots
        ]);
    }

    public function utility()
    {
        $utility = Utility::first();
        $flatFee = $utility ? $utility->flat_fee : 0;
        $creatorPercentage = $utility ? $utility->creator_percentage : 0;
        return response()->json([
            'status' => 'success',
            'flat_fee' => $flatFee,
            'creator_percentage' => $creatorPercentage
        ]);
    }
}
