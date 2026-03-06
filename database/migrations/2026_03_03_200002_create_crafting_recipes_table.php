<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crafting_recipes', function (Blueprint $table) {
            $table->id();
            $table->string('result_item_id')->unique();
            $table->string('result_item_name');
            $table->integer('result_quantity')->default(1);
            $table->json('ingredients'); // [{product_id, name, quantity}]
            $table->string('category')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crafting_recipes');
    }
};
