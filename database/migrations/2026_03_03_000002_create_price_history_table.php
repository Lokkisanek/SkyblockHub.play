<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bazaar_item_id')->constrained('bazaar_items')->onDelete('cascade');
            $table->decimal('sell_price', 16, 2);
            $table->decimal('buy_price', 16, 2);
            $table->bigInteger('sell_volume')->default(0);
            $table->bigInteger('buy_volume')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['bazaar_item_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
