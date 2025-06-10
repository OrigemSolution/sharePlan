<?php

return [
    /**
     * Public Key From Paystack Dashboard
     */
    'publicKey' => env('PAYSTACK_PUBLIC_KEY'),

    /**
     * Secret Key From Paystack Dashboard
     */
    'secretKey' => env('PAYSTACK_SECRET_KEY'),

    /**
     * Payment URL
     */
    'paymentUrl' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),

    /**
     * Merchant Email From Paystack Dashboard
     */
    'merchantEmail' => env('PAYSTACK_MERCHANT_EMAIL'),
]; 