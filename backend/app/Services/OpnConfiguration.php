<?php

namespace App\Services;

use RuntimeException;

class OpnConfiguration
{
    public function assertUsable(): void
    {
        $secretKey = (string) config('services.opn.secret_key');

        if ($secretKey === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า OPN_SECRET_KEY');
        }

        $isTestKey = str_starts_with($secretKey, 'skey_test_');
        $looksLikeLiveKey = str_starts_with($secretKey, 'skey_') && ! $isTestKey;

        if ($this->expectsLiveMode() && ! $looksLikeLiveKey) {
            throw new RuntimeException('OPN_LIVE_MODE=true แต่ OPN_SECRET_KEY ไม่ใช่ Live Secret Key');
        }

        if (! $this->expectsLiveMode() && ! $isTestKey && app()->environment('production')) {
            throw new RuntimeException('Production ต้องใช้ Test Secret Key เมื่อ OPN_LIVE_MODE=false');
        }
    }

    public function assertChargeMode(array $charge): void
    {
        if (! array_key_exists('livemode', $charge)) {
            throw new RuntimeException('Opn ไม่ได้ส่งสถานะ livemode กลับมา');
        }

        if ((bool) $charge['livemode'] !== $this->expectsLiveMode()) {
            throw new RuntimeException('โหมดของ Opn Charge ไม่ตรงกับ OPN_LIVE_MODE');
        }
    }

    public function expectsLiveMode(): bool
    {
        return (bool) config('services.opn.live_mode');
    }
}
