<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('polygon_anchor_history');
    }

    public function down(): void
    {
        // Polygon status is stored on Transaction_tb in the current schema.
    }
};
