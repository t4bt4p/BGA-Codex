<?php

namespace Tests\Feature;

use App\Models\Topup_request_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopupPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        \Illuminate\Support\Facades\Storage::fake("local");
        Http::preventStrayRequests();
        config([
            'services.opn.secret_key' => 'skey_test_example',
            'services.opn.live_mode' => false,
        ]);
    }

    private function payment(): array
    {
        $wallet = Wallet_tb::create(['Wallet_count' => 20]);
        $user = User::create([
            'User_username' => 'payer', 'User_password' => Hash::make('secret12'),
            'User_name' => 'Payer', 'User_role' => 'user', 'User_status' => 1,
            'Wallet_id' => $wallet->Wallet_id,
        ]);
        $opening = \App\Models\Transaction_tb::create(['User_id' => $user->User_id, 'T_type' => 'topup_credit', 'T_cost' => 20]);
        app(\App\Services\BlockchainService::class)->syncTransaction($opening);
        $topup = Topup_request_tb::create([
            'User_id' => $user->User_id, 'Amount' => 100, 'Reference' => 'REF100',
            'Provider_charge_id' => 'chrg_test100', 'Status' => 'pending',
        ]);
        $charge = [
            'id' => 'chrg_test100', 'status' => 'successful', 'paid' => true,
            'livemode' => false,
            'amount' => 10000, 'currency' => 'THB', 'metadata' => ['reference' => 'REF100'],
        ];

        return [$user, $wallet, $topup, $charge];
    }

    public function test_paid_webhook_and_repeated_sync_credit_wallet_only_once(): void
    {
        [$user, $wallet, $topup, $charge] = $this->payment();
        Http::fake(['api.omise.co/charges/*' => Http::response($charge)]);
        $event = ['key' => 'charge.complete', 'data' => $charge];
        $this->postJson('/api/webhooks/opn', $event)->assertOk();
        $this->postJson('/api/webhooks/opn', $event)->assertOk();
        Sanctum::actingAs($user);
        $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame(120, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 2);
        $this->assertDatabaseHas('Transaction_tb', ['User_id' => $user->User_id, 'T_cost' => 100, 'T_type' => 'topup_credit']);
        $this->assertNotNull($topup->fresh()->approved_at);
        Http::assertSentCount(1);
    }

    public function test_creating_promptpay_qr_sets_the_amount_but_does_not_credit_wallet(): void
    {
        [$user, $wallet] = $this->payment();
        Sanctum::actingAs($user);
        Http::fake(['api.omise.co/charges' => Http::response([
            'id' => 'chrg_new', 'status' => 'pending', 'paid' => false,
            'livemode' => false,
            'source' => ['scannable_code' => ['image' => ['download_uri' => 'https://qr.example.test/image']]],
        ]), 'qr.example.test/image' => Http::response('qr-image', 200, ['Content-Type' => 'image/png'])]);
        $this->postJson('/api/topups', ['amount' => 50])->assertCreated()
            ->assertJsonPath('topup.Provider_charge_id', 'chrg_new')
            ->assertJsonPath('charge.qr_data', 'data:image/png;base64,'.base64_encode('qr-image'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.omise.co/charges'
            && $request['amount'] === 5000 && $request['currency'] === 'THB'
            && $request['source[type]'] === 'promptpay' && ! empty($request['metadata[reference]']));
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public static function changedAmounts(): array
    {
        return [[20, 50], [100, 500]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('changedAmounts')]
    public function test_paying_new_amount_after_leaving_old_qr_unpaid_credits_only_the_paid_charge_once(int $oldAmount, int $newAmount): void
    {
        [$user, $wallet] = $this->payment();
        Sanctum::actingAs($user);
        Http::fake(function ($request) use ($newAmount) {
            if ($request->url() === 'https://api.omise.co/charges') {
                return Http::response([
                    'id' => 'chrg_switch_'.$request['amount'], 'livemode' => false,
                    'status' => 'pending', 'paid' => false,
                ]);
            }
            $topup = Topup_request_tb::where('Provider_charge_id', basename($request->url()))->firstOrFail();
            $paid = (int) $topup->Amount === $newAmount;

            return Http::response([
                'id' => $topup->Provider_charge_id, 'livemode' => false,
                'status' => $paid ? 'successful' : 'pending', 'paid' => $paid,
                'amount' => (int) $topup->Amount * 100, 'currency' => 'THB',
                'metadata' => ['reference' => $topup->Reference],
            ]);
        });
        $unpaid = $this->postJson('/api/topups', ['amount' => $oldAmount])->assertCreated()->json('topup.Topup_id');
        $paid = $this->postJson('/api/topups', ['amount' => $newAmount])->assertCreated()->json('topup.Topup_id');
        $this->postJson('/api/topups/'.$unpaid.'/sync')->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson('/api/topups/'.$paid.'/sync')->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/topups/'.$paid.'/sync')->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame(20 + $newAmount, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 2);
        $this->assertDatabaseHas('Topup_request_tb', ['Topup_id' => $unpaid, 'Status' => 'pending']);
    }

    public function test_background_sync_settles_paid_charge_without_browser_and_retries_provider_failure(): void
    {
        [$user, $wallet, $topup, $charge] = $this->payment();
        $unpaid = Topup_request_tb::create([
            'User_id' => $user->User_id, 'Amount' => 20, 'Reference' => 'UNPAID20',
            'Provider_charge_id' => 'chrg_unpaid20', 'Status' => 'pending',
        ]);
        $providerUnavailable = true;
        Http::fake(function ($request) use ($charge, &$providerUnavailable) {
            if (str_ends_with($request->url(), 'chrg_unpaid20')) {
                if ($providerUnavailable) {
                    throw new ConnectionException('Provider unavailable');
                }

                return Http::response(['id' => 'chrg_unpaid20', 'livemode' => false, 'status' => 'pending', 'paid' => false]);
            }

            return Http::response($charge);
        });
        $this->artisan('topups:sync-pending')->assertExitCode(1);
        $this->assertSame('approved', $topup->fresh()->Status);
        $this->assertSame('pending', $unpaid->fresh()->Status);
        $this->assertSame(120, (int) $wallet->fresh()->Wallet_count);
        $providerUnavailable = false;
        $this->artisan('topups:sync-pending')->assertSuccessful();
        $this->assertSame(120, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 2);
    }

    public function test_sync_timeout_returns_retryable_response_and_preserves_payment(): void
    {
        [$user, $wallet, $topup] = $this->payment();
        Sanctum::actingAs($user);
        Http::fake(fn () => throw new ConnectionException('Provider timeout'));
        $this->postJson('/api/topups/'.$topup->Topup_id.'/sync')
            ->assertStatus(503)->assertJsonPath('code', 'payment_verification_delayed');
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
    }

    public function test_webhook_cannot_credit_when_provider_has_not_confirmed_payment(): void
    {
        [, $wallet, $topup, $charge] = $this->payment();
        Http::fake(['api.omise.co/charges/*' => Http::response(array_replace($charge, ['paid' => false]))]);
        $this->postJson('/api/webhooks/opn', ['key' => 'charge.complete', 'data' => $charge])->assertOk();
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public function test_wrong_amount_currency_or_reference_never_credits_tokens(): void
    {
        [$user, $wallet, $topup, $charge] = $this->payment();
        Sanctum::actingAs($user);
        Http::fake(['api.omise.co/charges/*' => Http::sequence()
            ->push(array_replace($charge, ['amount' => 5000]))
            ->push(array_replace($charge, ['currency' => 'USD']))
            ->push(array_replace($charge, ['metadata' => ['reference' => 'WRONG']]))]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertUnprocessable()->assertJsonValidationErrors('payment');
        }
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public function test_suspended_user_still_receives_a_payment_already_made(): void
    {
        [$user, $wallet, , $charge] = $this->payment();
        $user->update(['User_status' => 0]);
        Http::fake(['api.omise.co/charges/*' => Http::response($charge)]);
        $this->postJson('/api/webhooks/opn', ['key' => 'charge.complete', 'data' => $charge])->assertOk();
        $this->assertSame(120, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame(0, (int) $user->fresh()->User_status);
    }

    public function test_test_charge_is_rejected_when_live_mode_is_enabled(): void
    {
        [, $wallet, $topup, $charge] = $this->payment();
        config(['services.opn.live_mode' => true]);
        Http::fake(['api.omise.co/charges/*' => Http::response($charge)]);

        $this->postJson('/api/webhooks/opn', ['key' => 'charge.complete', 'data' => $charge])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public function test_qr_provider_timeout_falls_back_to_the_signed_image_url(): void
    {
        [$user] = $this->payment();
        Sanctum::actingAs($user);
        $downloadUri = 'https://qr.example.test/slow-image';
        Http::fake(function ($request) use ($downloadUri) {
            if ($request->url() === 'https://api.omise.co/charges') {
                return Http::response([
                    'id' => 'chrg_slow_qr',
                    'status' => 'pending',
                    'paid' => false,
                    'livemode' => false,
                    'source' => ['scannable_code' => ['image' => ['download_uri' => $downloadUri]]],
                ]);
            }

            throw new ConnectionException('QR download timed out');
        });

        $this->postJson('/api/topups', ['amount' => 20])
            ->assertCreated()
            ->assertJsonPath('charge.qr_data', $downloadUri);
    }
}
