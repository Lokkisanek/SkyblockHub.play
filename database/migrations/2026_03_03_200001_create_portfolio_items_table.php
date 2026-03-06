<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id');
            $table->string('product_name');
            $table->decimal('buy_price', 16, 2);
            $table->integer('quantity');
            $table->timestamp('purchased_at');
            $table->decimal('sold_price', 16, 2)->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sold_at']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};
