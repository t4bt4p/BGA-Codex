<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\TopupSettlementService;
use Illuminate\Http\Request;

class OpnWebhookController extends Controller
{
    public function handle(Request $request, TopupSettlementService $settlement)
    {
        $event = $request->json()->all();
        $charge = $event['data'] ?? [];
        if (($event['key'] ?? '') !== 'charge.complete' || ($charge['status'] ?? '') !== 'successful') {
            return response()->json(['received' => true]);
        }
        $id = $charge['id'] ?? null;
        if (! $id) {
            return response()->json(['received' => true]);
        }
        $settlement->syncByChargeId($id);

        return response()->json(['received' => true]);
    }
}
