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
        Schema::create('profiles_cache', function (Blueprint $table) {
            $table->id();
            $table->string('minecraft_uuid')->index();
            $table->string('profile_id')->index();
            $table->string('cute_name')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamps();

            $table->unique(['minecraft_uuid', 'profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles_cache');
    }
};
