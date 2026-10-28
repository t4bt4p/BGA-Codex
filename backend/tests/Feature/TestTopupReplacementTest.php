<?php

namespace Tests\Feature;

use App\Models\Topup_request_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\TestTopupReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TestTopupReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $payer;
    private array $charges = [];
    private string $cancelResult = 'failed';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.opn.secret_key' => 'skey_test_replace', 'services.opn.live_mode' => false,
            'services.opn.replace_pending_test_charges' => true]);
        $wallet = Wallet_tb::create(['Wallet_count' => 0]);
        $this->payer = User::create(['User_username' => 'qr-replacement', 'User_name' => 'QR replacement',
            'User_password' => bcrypt('test-password'), 'User_role' => 'user', 'User_status' => 1,
            'Wallet_id' => $wallet->Wallet_id]);
        Sanctum::actingAs($this->payer);
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.omise.co/charges') {
                $id = 'chrg_test_replace_'.(count($this->charges) + 1);
                $this->charges[$id] = ['id' => $id, 'status' => 'pending', 'paid' => false,
                    'livemode' => false, 'amount' => $request['amount'], 'currency' => 'THB',
                    'metadata' => ['reference' => $request['metadata[reference]']]];
                return Http::response($this->charges[$id]);
            }
            if (str_ends_with($request->url(), '/mark_as_failed')) {
                $id = basename(dirname($request->url()));
                $this->charges[$id]['status'] = $this->cancelResult;
                $this->charges[$id]['paid'] = $this->cancelResult === 'successful';
                return Http::response($this->charges[$id], $this->cancelResult === 'failed' ? 200 : 400);
            }
            return Http::response($this->charges[basename($request->url())]);
        });
    }

    private function create(int $amount): array
    {
        return $this->postJson('/api/topups', ['amount' => $amount])->assertCreated()->json('topup');
    }

    public function test_new_500_cancels_old_100_with_provider_and_credits_only_500_once(): void
    {
        $old = $this->create(100);
        $new = $this->create(500);
        $this->assertSame('failed', $this->charges[$old['Provider_charge_id']]['status']);
        $this->assertDatabaseHas('Topup_request_tb', ['Topup_id' => $old['Topup_id'], 'Status' => 'cancelled']);
        $this->postJson('/api/topups/'.$old['Topup_id'].'/sync')->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(0, (int) $this->payer->wallet->Wallet_count);
        $this->charges[$new['Provider_charge_id']]['status'] = 'successful';
        $this->charges[$new['Provider_charge_id']]['paid'] = true;
        $this->postJson('/api/topups/'.$new['Topup_id'].'/sync')->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/topups/'.$new['Topup_id'].'/sync')->assertOk();
        $this->assertSame(500, (int) $this->payer->wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public function test_failed_cancellation_blocks_new_charge_and_preserves_old_pending(): void
    {
        $old = $this->create(100);
        $this->cancelResult = 'pending';
        $this->postJson('/api/topups', ['amount' => 500])->assertStatus(503);
        $this->assertCount(1, $this->charges);
        $this->assertDatabaseHas('Topup_request_tb', ['Topup_id' => $old['Topup_id'], 'Status' => 'pending']);
    }

    public function test_payment_winning_cancellation_race_is_credited_and_new_charge_is_not_created(): void
    {
        $old = $this->create(100);
        $this->cancelResult = 'successful';
        $this->postJson('/api/topups', ['amount' => 500])->assertStatus(409);
        $this->assertCount(1, $this->charges);
        $this->assertDatabaseHas('Topup_request_tb', ['Topup_id' => $old['Topup_id'], 'Status' => 'approved']);
        $this->assertSame(100, (int) $this->payer->wallet->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 1);
    }

    public function test_other_users_pending_charge_is_not_cancelled(): void
    {
        $other = $this->payer->replicate();
        $other->User_username = 'other-payer';
        $other->Wallet_id = Wallet_tb::create(['Wallet_count' => 0])->Wallet_id;
        $other->save();
        $foreign = Topup_request_tb::create(['User_id' => $other->User_id,
            'Amount' => 20, 'Reference' => 'foreign', 'Provider_charge_id' => 'chrg_test_foreign', 'Status' => 'pending']);
        $this->create(100);
        $this->assertSame('pending', $foreign->fresh()->Status);
        Http::assertSentCount(1);
    }

    public function test_live_mode_never_calls_sandbox_cancellation_even_when_flag_is_enabled(): void
    {
        $this->create(100);
        config(['services.opn.live_mode' => true, 'services.opn.secret_key' => 'skey_live_example']);
        $this->assertSame('live-create', app(TestTopupReplacementService::class)->create($this->payer, fn () => 'live-create'));
        Http::assertSentCount(1);
    }

    public function test_mismatched_provider_livemode_is_rejected_before_cancellation(): void
    {
        $old = $this->create(100);
        $this->charges[$old['Provider_charge_id']]['livemode'] = true;
        $this->postJson('/api/topups', ['amount' => 500])->assertUnprocessable();
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/mark_as_failed'));
    }

    public function test_another_tab_cannot_create_while_replacement_is_in_progress(): void
    {
        $lock = Cache::lock('topup-create:'.$this->payer->User_id, 900);
        $lock->get();
        try {
            $this->postJson('/api/topups', ['amount' => 500])->assertStatus(409);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }
}
