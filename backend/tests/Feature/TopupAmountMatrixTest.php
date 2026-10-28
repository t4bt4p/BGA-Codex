<?php

namespace Tests\Feature;

use App\Jobs\RecordPrivateBlockchainTransaction;
use App\Models\Topup_request_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopupAmountMatrixTest extends TestCase
{
    use RefreshDatabase;

    public static function allowedAmounts(): array
    {
        return ['20' => [20], '50' => [50], '100' => [100], '500' => [500], '1000' => [1000]];
    }

    #[DataProvider('allowedAmounts')]
    public function test_all_allowed_amounts_create_qr_and_credit_once_after_payment(int $amount): void
    {
        Queue::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.opn.secret_key' => 'skey_test_amount_matrix', 'services.opn.live_mode' => false]);
        $wallet = Wallet_tb::create(['Wallet_count' => 0]);
        $user = User::create(['User_username' => 'amount-matrix', 'User_name' => 'Amount Matrix', 'User_password' => Hash::make('SrsMatrix12!'), 'User_role' => 'user', 'User_status' => 1, 'Wallet_id' => $wallet->Wallet_id]);
        Sanctum::actingAs($user);
        Http::fake(function ($request) use ($amount) {
            if ($request->method() === 'POST') {
                $this->assertSame($amount * 100, $request['amount']);
                $this->assertSame('THB', $request['currency']);
                $this->assertSame('promptpay', $request['source[type]']);

                return Http::response(['id' => 'chrg_amount_'.$amount, 'livemode' => false, 'status' => 'pending', 'paid' => false, 'source' => ['scannable_code' => ['image' => ['download_uri' => 'https://matrix.test/qr']]]]);
            }
            if ($request->url() === 'https://matrix.test/qr') {
                return Http::response('qr', 200, ['Content-Type' => 'image/png']);
            }
            $topup = Topup_request_tb::firstOrFail();

            return Http::response(['id' => 'chrg_amount_'.$amount, 'livemode' => false, 'status' => 'successful', 'paid' => true, 'amount' => $amount * 100, 'currency' => 'THB', 'metadata' => ['reference' => $topup->Reference]]);
        });
        $created = $this->postJson('/api/topups', ['amount' => $amount])->assertCreated();
        $this->assertNotEmpty($created->json('charge.qr_data'));
        $this->assertSame(0, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 0);
        Queue::assertNothingPushed();
        $id = $created->json('topup.Topup_id');
        $this->postJson('/api/topups/'.$id.'/sync')->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/topups/'.$id.'/sync')->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame($amount, (int) $wallet->fresh()->Wallet_count);
        $this->assertDatabaseCount('Transaction_tb', 1);
        Queue::assertPushed(RecordPrivateBlockchainTransaction::class, 1);
        $this->assertTrue(Queue::pushed(RecordPrivateBlockchainTransaction::class)->first()->afterCommit);
    }
}
