<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
        ]);

        $service = Service::findOrFail($request->service_id);
        $user = Auth::user();

        // Generate unique payment reference
        $reference = Paystack::genTranxRef();

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'amount' => $service->price * 100, // Paystack amount in kobo
            'reference' => $reference,
            'status' => 'pending',
            'currency' => 'NGN',
        ]);

        try {
            $data = [
                "amount" => $payment->amount,
                "reference" => $payment->reference,
                "email" => $user->email,
                "currency" => "NGN",
                "metadata" => [
                    "service_id" => $service->id,
                    "user_id" => $user->id,
                    "payment_id" => $payment->id
                ]
            ];

            $authorizationUrl = Paystack::getAuthorizationUrl($data)->url;

            return response()->json([
                'status' => 'success',
                'message' => 'Payment initiated',
                'data' => [
                    'authorization_url' => $authorizationUrl,
                    'reference' => $reference,
                    'payment_id' => $payment->id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not initiate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string'
        ]);

        try {
            $paymentDetails = Paystack::getPaymentData($request->reference);

            if (!$paymentDetails['status']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed'
                ], 400);
            }

            $payment = Payment::where('reference', $paymentDetails['data']['reference'])->first();

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found'
                ], 404);
            }

            $payment->update([
                'status' => $paymentDetails['data']['status'],
                'payment_channel' => $paymentDetails['data']['channel'],
                'metadata' => $paymentDetails['data']['metadata']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified successfully',
                'data' => [
                    'payment' => $payment,
                    'service_id' => $payment->service_id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not verify payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        $paystack_secret = config('paystack.secretKey');
        $signature = $request->header('x-paystack-signature');
        $computedSignature = hash_hmac('sha512', $request->getContent(), $paystack_secret);

        if ($signature !== $computedSignature) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

        $payload = $request->all();

        if ($payload['event'] === 'charge.success') {
            $payment = Payment::where('reference', $payload['data']['reference'])->first();
            
            if ($payment) {
                $payment->update([
                    'status' => 'success',
                    'payment_channel' => $payload['data']['channel'],
                    'metadata' => $payload['data']['metadata']
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment updated successfully'
                ]);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
    }
}
