<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dungeon_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('floor')->index();           // e.g. "F7", "M7", "F1"
            $table->string('class');                      // e.g. "Healer", "Berserker", "Mage", "Archer", "Tank"
            $table->integer('catacombs_level')->default(0);
            $table->string('note', 255)->nullable();      // short description
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'floor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_parties');
    }
};
