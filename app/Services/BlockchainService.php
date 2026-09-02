<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
                Storage::put($node, $genesis);
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
                Storage::put($node, $encoded);
            }

            return $block;
        });
    }

    /** This health check is deliberately read-only. */
    public function verifyChain(): array
    {
        $network = $this->inspectNetwork();

        return [
            'success' => $network['consensus'],
            'message' => $network['consensus']
                ? 'เครือข่ายมีโหนดเสียงข้างมากที่เก็บบัญชีธุรกรรมตรงกัน'
                : 'ไม่พบโหนดเสียงข้างมากที่มีบัญชีธุรกรรมตรงกัน ระบบหยุดรับธุรกรรมใหม่',
            'nodes' => $network['nodes'],
        ];
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
                Storage::put($node, $encoded);
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
            } elseif (in_array($tx['type'] ?? '', ['rental_debit', 'late_fee_debit', 'admin_debit', 'Deduct (Admin)', 'เช่าเกม'], true)) {
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
