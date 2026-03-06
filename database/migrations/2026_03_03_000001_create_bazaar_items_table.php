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
        Schema::create('bazaar_items', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique()->index();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->decimal('sell_price', 16, 2)->default(0);
            $table->decimal('buy_price', 16, 2)->default(0);
            $table->bigInteger('sell_volume')->default(0);
            $table->bigInteger('buy_volume')->default(0);
            $table->bigInteger('sell_orders')->default(0);
            $table->bigInteger('buy_orders')->default(0);
            $table->decimal('sell_moving_week', 20, 2)->default(0);
            $table->decimal('buy_moving_week', 20, 2)->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bazaar_items');
    }
};
