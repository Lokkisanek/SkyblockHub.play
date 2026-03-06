<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->decimal('threshold_price', 16, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('bin_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('auction_uuid')->unique();
            $table->decimal('price', 16, 2);
            $table->string('tier')->nullable();
            $table->string('seller_username')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['item_name', 'price']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_snapshots');
        Schema::dropIfExists('bin_alerts');
    }
};
