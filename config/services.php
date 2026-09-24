<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'opn' => [
        'public_key' => env('OPN_PUBLIC_KEY'),
        'secret_key' => env('OPN_SECRET_KEY'),
        // Keep this explicit so a test charge can never be accepted as a live payment.
        'live_mode' => (bool) env('OPN_LIVE_MODE', false),
    ],

    'polygon' => [
        'enabled' => env('POLYGON_ENABLED', false),
        'network' => env('POLYGON_NETWORK', 'polygon-amoy'),
        'chain_id' => (int) env('POLYGON_CHAIN_ID', 80002),
        'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-amoy.drpc.org'),
        'private_key' => env('POLYGON_PRIVATE_KEY'),
        'encrypted_wallet_path' => env('POLYGON_ENCRYPTED_WALLET_PATH', 'polygon/wallet.key'),
        'wallet_address_path' => env('POLYGON_WALLET_ADDRESS_PATH', 'polygon/address.txt'),
        'contract_address' => env('POLYGON_CONTRACT_ADDRESS'),
        'contract_info_path' => env('POLYGON_CONTRACT_INFO_PATH', 'polygon/contract.json'),
        'confirmations' => (int) env('POLYGON_CONFIRMATIONS', 1),
        'priority_fee_gwei' => env('POLYGON_PRIORITY_FEE_GWEI', 25),
        'max_fee_gwei' => env('POLYGON_MAX_FEE_GWEI', 35),
        'explorer_url' => env('POLYGON_EXPLORER_URL', 'https://amoy.polygonscan.com'),
    ],

];
