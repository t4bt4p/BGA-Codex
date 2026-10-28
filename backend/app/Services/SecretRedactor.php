<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SecretRedactor
{
    public function redact(string $message): string
    {
        $secrets = [config('services.polygon.private_key'), config('services.opn.secret_key'), config('app.key')];
        try {
            $path = config('services.polygon.encrypted_wallet_path');
            if ($path && Storage::disk('local')->exists($path)) {
                $secrets[] = Crypt::decryptString(Storage::disk('local')->get($path));
            }
        } catch (Throwable) {
            // Reporting must still work when the wallet cannot be decrypted.
        }
        if (app()->bound('request')) {
            $request = app('request');
            $secrets[] = $request->bearerToken();
            $secrets[] = $request->input('password');
            $secrets[] = $request->input('User_password');
        }
        foreach ($secrets as $secret) {
            if (is_string($secret) && strlen($secret) >= 6) {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        // Node/provider errors can include credentials that differ from current config.
        return preg_replace(['/\b0x[0-9a-fA-F]{64}\b/', '/\bskey_[A-Za-z0-9_]+\b/'], '[redacted]', $message);
    }
}
