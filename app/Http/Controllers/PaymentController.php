<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Service;
use App\Models\Slot;
use App\Models\User;
use App\Models\SlotMember;
use App\Models\PasswordSharingSlot;
use App\Models\PasswordSharingSlotMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Unicodeveloper\Paystack\Facades\Paystack;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
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

            // Determine payment type and process accordingly
            $this->processRegularSlotPayment($payment, $payload['data']['reference']);
            $this->processPasswordSharingSlotPayment($payment, $payload['data']['reference']);

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
     * Process regular slot payments
     */
    private function processRegularSlotPayment($payment, $reference)
    {
        // Update Regular Slot (for Creator)
        $slot = Slot::where('payment_reference', $reference)->first();
        if ($slot) {
            $isCreatorPayment = $payment->user_id && $slot->user_id == $payment->user_id;
            
            $slot->update([
                'payment_status' => 'paid',
                'is_active' => $isCreatorPayment ? true : $slot->is_active
            ]);
        }

        // Update Regular SlotMember
        $slotMember = SlotMember::where('payment_id', $payment->id)->first();
        if ($slotMember && $slotMember->payment_status !== 'paid') {
            $slotMember->update(['payment_status' => 'paid']);
        }
    }

    /**
     * Process password sharing slot payments
     */
    private function processPasswordSharingSlotPayment($payment, $reference)
    {
        // Skip if this payment is not for a password sharing slot
        if (!$payment->password_sharing_slot_id) {
            return;
        }

        // Find the password sharing slot by password_sharing_slot_id from payment
        $passwordSlot = PasswordSharingSlot::where('id', $payment->password_sharing_slot_id)->first();
        
        if (!$passwordSlot) {
            \Log::warning('Password sharing slot not found for payment', [
                'payment_id' => $payment->id,
                'password_sharing_slot_id' => $payment->password_sharing_slot_id
            ]);
            return;
        }

        // Determine if this is a creator payment
        $isCreatorPayment = $payment->user_id && $passwordSlot->user_id == $payment->user_id;
        
        // Update password sharing slot
        $updateData = [
            'payment_status' => 'paid',
            'payment_reference' => $reference,
        ];

        // If creator payment, activate the slot
        if ($isCreatorPayment) {
            $updateData['is_active'] = true;
        }

        $passwordSlot->update($updateData);

        // Update Password Sharing SlotMember
        $passwordSlotMember = PasswordSharingSlotMember::where('payment_id', $payment->id)->first();
        if ($passwordSlotMember) {
            // Only update if not already paid (to avoid double processing)
            if ($passwordSlotMember->payment_status !== 'paid') {
                $passwordSlotMember->update(['payment_status' => 'paid']);
                
                // Reload the slot to get updated member count
                $passwordSlot->refresh();
                
                // Count paid members
                $paidMembersCount = PasswordSharingSlotMember::where('password_sharing_slot_id', $passwordSlot->id)
                    ->where('payment_status', 'paid')
                    ->count();
                
                // Update current_members to match actual paid members count
                $passwordSlot->update(['current_members' => $paidMembersCount]);
                
                // Mark slot as completed if it's now full
                if ($passwordSlot->current_members >= $passwordSlot->guest_limit) {
                    $passwordSlot->update(['status' => 'completed']);
                }
            }
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
