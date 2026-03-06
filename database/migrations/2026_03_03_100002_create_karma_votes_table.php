<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karma_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('target_id')->constrained('users')->onDelete('cascade');
            $table->smallInteger('value');  // +1 or -1
            $table->timestamps();

            $table->unique(['voter_id', 'target_id']);
            $table->index('target_id');
        });

        // Add karma_score column to users table for fast reads
        Schema::table('users', function (Blueprint $table) {
            $table->integer('karma_score')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karma_votes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('karma_score');
        });
    }
};
