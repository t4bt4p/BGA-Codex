<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRecoveryService
{
    public function __construct(private BlockchainService $blockchain) {}

    public function restore(bool $dryRun = false): array
    {
        return Cache::lock('bga-blockchain-write', 30)->block(5, function () use ($dryRun) {
            $chain = $this->blockchain->canonicalChainForRecovery();
            $expected = [];
            $legacy = 0;
            foreach (array_slice($chain, 1) as $block) {
                $data = $block['data'];
                if (! is_array($data) || ! isset($data['transaction_id'])) {
                    $legacy++;
                    continue;
                }
                $id = $data['transaction_id'];
                abort_unless(is_int($id) && $id > 0 && ! isset($expected[$id]), 409,
                    'เลขธุรกรรมในหลักฐานซ้ำหรือไม่ถูกต้อง ไม่สามารถกู้ข้อมูลอัตโนมัติได้');
                foreach (['user_id', 'bg_id', 'rental_id', 'cost', 'type', 'timestamp'] as $key) {
                    abort_unless(array_key_exists($key, $data), 409, 'ข้อมูลในหลักฐานไม่ครบ ไม่สามารถกู้ข้อมูลอัตโนมัติได้');
                }
                abort_unless(is_int($data['user_id']) && $data['user_id'] > 0
                    && is_int($data['cost']) && $data['cost'] >= 0
                    && ($data['bg_id'] === null || (is_int($data['bg_id']) && $data['bg_id'] > 0))
                    && ($data['rental_id'] === null || (is_int($data['rental_id']) && $data['rental_id'] > 0))
                    && is_string($data['type']) && $data['type'] !== ''
                    && is_string($data['timestamp']), 409, 'รูปแบบข้อมูลในหลักฐานไม่ถูกต้อง');
                $expected[$id] = [
                    'User_id' => $data['user_id'], 'Bg_id' => $data['bg_id'],
                    'Rental_id' => $data['rental_id'], 'T_cost' => $data['cost'], 'T_type' => $data['type'],
                    'created_at' => CarbonImmutable::parse($data['timestamp'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    'Chain_payload_hash' => '0x'.$block['hash'],
                ];
            }

            $result = DB::transaction(function () use ($expected, $legacy, $dryRun) {
                $rows = DB::table('Transaction_tb')->orderBy('Ts_id')->lockForUpdate()->get()->keyBy('Ts_id');
                $changes = [];
                $missing = [];
                foreach ($expected as $id => $fields) {
                    $row = $rows->get($id);
                    if (! $row) {
                        // A deleted row and an edited primary key are ambiguous. Never guess an alias.
                        $missing[] = $id;
                        continue;
                    }
                    $patch = [];
                    $before = [];
                    foreach ($fields as $field => $value) {
                        // A hash can legitimately be absent before the Polygon worker starts.
                        if ($field === 'Chain_payload_hash' && $row->$field === null && $row->Chain_status !== 'confirmed') {
                            continue;
                        }
                        if ($row->$field === null ? $value !== null : ($value === null || (string) $row->$field !== (string) $value)) {
                            $patch[$field] = $value;
                            $before[$field] = $row->$field;
                        }
                    }
                    if ($patch) {
                        $changes[$id] = ['before' => $before, 'after' => $patch];
                    }
                }
                if (! $dryRun) {
                    // Clear changed unique hashes first, including when two stored hashes were swapped.
                    foreach ($changes as $id => $change) {
                        if (array_key_exists('Chain_payload_hash', $change['after'])) {
                            DB::table('Transaction_tb')->where('Ts_id', $id)->update(['Chain_payload_hash' => null]);
                        }
                    }
                    foreach ($changes as $id => $change) {
                        // No model events, new ledger blocks, or Polygon broadcasts for a restoration.
                        DB::table('Transaction_tb')->where('Ts_id', $id)->update($change['after']);
                    }
                }

                return ['changes' => $changes, 'missing_transaction_ids' => $missing, 'legacy_blocks_skipped' => $legacy];
            });
            if (! $dryRun && $result['changes']) {
                Log::warning('Transaction records restored from validated majority ledger', $result);
            }
            if (! $dryRun && $result['missing_transaction_ids']) {
                Log::warning('Transaction recovery needs review: original IDs are missing', [
                    'transaction_ids' => $result['missing_transaction_ids'],
                ]);
            }

            return $result;
        });
    }
}
