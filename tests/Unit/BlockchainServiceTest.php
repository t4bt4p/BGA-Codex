<?php

namespace Tests\Unit;

use App\Services\BlockchainService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlockchainServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_transaction_is_replicated_and_consensus_requires_matching_nodes(): void
    {
        $service = app(BlockchainService::class);
        $service->addTransaction(['type' => 'topup_credit', 'user_id' => 10, 'cost' => 100]);

        $this->assertTrue($service->verifyChain()['success']);
        $first = Storage::get('blockchain/node_1_ledger.json');
        $this->assertSame($first, Storage::get('blockchain/node_2_ledger.json'));

        Storage::put('blockchain/node_1_ledger.json', '{}');
        $this->assertTrue($service->verifyChain()['success'], 'Two matching authority nodes still form a majority.');

        Storage::put('blockchain/node_2_ledger.json', '[]');
        $this->assertFalse($service->verifyChain()['success']);
        $this->assertSame('{}', Storage::get('blockchain/node_1_ledger.json'), 'Verification must not repair or overwrite data.');
    }

    public function test_balance_supports_topups_and_rental_debits_without_bg_id_errors(): void
    {
        $service = app(BlockchainService::class);
        $service->addTransaction(['type' => 'topup_credit', 'user_id' => 3, 'cost' => 100]);
        $service->addTransaction(['type' => 'rental_debit', 'user_id' => 3, 'bg_id' => 8, 'cost' => 25]);

        $this->assertSame(75, $service->getRealBalance(3));
    }
}
