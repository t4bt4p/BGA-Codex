<?php

namespace App\Services;

use App\Models\Transaction_tb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BlockchainService
{
    private array $nodes = [
        'blockchain/node_1_ledger.json',
        'blockchain/node_2_ledger.json',
        'blockchain/node_3_ledger.json',
    ];

    public function __construct()
    {
        $this->initializeNetwork();
    }

    private function initializeNetwork(): void
    {
        $existing = collect($this->nodes)->first(fn (string $node) => Storage::exists($node));
        $genesis = $existing ? Storage::get($existing) : json_encode([$this->genesisBlock()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        foreach ($this->nodes as $node) {
            if (! Storage::exists($node)) {
                $this->writeNode($node, $genesis);
            }
        }
    }

    private function genesisBlock(): array
    {
        $timestamp = '2026-01-01T00:00:00+00:00';
        $data = 'Genesis Block - บล็อกเริ่มต้นของระบบ';

        return ['index' => 1, 'timestamp' => $timestamp, 'data' => $data, 'previous_hash' => '0', 'hash' => $this->calculateHash(1, $timestamp, $data, '0')];
    }

    public function calculateHash(int $index, string $timestamp, mixed $data, string $previousHash): string
    {
        // Keep the original serialization format so existing ledger hashes remain valid.
        return hash('sha256', $index.$timestamp.json_encode($data).$previousHash);
    }

    public function addTransaction(array $transactionData): array
    {
        $transactionData += ['bg_id' => null];

        return Cache::lock('bga-blockchain-write', 10)->block(5, function () use ($transactionData) {
            $network = $this->inspectNetwork();
            if (! $network['consensus']) {
                throw new RuntimeException('เครือข่าย Blockchain ไม่มีข้อมูลเสียงข้างมากที่ตรงกัน');
            }
            $chain = $network['canonical_chain'];
            if (isset($transactionData['transaction_id'])) {
                foreach (array_slice($chain, 1) as $existingBlock) {
                    if ((int) ($existingBlock['data']['transaction_id'] ?? 0) === (int) $transactionData['transaction_id']) {
                        if (! $this->sameTransaction($existingBlock['data'], $transactionData)) {
                            throw new RuntimeException('ข้อมูลธุรกรรมไม่ตรงกับบล็อกเดิม ไม่สามารถบันทึกทับได้');
                        }
                        return $existingBlock;
                    }
                }
            }
            $lastBlock = end($chain);
            $index = $lastBlock['index'] + 1;
            $timestamp = now()->toIso8601String();
            $block = [
                'index' => $index,
                'timestamp' => $timestamp,
                'data' => $transactionData,
                'previous_hash' => $lastBlock['hash'],
                'hash' => $this->calculateHash($index, $timestamp, $transactionData, $lastBlock['hash']),
            ];
            $chain[] = $block;
            $encoded = json_encode($chain, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            foreach ($this->nodes as $node) {
                $this->writeNode($node, $encoded);
            }

            return $block;
        });
    }

    /** This health check is deliberately read-only. */
    public function verifyChain(): array
    {
        $network = $this->inspectNetwork();
        $chain = $network['canonical_chain'] ?? [];
        $coverage = $network['consensus'] ? $this->databaseCoverage($chain) : [
            'complete' => false, 'missing_transaction_ids' => [],
            'mismatched_transaction_ids' => [], 'orphaned_transaction_ids' => [],
        ];

        return [
            'success' => $network['consensus'] && $coverage['complete'],
            'consensus' => $network['consensus'],
            'database_complete' => $coverage['complete'],
            'missing_transaction_ids' => $coverage['missing_transaction_ids'],
            'mismatched_transaction_ids' => $coverage['mismatched_transaction_ids'],
            'orphaned_transaction_ids' => $coverage['orphaned_transaction_ids'],
            'block_count' => count($chain),
            'transaction_block_count' => max(0, count($chain) - 1),
            'latest_block' => $chain ? end($chain) : null,
            'message' => ! $network['consensus']
                ? 'ไม่พบโหนดเสียงข้างมากที่มีบัญชีธุรกรรมตรงกัน ระบบหยุดรับธุรกรรมใหม่'
                : ($coverage['complete']
                    ? 'เครือข่ายถูกต้องและมีธุรกรรมครบถ้วนตรงกับฐานข้อมูล'
                    : 'พบธุรกรรมตกหล่นหรือข้อมูลธุรกรรมไม่ตรงกันระหว่างฐานข้อมูลกับบล็อกเชน'),
            'nodes' => $network['nodes'],
        ];
    }

    public function syncTransaction(Transaction_tb $transaction, array $extra = []): array
    {
        return $this->addTransaction(array_merge([
            'transaction_id' => (int) $transaction->Ts_id,
            'type' => $transaction->T_type,
            'user_id' => (int) $transaction->User_id,
            'bg_id' => $transaction->Bg_id === null ? null : (int) $transaction->Bg_id,
            'rental_id' => $transaction->Rental_id === null ? null : (int) $transaction->Rental_id,
            'cost' => (int) $transaction->T_cost,
            'timestamp' => $transaction->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ], $extra));
    }

    public function blockForTransaction(int $transactionId): array
    {
        $network = $this->inspectNetwork();
        if (! $network['consensus']) {
            throw new RuntimeException('ไม่สามารถค้นหาบล็อกได้ เพราะ Private Blockchain ไม่มี consensus');
        }

        foreach (array_slice($network['canonical_chain'], 1) as $block) {
            if ((int) ($block['data']['transaction_id'] ?? 0) === $transactionId) {
                return $block;
            }
        }

        throw new RuntimeException("ยังไม่พบบล็อกของธุรกรรม {$transactionId} ใน Private Blockchain");
    }

    public function reconcileTransactions(): array
    {
        $network = $this->inspectNetwork();
        if (! $network['consensus']) {
            throw new RuntimeException('ไม่สามารถกู้ธุรกรรมได้ เพราะไม่มี chain เสียงข้างมาก');
        }

        $coverage = $this->databaseCoverage($network['canonical_chain']);
        if ($coverage['mismatched_transaction_ids'] || $coverage['orphaned_transaction_ids']) {
            throw new RuntimeException('พบข้อมูลธุรกรรมถูกแก้ไขหรือหายไปจากฐานข้อมูล กรุณาตรวจสอบก่อนกู้ธุรกรรม');
        }
        $missingIds = $coverage['missing_transaction_ids'];
        foreach (Transaction_tb::whereIn('Ts_id', $missingIds)->orderBy('Ts_id')->get() as $transaction) {
            $this->syncTransaction($transaction, ['reconciled' => true]);
        }

        return $this->verifyChain();
    }

    /** Repairs only from a cryptographically valid majority chain. */
    public function repairNetwork(): array
    {
        return Cache::lock('bga-blockchain-write', 10)->block(5, function () {
            $network = $this->inspectNetwork();
            if (! $network['consensus']) {
                throw new RuntimeException('ไม่สามารถซ่อมได้ เพราะไม่มี chain เสียงข้างมาก');
            }
            $encoded = json_encode($network['canonical_chain'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            foreach ($this->nodes as $node) {
                $this->writeNode($node, $encoded);
            }

            return $this->verifyChain();
        });
    }

    public function getRealBalance(int $userId): int
    {
        $network = $this->inspectNetwork();
        if (! $network['consensus']) {
            throw new RuntimeException('ไม่สามารถคำนวณยอดได้ เพราะ Blockchain ไม่มี consensus');
        }
        $balance = 0;
        foreach (array_slice($network['canonical_chain'], 1) as $block) {
            $tx = $block['data'];
            if ((int) ($tx['user_id'] ?? 0) !== $userId) {
                continue;
            }
            $amount = (int) ($tx['cost'] ?? 0);
            if (in_array($tx['type'] ?? '', ['topup_credit', 'Topup (Admin)'], true)) {
                $balance += $amount;
            } elseif (in_array($tx['type'] ?? '', ['rental_debit', 'admin_debit', 'Deduct (Admin)', 'เช่าเกม'], true)) {
                $balance -= $amount;
            }
        }

        return $balance;
    }

    private function inspectNetwork(): array
    {
        $groups = [];
        $nodes = [];
        foreach ($this->nodes as $index => $node) {
            $chain = Storage::exists($node) ? json_decode(Storage::get($node), true) : null;
            $error = $this->chainError($chain);
            $fingerprint = $error === null ? hash('sha256', json_encode($chain, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null;
            if ($fingerprint) {
                $groups[$fingerprint][] = $chain;
            }
            $nodes[] = ['node' => 'Node '.($index + 1), 'status' => $error === null, 'fingerprint' => $fingerprint, 'message' => $error ?? 'โครงสร้างและ hash ถูกต้อง'];
        }
        uasort($groups, fn (array $a, array $b) => count($b) <=> count($a));
        $majority = reset($groups);
        $consensus = is_array($majority) && count($majority) >= 2;

        return ['consensus' => $consensus, 'canonical_chain' => $consensus ? $majority[0] : null, 'nodes' => $nodes];
    }

    private function databaseCoverage(array $chain): array
    {
        if (! Schema::hasTable('Transaction_tb')) {
            return ['complete' => true, 'missing_transaction_ids' => [], 'mismatched_transaction_ids' => [], 'orphaned_transaction_ids' => []];
        }
        $ids = [];
        $legacySignatures = [];
        foreach (array_slice($chain, 1) as $block) {
            $data = $block['data'];
            if (isset($data['transaction_id'])) {
                $ids[(int) $data['transaction_id']] = $data;
            } else {
                $signature = $this->transactionSignature($data);
                $legacySignatures[$signature] = ($legacySignatures[$signature] ?? 0) + 1;
            }
        }

        $missing = [];
        $mismatched = [];
        $seen = [];
        foreach (Transaction_tb::orderBy('Ts_id')->get(['Ts_id', 'User_id', 'Bg_id', 'Rental_id', 'T_cost', 'T_type']) as $transaction) {
            $id = (int) $transaction->Ts_id;
            $seen[$id] = true;
            $data = [
                'type' => $transaction->T_type, 'user_id' => $transaction->User_id,
                'bg_id' => $transaction->Bg_id, 'cost' => $transaction->T_cost,
                'rental_id' => $transaction->Rental_id,
            ];
            if (isset($ids[(int) $transaction->Ts_id])) {
                if (! $this->sameTransaction($ids[$id], $data)) {
                    $mismatched[] = $id;
                }
                continue;
            }
            $signature = $this->transactionSignature([
                'type' => $transaction->T_type,
                'user_id' => $transaction->User_id,
                'bg_id' => $transaction->Bg_id,
                'cost' => $transaction->T_cost,
            ]);
            if (($legacySignatures[$signature] ?? 0) > 0) {
                $legacySignatures[$signature]--;
                continue;
            }
            $missing[] = (int) $transaction->Ts_id;
        }

        $orphaned = array_values(array_diff(array_keys($ids), array_keys($seen)));

        return [
            'complete' => $missing === [] && $mismatched === [] && $orphaned === [],
            'missing_transaction_ids' => $missing,
            'mismatched_transaction_ids' => $mismatched,
            'orphaned_transaction_ids' => $orphaned,
        ];
    }

    private function sameTransaction(array $stored, array $current): bool
    {
        return $this->transactionSignature($stored) === $this->transactionSignature($current)
            && (! array_key_exists('rental_id', $stored)
                || ($stored['rental_id'] === null ? null : (int) $stored['rental_id'])
                    === (isset($current['rental_id']) ? (int) $current['rental_id'] : null));
    }

    private function transactionSignature(array $data): string
    {
        return implode('|', [
            (string) ($data['type'] ?? ''),
            (int) ($data['user_id'] ?? 0),
            ! isset($data['bg_id']) ? 'null' : (int) $data['bg_id'],
            (int) ($data['cost'] ?? 0),
        ]);
    }

    private function writeNode(string $node, string $contents): void
    {
        if (! Storage::put($node, $contents)) {
            throw new RuntimeException("ไม่สามารถเขียนข้อมูล Private Blockchain ไปยัง {$node} ได้ กรุณาตรวจสอบสิทธิ์ของ storage");
        }
    }

    private function chainError(mixed $chain): ?string
    {
        if (! is_array($chain) || $chain === []) {
            return 'ไม่พบ chain หรือรูปแบบ JSON ไม่ถูกต้อง';
        }
        foreach ($chain as $offset => $block) {
            if (! is_array($block) || ! isset($block['index'], $block['timestamp'], $block['data'], $block['previous_hash'], $block['hash'])) {
                return 'โครงสร้างบล็อกไม่ครบถ้วน';
            }
            $expectedPrevious = $offset === 0 ? '0' : $chain[$offset - 1]['hash'];
            if ($block['previous_hash'] !== $expectedPrevious) {
                return "previous hash ไม่ตรงกันที่บล็อก {$block['index']}";
            }
            $hash = $this->calculateHash((int) $block['index'], (string) $block['timestamp'], $block['data'], (string) $block['previous_hash']);
            if (! hash_equals($hash, (string) $block['hash'])) {
                return "hash ไม่ถูกต้องที่บล็อก {$block['index']}";
            }
        }

        return null;
    }
}
