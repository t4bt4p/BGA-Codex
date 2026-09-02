<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class CreatePolygonWallet extends Command
{
    protected $signature = 'polygon:wallet-create {--force : Replace the existing testnet wallet}';

    protected $description = 'Create an encrypted backend wallet for Polygon Amoy';

    public function handle(): int
    {
        $path = config('services.polygon.encrypted_wallet_path');
        if (Storage::disk('local')->exists($path) && ! $this->option('force')) {
            $this->error('A Polygon backend wallet already exists. Use --force only if you intend to replace it.');

            return self::FAILURE;
        }

        $result = Process::path(base_path())->timeout(30)->run(['node', 'scripts/polygon-wallet-create.mjs']);
        if (! $result->successful()) {
            $this->error(trim($result->errorOutput() ?: $result->output()));

            return self::FAILURE;
        }

        $wallet = json_decode(trim($result->output()), true);
        if (! is_array($wallet) || empty($wallet['address']) || empty($wallet['private_key'])) {
            $this->error('Wallet generator returned invalid data.');

            return self::FAILURE;
        }

        Storage::disk('local')->put($path, Crypt::encryptString($wallet['private_key']));
        Storage::disk('local')->put(config('services.polygon.wallet_address_path'), $wallet['address']);

        $this->info('Polygon Amoy backend wallet created.');
        $this->line('Public address: '.$wallet['address']);
        $this->warn('The private key is encrypted and was not printed. Back up APP_KEY securely.');

        return self::SUCCESS;
    }
}
