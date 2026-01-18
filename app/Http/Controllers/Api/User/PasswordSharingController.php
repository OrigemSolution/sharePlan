<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PasswordService;
use App\Models\PasswordSharingSlot;
use App\Models\Payment;
use App\Models\PasswordSharingSlotMember;
use App\Models\Utility;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PasswordSharingController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = PasswordSharingSlot::with([
                'passwordService',
                'user',
                'passwordSharingSlotMembers' => function ($q) {
                    $q->where('payment_status', 'paid');
                }
            ]);

            // Authenticated users see their own + active ones; guests see only active.
            if (auth()->check()) {
                $query->where(function ($q) {
                    $q->where('user_id', auth()->id())
                      ->orWhere('is_active', true);
                });
            } else {
                $query->where('is_active', true);
            }

            $slots = $query->latest()->get();

            $utility  = Utility::first();
            $flatFee  = $utility ? $utility->flat_fee : 0;

            $data = $slots->map(function ($slot) use ($flatFee) {
                $service          = $slot->passwordService;
                $servicePrice     = $service->price;
                $guestLimit       = (int) $slot->guest_limit;
                $duration         = (int) $slot->duration;
                $perMemberPrice   = $servicePrice / max($guestLimit, 1);
                $guestAmount      = round(($perMemberPrice + $flatFee) * $duration);
                $paidMembers      = $slot->passwordSharingSlotMembers->where('payment_status', 'paid')->count();

                return [
                    'id'              => $slot->id,
                    'user_id'         => $slot->user_id,
                    'creator_name'    => optional($slot->user)->name,
                    'status'          => $slot->status,
                    'duration'        => $slot->duration,
                    'current_members' => $paidMembers,
                    'max_members'     => $guestLimit,
                    'is_available'    => $paidMembers < $guestLimit,
                    'payment_status'  => $slot->payment_status,
                    'service'         => $service,
                    'guest_price'     => $guestAmount,
                ];
            })->sortByDesc('is_available')->values();

            return response()->json([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Creator opens a new password-sharing slot and initializes Paystack payment.
     */
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
            'password_service_id' => 'required|exists:password_services,id',
            'duration'            => 'required|integer|min:1',
            'guest_limit'         => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = auth()->user();
            $service = PasswordService::where('id', $request->password_service_id)
                        ->where('is_active', true)->first();

            if (!$service) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Service not available',
                ], 404);
            }

            $perMemberPrice = $service->price / $request->guest_limit;
            $utility        = Utility::first();
            $flatFee        = $utility ? $utility->flat_fee : 0;

            $amount = ($perMemberPrice + $flatFee) * $request->duration;
            
            // Create the PS slot
            $slot = PasswordSharingSlot::create([
                'password_service_id' => $service->id,
                'user_id'             => $user->id,
                'guest_limit'         => $request->guest_limit,
                'current_members'     => 1,
                'duration'            => $request->duration,
                'status'              => 'open',
                'payment_status'      => 'pending',
                'is_active'           => false // PS Slot is not active until payment is confirmed
            ]);

            // Generate Paystack payment reference
            $reference = Paystack::genTranxRef();
            // create payment record for creator
            $payment = Payment::create([
                'user_id'                   => $user->id,
                'password_service_id'       => $service->id,
                'password_sharing_slot_id'  => $slot->id,
                'amount'                    => $amount * 100, // kobo
                'status'                    => 'pending',
                'reference'                 => $reference,
                'currency'                  => 'NGN',
            ]);

            // Update slot with payment reference
            $slot->update(['payment_reference' => $reference]);

            // Creator becomes first member (pending payment)
            PasswordSharingSlotMember::create([
                'user_id'        => $user->id,
                'member_name' => $user->name,
                'member_email' => $user->email,
                'member_phone' => $user->phone,
                'password_sharing_slot_id' => $slot->id,
                'payment_status' => 'pending',
                'payment_id' => $payment->id,
            ]);

            // Initiate Paystack transaction
            $metadata = [
                'slot_id'       => $slot->id,
                'user_id'       => $user->id,
                'guest_type'    => 'creator',
                'platform_fee'  => $flatFee,
                'per_member_price' => $perMemberPrice,
                'password_sharing_service_name' => $service->name,
                'payment_id' => $payment->id,
            ];

            $paymentData = [
                'amount'          => $payment->amount, // Already in kobo from payment record
                'email'           => $user->email,
                'reference'       => $payment->reference,
                'currency'        => 'NGN',
                'metadata'        => $metadata,
            ];

            // Get authorization URL (not redirectNow for API response)
            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;

            DB::commit();

            return response()->json([
                'status'         => 'success',
                'message' => 'Password Sharing Slot created successfully. Please complete payment.',
                'authorization_url'  => $authorizationUrl,
                'reference'      => $paymentData['reference'],
                'amount'         => $amount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm Paystack payment for a password-sharing slot.
     */
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'slot_id'   => 'required|exists:password_sharing_slots,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $payment = Payment::where('reference', $request->reference)
                              ->where('status', 'pending')
                              ->first();
            if (!$payment) {
                return response()->json(['status' => 'error', 'message' => 'Payment not found or already processed'], 404);
            }

            // Verify with Paystack
            $client = new \GuzzleHttp\Client();
            $resp = $client->request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($request->reference), [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('paystack.secretKey'),
                    'Cache-Control' => 'no-cache',
                ]
            ]);
            $details = json_decode($resp->getBody(), true);
            if (!$details['status'] || $details['data']['status'] !== 'success') {
                return response()->json(['status' => 'error', 'message' => 'Payment verification failed'], 400);
            }

            // update payment
            $payment->update([
                'status'          => 'success',
                'payment_channel' => $details['data']['channel'] ?? 'unknown',
                'metadata'        => $details['data']['metadata'] ?? null,
            ]);

            $slot = PasswordSharingSlot::findOrFail($request->slot_id);
            // increment members only if not counted yet
            if ($slot->current_members < $slot->guest_limit) {
                $slot->increment('current_members');
            }
            if ($slot->current_members >= $slot->guest_limit) {
                $slot->status = 'completed';
            }
            $slot->payment_status = 'paid';
            if ($payment->user_id && $payment->user_id == $slot->user_id) {
                $slot->is_active = true;
            }
            $slot->save();

            // update slot member
            $member = PasswordSharingSlotMember::where('password_sharing_slot_id', $slot->id)
                        ->where(function ($q) use ($payment) {
                            $q->where('payment_id', $payment->id)
                              ->orWhere('user_id', $payment->user_id);
                        })->first();

            if ($member) {
                $member->update(['payment_status' => 'paid']);
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Payment confirmed', 'slot' => $slot]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Guest joins an existing password-sharing slot.
     */
    public function joinAsGuest(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'phone'     => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        DB::beginTransaction();
        try {
            $slot = PasswordSharingSlot::with('passwordService')->findOrFail($id);
            if ($slot->status !== 'open') {
                return response()->json(['status' => 'error', 'message' => 'Slot not open for joining'], 400);
            }
            $paid = PasswordSharingSlotMember::where('password_sharing_slot_id', $slot->id)->where('payment_status', 'paid')->count();
            if ($paid >= $slot->guest_limit) {
                return response()->json(['status' => 'error', 'message' => 'Slot is full'], 400);
            }
            $existing = PasswordSharingSlotMember::where('password_sharing_slot_id', $slot->id)->where('member_email', $request->email)->first();
            if ($existing && $existing->payment_status === 'paid') {
                return response()->json(['status' => 'error', 'message' => 'Email already joined'], 400);
            }

            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $perMember = $slot->passwordService->price / $slot->guest_limit;
            $amount = ($perMember + $flatFee) * $slot->duration;

            $slotMember = $existing ?? PasswordSharingSlotMember::create([
                'password_sharing_slot_id' => $slot->id,
                'user_id'        => null,
                'member_name'    => $request->full_name,
                'member_email'   => $request->email,
                'member_phone'   => $request->phone,
                'payment_status' => 'pending',
            ]);

            $reference = Paystack::genTranxRef();
            $payment = Payment::create([
                'password_service_id'       => $slot->passwordService->id,
                'password_sharing_slot_id'  => $slot->id,
                'amount'                    => $amount * 100, // kobo
                'status'                    => 'pending',
                'reference'                 => $reference,
                'currency'                  => 'NGN',
            ]);
            $slotMember->update(['payment_id' => $payment->id]);

            $metadata = [
                'slot_id'        => $slot->id,
                'service_id'     => $slot->passwordService->id,
                'slot_member_id' => $slotMember->id,
                'guest_type'     => 'guest',
                'platform_fee'   => $flatFee,
                'per_member_price' => $perMember,
                'payment_id'     => $payment->id,
            ];
            $paymentData = [
                'amount'       => $payment->amount, // Already in kobo from payment record
                'email'        => $request->email,
                'reference'    => $payment->reference,
                'currency'     => 'NGN',
                'metadata'     => $metadata,
            ];
            $authorizationUrl = Paystack::getAuthorizationUrl($paymentData)->url;
            DB::commit();

            return response()->json(['status' => 'success', 'data' => [
                'authorization_url' => $authorizationUrl,
                'reference'         => $paymentData['reference'],
                'amount'            => $amount,
            ]]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display details of a specific password-sharing slot.
     */
    public function show($id)
    {
        try {
            $slot = PasswordSharingSlot::with(['passwordService', 'user', 'passwordSharingSlotMembers' => function ($q) {
                $q->where('payment_status', 'paid');
            }])->findOrFail($id);

            $utility = Utility::first();
            $flatFee = $utility ? $utility->flat_fee : 0;
            $perMemberPrice = $slot->passwordService->price / $slot->guest_limit;
            $guestAmount = ($perMemberPrice + $flatFee) * $slot->duration;

            $data = [
                'id'              => $slot->id,
                'user_id'         => $slot->user_id,
                'creator_name'    => optional($slot->user)->name,
                'status'          => $slot->status,
                'duration'        => $slot->duration,
                'current_members' => $slot->passwordSharingSlotMembers->where('payment_status', 'paid')->count(),
                'max_members'     => $slot->guest_limit,
                'payment_status'  => $slot->payment_status,
                'is_active'       => $slot->is_active,
                'service'         => $slot->passwordService,
                'members'         => $slot->passwordSharingSlotMembers->where('payment_status', 'paid'),
                'guest_price'     => $guestAmount,
            ];

            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}



