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

class SlotController extends Controller
{
    public function index()
    {
        try {
            $query = Slot::with(['service', 'members']);

            // If user is authenticated, show their slots
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }

            $slots = $query->latest()->get();

            return response()->json([
                'status' => 'success',
                'data' => $slots
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
            'expires_at' => 'required|date|after:today'
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

            // Create the slot
            $slot = Slot::create([
                'service_id' => $service->id,
                'user_id' => $request->user()->id,
                'current_members' => 1, // Creator is the first member
                'duration' => $request->duration,
                'status' => 'open',
                'expires_at' => $request->expires_at,
                'payment_status' => 'pending'
            ]);

            // Generate Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Create payment record
            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'slot_id' => $slot->id,
                'amount' => $service->price * 100, // Paystack amount in kobo
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
                        'amount' => $service->price
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
            $slot->update(['payment_status' => 'paid']);

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
                'slot' => $slot,
                'slot_member' => $slotMember,
                'payment' => $payment
            ], 'Payment confirmed successfully', 200);

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
            $slot = Slot::with(['service', 'members'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Slot fetched successfully',
                'data' => $slot
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
                'expires_at' => 'sometimes|required|date|after:today'
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

            $slot->update($request->only(['duration', 'status', 'expires_at']));

            return response()->json([
                'status' => 'success',
                'message' => 'Slot updated successfully',
                'data' => $slot
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
            if ($slot->current_members >= $slot->service->max_members) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This slot is already full'
                ], 400);
            }

            // Check if the email is not already in this slot
            $existingMember = SlotMember::where('slot_id', $slot->id)
                                      ->where('member_email', $request->email)
                                      ->first();
            
            if ($existingMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This email is already registered in this slot'
                ], 400);
            }

            // Generate Paystack payment
            $reference = Paystack::genTranxRef();
            
            // Create payment record
            $payment = Payment::create([
                'user_id' => null, // Guest payment
                'service_id' => $slot->service_id,
                'slot_id' => $slot->id,
                'amount' => $slot->service->price * 100, // Paystack amount in kobo
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
                'slot' => $slot,
                'payment' => [
                    'authorization_url' => $authorizationUrl,
                    'reference' => $reference,
                    'amount' => $slot->service->price
                ],
                'service' => $slot->service
            ], 'Slot joined successfully. Please complete payment.');

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
                'slot_member' => $slotMember,
                'payment' => $payment
            ], 'Payment confirmed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
