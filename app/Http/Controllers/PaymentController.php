<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Service;
use App\Models\Slot;
use App\Models\User;
use App\Models\SlotMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    // public function initiatePayment(Request $request)
    // {
    //     $request->validate([
    //         'service_id' => 'required|exists:services,id',
    //     ]);

    //     $service = Service::findOrFail($request->service_id);
    //     $user = Auth::user();

    //     // Generate unique payment reference
    //     $reference = Paystack::genTranxRef();

    //     // Create payment record
    //     $payment = Payment::create([
    //         'user_id' => $user->id,
    //         'service_id' => $service->id,
    //         'amount' => $service->price * 100, // Paystack amount in kobo
    //         'reference' => $reference,
    //         'status' => 'pending',
    //         'currency' => 'NGN',
    //     ]);

    //     try {
    //         $data = [
    //             "amount" => $payment->amount,
    //             "reference" => $payment->reference,
    //             "email" => $user->email,
    //             "currency" => "NGN",
    //             "metadata" => [
    //                 "service_id" => $service->id,
    //                 "user_id" => $user->id,
    //                 "payment_id" => $payment->id
    //             ]
    //         ];

    //         $authorizationUrl = Paystack::getAuthorizationUrl($data)->url;

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Payment initiated',
    //             'data' => [
    //                 'authorization_url' => $authorizationUrl,
    //                 'reference' => $reference,
    //                 'payment_id' => $payment->id
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Could not initiate payment',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function verifyPayment(Request $request)
    // {
    //     $request->validate([
    //         'reference' => 'required|string'
    //     ]);

    //     try {
    //         $paymentDetails = Paystack::getPaymentData($request->reference);

    //         if (!$paymentDetails['status']) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Payment verification failed'
    //             ], 400);
    //         }

    //         $payment = Payment::where('reference', $paymentDetails['data']['reference'])->first();

    //         if (!$payment) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Payment not found'
    //             ], 404);
    //         }

    //         $payment->update([
    //             'status' => $paymentDetails['data']['status'],
    //             'payment_channel' => $paymentDetails['data']['channel'],
    //             'metadata' => $paymentDetails['data']['metadata']
    //         ]);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Payment verified successfully',
    //             'data' => [
    //                 'payment' => $payment,
    //                 'service_id' => $payment->service_id
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Could not verify payment',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function handleWebhook(Request $request)
    {
        $paystack_secret = config('paystack.secretKey');
        $signature = $request->header('x-paystack-signature');
        $computedSignature = hash_hmac('sha512', $request->getContent(), $paystack_secret);

        if ($signature !== $computedSignature) {
            \Log::warning('Invalid Paystack webhook signature', [
                'signature' => $signature,
                'computed' => $computedSignature,
                'payload' => $request->all()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

        $payload = $request->all();

        if (!isset($payload['event'], $payload['data']['reference'])) {
            \Log::warning('Malformed Paystack webhook payload', ['payload' => $payload]);
            return response()->json(['status' => 'error', 'message' => 'Malformed payload'], 400);
        }

        if ($payload['event'] !== 'charge.success') {
            return response()->json(['status' => 'success', 'message' => 'Event ignored']);
        }

        DB::beginTransaction();
        try {
            // 1. Update Payment
            $payment = \App\Models\Payment::where('reference', $payload['data']['reference'])->first();
            if (!$payment) {
                \Log::warning('Payment not found for reference', ['reference' => $payload['data']['reference']]);
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
            }
            $payment->update([
                'status' => 'success',
                'payment_channel' => $payload['data']['channel'] ?? null,
                'metadata' => $payload['data']['metadata'] ?? null
            ]);

            // 2. Update Slot
            $slot = \App\Models\Slot::where('payment_reference', $payload['data']['reference'])->first();
            if ($slot && $slot->payment_status !== 'paid') {
                $slot->update([
                    'status' => 'success',
                    'payment_status' => 'paid',
                    'is_active' => true
                ]);
            }

            // 3. Update SlotMember
            $slotMember = \App\Models\SlotMember::where('payment_id', $payment->id)->first();
            if ($slotMember && $slotMember->payment_status !== 'paid') {
                $slotMember->update(['payment_status' => 'paid']);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Get all payments for the authenticated user
     */
    public function getUserPayments(Request $request)
    {
        $user = Auth::user();
        
        $payments = Slot::with(['service', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $payments
        ]);
    }

    /**
     * Get all payments in the system (Admin only)
     */
    public function getAllPayments(Request $request)
    {
        $payments = Slot::with(['service', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $payments
        ]);
    }

    /**
     * Get detailed information about a specific payment (Admin only)
     */
    public function getPaymentDetails($id)
    {
        $payment = Slot::with(['service', 'user'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $payment
        ]);
    }
}
