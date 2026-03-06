<?php

namespace App\Console\Commands;

use App\Models\CraftingRecipe;
use Illuminate\Console\Command;

class SeedCraftingRecipes extends Command
{
    protected $signature = 'recipes:seed';

    protected $description = 'Seed crafting recipes for Bazaar arbitrage calculations';

    public function handle(): int
    {
        $recipes = $this->getRecipes();
        $count = 0;

        foreach ($recipes as $recipe) {
            CraftingRecipe::updateOrCreate(
                ['result_item_id' => $recipe['result_item_id']],
                $recipe,
            );
            $count++;
        }

        $this->info("Seeded {$count} crafting recipes.");
        return self::SUCCESS;
    }

    /**
     * Common Skyblock crafting recipes that use Bazaar materials.
     */
    private function getRecipes(): array
    {
        return [
            [
                'result_item_id'   => 'ENCHANTED_DIAMOND',
                'result_item_name' => 'Enchanted Diamond',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'DIAMOND', 'name' => 'Diamond', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_DIAMOND_BLOCK',
                'result_item_name' => 'Enchanted Diamond Block',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'ENCHANTED_DIAMOND', 'name' => 'Enchanted Diamond', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_GOLD',
                'result_item_name' => 'Enchanted Gold',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'GOLD_INGOT', 'name' => 'Gold Ingot', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_GOLD_BLOCK',
                'result_item_name' => 'Enchanted Gold Block',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'ENCHANTED_GOLD', 'name' => 'Enchanted Gold', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_IRON',
                'result_item_name' => 'Enchanted Iron',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'IRON_INGOT', 'name' => 'Iron Ingot', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_LAPIS_LAZULI',
                'result_item_name' => 'Enchanted Lapis Lazuli',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'INK_SACK:4', 'name' => 'Lapis Lazuli', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_REDSTONE',
                'result_item_name' => 'Enchanted Redstone',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'REDSTONE', 'name' => 'Redstone', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_REDSTONE_BLOCK',
                'result_item_name' => 'Enchanted Redstone Block',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'ENCHANTED_REDSTONE', 'name' => 'Enchanted Redstone', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_COBBLESTONE',
                'result_item_name' => 'Enchanted Cobblestone',
                'result_quantity'  => 1,
                'category'         => 'Mining',
                'ingredients'      => [
                    ['product_id' => 'COBBLESTONE', 'name' => 'Cobblestone', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_OAK_LOG',
                'result_item_name' => 'Enchanted Oak Wood',
                'result_quantity'  => 1,
                'category'         => 'Foraging',
                'ingredients'      => [
                    ['product_id' => 'LOG', 'name' => 'Oak Wood', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_BIRCH_LOG',
                'result_item_name' => 'Enchanted Birch Wood',
                'result_quantity'  => 1,
                'category'         => 'Foraging',
                'ingredients'      => [
                    ['product_id' => 'LOG:2', 'name' => 'Birch Wood', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_SUGAR',
                'result_item_name' => 'Enchanted Sugar',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'SUGAR_CANE', 'name' => 'Sugar Cane', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_RAW_BEEF',
                'result_item_name' => 'Enchanted Raw Beef',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'RAW_BEEF', 'name' => 'Raw Beef', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_PORK',
                'result_item_name' => 'Enchanted Pork',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'PORK', 'name' => 'Raw Porkchop', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_RAW_CHICKEN',
                'result_item_name' => 'Enchanted Raw Chicken',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'RAW_CHICKEN', 'name' => 'Raw Chicken', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_LEATHER',
                'result_item_name' => 'Enchanted Leather',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'LEATHER', 'name' => 'Leather', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_ROTTEN_FLESH',
                'result_item_name' => 'Enchanted Rotten Flesh',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'ROTTEN_FLESH', 'name' => 'Rotten Flesh', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_BONE',
                'result_item_name' => 'Enchanted Bone',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'BONE', 'name' => 'Bone', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_STRING',
                'result_item_name' => 'Enchanted String',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'STRING', 'name' => 'String', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_GUNPOWDER',
                'result_item_name' => 'Enchanted Gunpowder',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'SULPHUR', 'name' => 'Gunpowder', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_ENDER_PEARL',
                'result_item_name' => 'Enchanted Ender Pearl',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'ENDER_PEARL', 'name' => 'Ender Pearl', 'quantity' => 20],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_SLIME_BALL',
                'result_item_name' => 'Enchanted Slime Ball',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'SLIME_BALL', 'name' => 'Slime Ball', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_BLAZE_ROD',
                'result_item_name' => 'Enchanted Blaze Rod',
                'result_quantity'  => 1,
                'category'         => 'Combat',
                'ingredients'      => [
                    ['product_id' => 'BLAZE_ROD', 'name' => 'Blaze Rod', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_CARROT',
                'result_item_name' => 'Enchanted Carrot',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'CARROT_ITEM', 'name' => 'Carrot', 'quantity' => 160],
                ],
            ],
            [
                'result_item_id'   => 'ENCHANTED_POTATO',
                'result_item_name' => 'Enchanted Potato',
                'result_quantity'  => 1,
                'category'         => 'Farming',
                'ingredients'      => [
                    ['product_id' => 'POTATO_ITEM', 'name' => 'Potato', 'quantity' => 160],
                ],
            ],
            // ── Multi-ingredient recipes ──
            [
                'result_item_id'   => 'SPEED_POTION',
                'result_item_name' => 'Speed Potion',
                'result_quantity'  => 1,
                'category'         => 'Brewing',
                'ingredients'      => [
                    ['product_id' => 'ENCHANTED_SUGAR', 'name' => 'Enchanted Sugar', 'quantity' => 1],
                    ['product_id' => 'NETHER_STALK', 'name' => 'Nether Wart', 'quantity' => 1],
                ],
            ],
            [
                'result_item_id'   => 'CRITICAL_POTION',
                'result_item_name' => 'Critical Potion',
                'result_quantity'  => 1,
                'category'         => 'Brewing',
                'ingredients'      => [
                    ['product_id' => 'ENCHANTED_FLINT', 'name' => 'Enchanted Flint', 'quantity' => 1],
                    ['product_id' => 'NETHER_STALK', 'name' => 'Nether Wart', 'quantity' => 1],
                ],
            ],
        ];
    }
}
