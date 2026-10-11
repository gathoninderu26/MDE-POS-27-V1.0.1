<?php

return [
    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja API Configuration
    |--------------------------------------------------------------------------
    |
    | Set MPESA_ENV=sandbox for testing with the Daraja Sandbox.
    | Switch to MPESA_ENV=production when you have live credentials.
    |
    | Sandbox portal : https://developer.safaricom.co.ke
    | Live portal    : https://developer.safaricom.co.ke (production apps)
    |
    */

    'env' => env('MPESA_ENV', 'sandbox'),

    /*
    | Daraja App Credentials (from the Daraja portal → App Details)
    */
    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    /*
    | Lipa Na M-Pesa Online (STK Push)
    | Shortcode  : Your Till number (Buy Goods) or Paybill number.
    | Passkey    : From the Daraja portal → Lipa na M-Pesa → Go Live / Sandbox.
    | Sandbox defaults (these work as-is in sandbox mode):
    |   shortcode  = 174379
    |   passkey    = bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
    */
    'shortcode' => env('MPESA_SHORTCODE', '174379'),
    'passkey' => env('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),

    /*
    | Callback URL – must be a publicly reachable HTTPS URL.
    | Use ngrok (https://ngrok.com) in local development.
    | Example: https://abc123.ngrok.io/mpesa/callback
    */
    'callback_url' => env('MPESA_CALLBACK_URL', env('APP_URL', 'http://localhost:8000').'/mpesa/callback'),

    /*
    | Transaction metadata shown in the customer's M-Pesa message.
    | AccountReference max 12 chars, TransactionDesc max 20 chars.
    */
    'account_reference' => env('MPESA_ACCOUNT_REFERENCE', 'MDE POS'),
    'transaction_desc' => env('MPESA_TRANSACTION_DESC', 'MDE POS Sale'),

    /*
    | Transaction type:
    |   CustomerBuyGoodsOnline  → Till number (Buy Goods)
    |   CustomerPayBillOnline   → Paybill number
    */
    'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerBuyGoodsOnline'),
];
