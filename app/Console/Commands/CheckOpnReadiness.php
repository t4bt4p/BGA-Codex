<?php

namespace App\Console\Commands;

use App\Services\OpnConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckOpnReadiness extends Command
{
    protected $signature = 'opn:check {--remote : Ask Opn for this account capability without creating a charge}';

    protected $description = 'Check Opn/Omise PromptPay configuration without charging money';

    public function handle(OpnConfiguration $configuration): int
    {
        $live = $configuration->expectsLiveMode();
        $this->components->info('Opn mode: '.($live ? 'LIVE' : 'TEST'));

        try {
            $configuration->assertUsable();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $appUrl = (string) config('app.url');
        if ($live && ! str_starts_with($appUrl, 'https://')) {
            $this->components->error('Live Mode ต้องตั้ง APP_URL เป็น HTTPS URL ที่เข้าถึงได้จากอินเทอร์เน็ต');

            return self::FAILURE;
        }

        $this->line('Webhook: '.rtrim($appUrl, '/').'/api/webhooks/opn');

        if ($live && config('database.default') === 'sqlite') {
            $this->components->warn('Live Mode กำลังใช้ SQLite; แนะนำ MySQL เพื่อรองรับ webhook และการกด sync พร้อมกัน');
        }

        if (! $this->option('remote')) {
            $this->components->info('Local configuration is ready. Add --remote to verify the account capability.');

            return self::SUCCESS;
        }

        try {
            $response = Http::withBasicAuth((string) config('services.opn.secret_key'), '')
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://api.omise.co/capability');
        } catch (Throwable $exception) {
            $this->components->error('ติดต่อ Opn ไม่สำเร็จ: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error('Opn ปฏิเสธคีย์หรือเรียก capability ไม่สำเร็จ (HTTP '.$response->status().')');

            return self::FAILURE;
        }

        $promptPay = collect($response->json('payment_methods', []))->firstWhere('name', 'promptpay');
        if (! $promptPay) {
            $this->components->error('บัญชีนี้ยังไม่มี PromptPay ใน capability; ติดต่อ Opn เพื่อเปิดช่องทางนี้');

            return self::FAILURE;
        }

        $currencies = collect($promptPay['currencies'] ?? [])->map(fn ($currency) => strtoupper((string) $currency));
        if (! $currencies->contains('THB')) {
            $this->components->error('PromptPay ของบัญชีนี้ยังไม่รองรับ THB');

            return self::FAILURE;
        }

        $this->components->info('Opn accepted the key and PromptPay/THB is available. No charge was created.');

        return self::SUCCESS;
    }
}
