<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('Topup_request_tb', function(Blueprint $t){ $t->string('Provider_charge_id',80)->nullable()->unique()->after('Reference'); }); }
 public function down(): void { Schema::table('Topup_request_tb', fn(Blueprint $t)=>$t->dropColumn('Provider_charge_id')); }
};
