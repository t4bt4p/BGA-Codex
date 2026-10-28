<?php

namespace Tests\Feature;

use App\Jobs\AnchorTransactionOnPolygon;
use App\Models\Transaction_tb;
use App\Models\User;
use App\Models\Wallet_tb;
use App\Services\BlockchainService;
use App\Services\PolygonAnchorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tests\TestCase;

class SecretsAndMessagesTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->key = '0x'.str_repeat('b', 64);
        $this->logPath = Storage::disk('local')->path('security-test.log');
        config(['services.polygon.private_key' => $this->key, 'logging.default' => 'single', 'logging.channels.single.path' => $this->logPath]);
        Log::swap(new \Illuminate\Log\Logger(new Logger('srs-security', [new StreamHandler($this->logPath)])));
    }

    public function test_registration_and_login_store_only_password_and_token_hashes(): void
    {
        $registration = $this->postJson('/api/register', [
            'User_name' => 'Security Test', 'username' => 'security-test',
            'User_phone' => '0812345678', 'password' => 'SecretPassword123!',
        ])->assertCreated()->assertJsonMissingPath('user.User_password');
        $user = User::firstOrFail();
        $this->assertNotSame('SecretPassword123!', $user->User_password);
        $this->assertTrue(Hash::check('SecretPassword123!', $user->User_password));
        $login = $this->postJson('/api/login', ['username' => 'security-test', 'password' => 'SecretPassword123!'])
            ->assertOk()->assertJsonMissingPath('user.User_password');
        foreach ([$registration->json('token'), $login->json('token')] as $token) {
            [$id, $plain] = explode('|', $token, 2);
            $stored = $user->tokens()->findOrFail($id)->token;
            $this->assertSame(hash('sha256', $plain), $stored);
            $this->assertNotSame($plain, $stored);
        }
        Sanctum::actingAs($user);
        foreach (['/api/user', '/api/transactions', '/api/rentals/active'] as $url) {
            $response = $this->getJson($url)->assertOk();
            $this->assertStringNotContainsString($this->key, $response->getContent());
            $this->assertStringNotContainsString('SecretPassword123!', $response->getContent());
            $this->assertStringNotContainsString('User_password', $response->getContent());
        }
    }

    public function test_wallet_file_is_laravel_crypt_encrypted_and_key_is_not_printed(): void
    {
        config(['services.polygon.private_key' => null]);
        Process::fake(['*' => Process::result(output: json_encode(['address' => '0x'.str_repeat('1', 40), 'private_key' => $this->key]))]);
        $this->artisan('polygon:wallet-create')->expectsOutputToContain('Public address:')->assertSuccessful();
        $file = Storage::disk('local')->get(config('services.polygon.encrypted_wallet_path'));
        $this->assertStringNotContainsString($this->key, $file);
        $this->assertSame($this->key, Crypt::decryptString($file));
        $this->assertSame($this->key, app(PolygonAnchorService::class)->privateKey());
        $this->assertStringNotContainsString($this->key, $this->artisanOutput());
        config(['services.polygon.private_key' => 'environment-key-test']);
        $this->assertSame('environment-key-test', app(PolygonAnchorService::class)->privateKey());
    }

    private function artisanOutput(): string
    {
        return Artisan::output();
    }

    public function test_polygon_failure_does_not_persist_or_expose_private_key(): void
    {
        $wallet = Wallet_tb::create(['Wallet_count' => 0]);
        $user = User::create(['User_username' => 'chain-security', 'User_name' => 'Chain Test', 'User_password' => Hash::make('SecretPassword123!'), 'User_role' => 'user', 'User_status' => 1, 'Wallet_id' => $wallet->Wallet_id]);
        $tx = Transaction_tb::create(['User_id' => $user->User_id, 'T_type' => 'topup_credit', 'T_cost' => 20]);
        app(BlockchainService::class)->syncTransaction($tx);
        config(['services.polygon.contract_address' => '0x'.str_repeat('1', 40)]);
        Process::fake(['*' => Process::result(errorOutput: 'RPC failure: private key '.$this->key, exitCode: 1)]);
        try {
            (new AnchorTransactionOnPolygon($tx->Ts_id))->handle(app(PolygonAnchorService::class));
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) {
            $this->assertStringNotContainsString($this->key, $error->getMessage());
        }
        $this->assertSame('failed', $tx->fresh()->Chain_status);
        $this->assertStringNotContainsString($this->key, $tx->fresh()->Chain_error);
        Sanctum::actingAs($user);
        $this->assertStringNotContainsString($this->key, $this->getJson('/api/transactions')->assertOk()->getContent());
    }

    public function test_api_errors_have_understandable_messages_without_traces_or_secrets_even_in_debug(): void
    {
        config(['app.debug' => true]);
        Route::post('/api/srs-security-error', fn () => throw new \RuntimeException('Failed with private key '.$this->key));
        Route::post('/api/srs-security-conflict', fn () => abort(409, 'บอร์ดเกมนี้ไม่ว่าง'));
        $error = $this->postJson('/api/srs-security-error')->assertStatus(500);
        $this->assertNotEmpty($error->json('message'));
        foreach (['trace', 'exception', 'file', 'line'] as $field) {
            $error->assertJsonMissingPath($field);
        }
        $this->assertStringNotContainsString($this->key, $error->getContent());
        $conflict = $this->postJson('/api/srs-security-conflict')->assertStatus(409)->assertJsonPath('message', 'บอร์ดเกมนี้ไม่ว่าง');
        $conflict->assertJsonMissingPath('trace');
        $this->assertFileExists($this->logPath);
        $this->assertStringNotContainsString($this->key, file_get_contents($this->logPath));
    }
}
