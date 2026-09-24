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
        $this->assertDatabaseCount('Transaction_tb', 1);
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
        $this->assertDatabaseCount('Transaction_tb', 0);
    }

    public function test_webhook_cannot_credit_when_provider_has_not_confirmed_payment(): void
    {
        [, $wallet, $topup, $charge] = $this->payment();
        Http::fake(['api.omise.co/charges/*' => Http::response(array_replace($charge, ['paid' => false]))]);
        $this->postJson('/api/webhooks/opn', ['key' => 'charge.complete', 'data' => $charge])->assertOk();
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 0);
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
            $this->postJson("/api/topups/{$topup->Topup_id}/sync")->assertStatus(500);
        }
        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 0);
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
            ->assertStatus(500);

        $this->assertSame(20, (int) $wallet->fresh()->Wallet_count);
        $this->assertSame('pending', $topup->fresh()->Status);
        $this->assertDatabaseCount('Transaction_tb', 0);
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
