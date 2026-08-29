<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction_tb;
use App\Models\User; // 📌 เพิ่ม Model User เข้ามาเพื่อจัดการกระเป๋าเงิน
use Carbon\Carbon;

class BlockchainService
{
    private $nodes = [
        'blockchain/node_1_ledger.json',
        'blockchain/node_2_ledger.json',
        'blockchain/node_3_ledger.json'
    ];

    public function __construct()
    {
        $this->initializeNetwork();
    }

    private function initializeNetwork()
    {
        foreach ($this->nodes as $node) {
            if (!Storage::exists($node)) {
                $timestamp = now()->toIso8601String();
                $data = 'Genesis Block - บล็อกเริ่มต้นของระบบ';
                
                $genesisBlock = [
                    [
                        'index' => 1,
                        'timestamp' => $timestamp,
                        'data' => $data,
                        'previous_hash' => '0', 
                        'hash' => $this->calculateHash(1, $timestamp, $data, '0')
                    ]
                ];
                
                Storage::put($node, json_encode($genesisBlock, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    public function calculateHash($index, $timestamp, $data, $previousHash)
    {
        $stringToHash = $index . $timestamp . json_encode($data) . $previousHash;
        return hash('sha256', $stringToHash);
    }

    public function addTransaction($transactionData)
    {
        return Cache::lock('bga-blockchain-write', 10)->block(5, function () use ($transactionData) {
            $chain = json_decode(Storage::get($this->nodes[0]), true);
            $lastBlock = end($chain);
            $newIndex = $lastBlock['index'] + 1;
            $newTimestamp = now()->toIso8601String();
            $newPreviousHash = $lastBlock['hash'];
            $newHash = $this->calculateHash($newIndex, $newTimestamp, $transactionData, $newPreviousHash);
            $newBlock = ['index'=>$newIndex,'timestamp'=>$newTimestamp,'data'=>$transactionData,'previous_hash'=>$newPreviousHash,'hash'=>$newHash];
            $chain[] = $newBlock;
            $jsonChain = json_encode($chain, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            foreach ($this->nodes as $node) Storage::put($node, $jsonChain);
            return $newBlock;
        });
    }

    public function verifyChain()
    {
        $results = [];
        $validNodes = []; 
        $invalidNodes = []; 

        foreach ($this->nodes as $index => $node) {
            $nodeName = "Node " . ($index + 1);
            
            if (!Storage::exists($node)) {
                $invalidNodes[] = $node;
                $results[] = ['node' => $nodeName, 'status' => false, 'message' => 'ไม่พบไฟล์ของโหนดนี้'];
                continue; 
            }

            $chain = json_decode(Storage::get($node), true);
            $isValid = true;
            $errorMessage = 'ข้อมูลถูกต้องสมบูรณ์';

            for ($i = 1; $i < count($chain); $i++) {
                $currentBlock = $chain[$i];
                $previousBlock = $chain[$i - 1];

                if ($currentBlock['previous_hash'] !== $previousBlock['hash']) {
                    $isValid = false;
                    $errorMessage = "โซ่ขาดที่บล็อก {$currentBlock['index']}: รหัสเชื่อมต่อไม่ตรงกัน";
                    break;
                }

                $calculatedHash = $this->calculateHash($currentBlock['index'], $currentBlock['timestamp'], $currentBlock['data'], $currentBlock['previous_hash']);
                
                if ($currentBlock['hash'] !== $calculatedHash) {
                    $isValid = false;
                    $errorMessage = "ข้อมูลถูกดัดแปลงที่บล็อก {$currentBlock['index']}: รหัส Hash ไม่ถูกต้อง";
                    break;
                }
            }

            if ($isValid) {
                $validNodes[] = $node; 
            } else {
                $invalidNodes[] = $node; 
            }

            $results[] = [
                'node' => $nodeName,
                'status' => $isValid,
                'message' => $errorMessage
            ];
        }

        $isConsensusReached = count($validNodes) >= 2;
        $healedMessage = "";

        if ($isConsensusReached) {
            if (count($invalidNodes) > 0) {
                $masterData = Storage::get($validNodes[0]);
                foreach ($invalidNodes as $invalidNode) {
                    Storage::put($invalidNode, $masterData);
                }
                $healedMessage .= "<br><span class='text-success fw-bold'><i class='fa-solid fa-wrench'></i> ซ่อมแซมโหนด Blockchain ที่เสียหายเรียบร้อยแล้ว</span>";
            }

            $masterChain = json_decode(Storage::get($validNodes[0]), true);
            $dbTransactions = Transaction_tb::orderBy('Ts_id', 'asc')->get();
            $dbIsCorrupted = false;

            if (count($masterChain) - 1 !== count($dbTransactions)) {
                $dbIsCorrupted = true;
            } else {
                foreach ($dbTransactions as $key => $dbTx) {
                    $chainTx = $masterChain[$key + 1]['data'];
                    
                    if (
                        $dbTx->User_id != $chainTx['user_id'] ||
                        $dbTx->Bg_id != $chainTx['bg_id'] ||
                        $dbTx->T_cost != $chainTx['cost'] ||
                        $dbTx->T_type != $chainTx['type']
                    ) {
                        $dbIsCorrupted = true;
                        break;
                    }
                }
            }

            // 🚨 หากพบว่า Database โดนแฮ็ก
            if ($dbIsCorrupted) {
                Transaction_tb::truncate(); 
                $userIdsToUpdate = []; // เก็บ ID ผู้ใช้ที่ต้องปรับยอดเงินใหม่

                for ($i = 1; $i < count($masterChain); $i++) {
                    $txData = $masterChain[$i]['data'];
                    Transaction_tb::create([
                        'User_id' => $txData['user_id'],
                        'Bg_id' => $txData['bg_id'],
                        'T_cost' => $txData['cost'],
                        'T_type' => $txData['type'],
                        'created_at' => Carbon::parse($txData['timestamp']),
                        'updated_at' => Carbon::parse($txData['timestamp'])
                    ]);

                    // บันทึก ID ผู้ใช้งานลง Array เพื่อนำไปรีเฟรชกระเป๋าเงิน
                    $userIdsToUpdate[$txData['user_id']] = true;
                }

                // 🔄 อัปเดตกระเป๋าเงิน (Wallet_tb) ให้ตรงกับยอดที่แท้จริงใน Blockchain
                foreach (array_keys($userIdsToUpdate) as $userId) {
                    $realBalance = $this->getRealBalance($userId);
                    $user = User::with('wallet')->find($userId);
                    if ($user && $user->wallet) {
                        $user->wallet->Wallet_count = $realBalance;
                        $user->wallet->save();
                    }
                }

                $healedMessage .= "<br><span class='text-warning fw-bold'><i class='fa-solid fa-database'></i> ตรวจพบฐานข้อมูล MySQL ถูกดัดแปลง! ระบบได้ทำการ Restore ประวัติและยอดเงินจาก Blockchain เรียบร้อยแล้ว</span>";
            }
        }

        return [
            'success' => $isConsensusReached,
            'message' => $isConsensusReached 
                ? 'เครือข่ายปลอดภัย: ข้อมูลส่วนใหญ่ถูกต้อง' . $healedMessage
                : 'ระบบเตือนภัย: พบการทุจริตเกินกว่าครึ่งหนึ่งของเครือข่าย ไม่สามารถกู้คืนได้!',
            'nodes' => $results
        ];
    }

    public function getRealBalance($userId)
    {
        $chain = json_decode(Storage::get($this->nodes[0]), true);
        $realBalance = 0;

        for ($i = 1; $i < count($chain); $i++) {
            $tx = $chain[$i]['data'];
            
            if ($tx['user_id'] == $userId) {
                if (in_array($tx['type'], ['topup_credit', 'Topup (Admin)'], true)) {
                    $realBalance += (float)$tx['cost']; // เติมเงิน (บวก)
                } elseif (in_array($tx['type'], ['rental_debit', 'late_fee_debit', 'admin_debit', 'Deduct (Admin)', 'เช่าเกม', 'คืนเกม'], true)) {
                    // 👈 ย้าย 'คืนเกม' มารวมฝั่งลบ เพราะมันคือการหักค่าปรับ late_fee
                    $realBalance -= (float)$tx['cost']; 
                }
            }
        }
        return $realBalance;
    }
}
