<?php

namespace App\Services;

use App\Models\Transaction_tb;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PolygonAnchorService
{
    public function __construct(private BlockchainService $blockchain) {}

    public function anchor(Transaction_tb $transaction): array
    {
        $digest = $transaction->Chain_payload_hash ?: $this->digest($transaction);
        $result = Process::path(base_path())
            ->timeout(120)
            ->env([
                'POLYGON_RPC_URL' => config('services.polygon.rpc_url'),
                'POLYGON_PRIVATE_KEY' => $this->privateKey(),
                'POLYGON_CONTRACT_ADDRESS' => $this->contractAddress(),
                'POLYGON_CHAIN_ID' => (string) config('services.polygon.chain_id'),
                'POLYGON_CONFIRMATIONS' => (string) config('services.polygon.confirmations'),
                'POLYGON_PRIORITY_FEE_GWEI' => (string) config('services.polygon.priority_fee_gwei'),
                'POLYGON_MAX_FEE_GWEI' => (string) config('services.polygon.max_fee_gwei'),
            ])
            ->run(['node', 'scripts/polygon-anchor.mjs', $digest, (string) $transaction->Ts_id]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Polygon anchor process failed');
        }

        $data = json_decode(trim($result->output()), true);
        if (! is_array($data) || empty($data['transaction_hash'])) {
            throw new RuntimeException('Polygon anchor returned an invalid response');
        }

        return ['payload_hash' => $digest] + $data;
    }

    public function privateKey(): string
    {
        if ($privateKey = config('services.polygon.private_key')) {
            return $privateKey;
        }

        $path = config('services.polygon.encrypted_wallet_path');
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Polygon backend wallet has not been created');
        }

        return Crypt::decryptString(Storage::disk('local')->get($path));
    }

    public function contractAddress(): string
    {
        if ($address = config('services.polygon.contract_address')) {
            return $address;
        }

        $path = config('services.polygon.contract_info_path');
        if (Storage::disk('local')->exists($path)) {
            $deployment = json_decode(Storage::disk('local')->get($path), true);
            if (! empty($deployment['contract_address'])) {
                return $deployment['contract_address'];
            }
        }

        throw new RuntimeException('BGAAnchor contract has not been deployed');
    }

    public function digest(Transaction_tb $transaction): string
    {
        return '0x'.$this->blockchain->blockForTransaction((int) $transaction->Ts_id)['hash'];
    }
}
