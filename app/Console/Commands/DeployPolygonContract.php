<?php

namespace App\Console\Commands;

use App\Services\PolygonAnchorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DeployPolygonContract extends Command
{
    protected $signature = 'polygon:deploy {--force : Deploy a new contract even if one is already stored}';

    protected $description = 'Compile and deploy BGAAnchor to Polygon Amoy';

    public function handle(PolygonAnchorService $polygon): int
    {
        $path = config('services.polygon.contract_info_path');
        if (Storage::disk('local')->exists($path) && ! $this->option('force')) {
            $this->error('A Polygon contract deployment is already stored.');

            return self::FAILURE;
        }

        $this->info('Deploying BGAAnchor to Polygon Amoy...');
        $result = Process::path(base_path())
            ->timeout(180)
            ->env([
                'POLYGON_RPC_URL' => config('services.polygon.rpc_url'),
                'POLYGON_PRIVATE_KEY' => $polygon->privateKey(),
                'POLYGON_CHAIN_ID' => (string) config('services.polygon.chain_id'),
                'POLYGON_PRIORITY_FEE_GWEI' => (string) config('services.polygon.priority_fee_gwei'),
                'POLYGON_MAX_FEE_GWEI' => (string) config('services.polygon.max_fee_gwei'),
            ])
            ->run(['node', 'scripts/polygon-deploy.mjs']);

        if (! $result->successful()) {
            $this->error(trim($result->errorOutput() ?: $result->output()));

            return self::FAILURE;
        }

        $deployment = json_decode(trim($result->output()), true);
        if (! is_array($deployment) || empty($deployment['contract_address'])) {
            $this->error('Deployment returned invalid data.');

            return self::FAILURE;
        }

        Storage::disk('local')->put($path, json_encode($deployment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('BGAAnchor deployed successfully.');
        $this->line('Contract: '.$deployment['contract_address']);
        $this->line('Transaction: '.$deployment['transaction_hash']);
        $this->line(rtrim(config('services.polygon.explorer_url'), '/').'/tx/'.$deployment['transaction_hash']);

        return self::SUCCESS;
    }
}
