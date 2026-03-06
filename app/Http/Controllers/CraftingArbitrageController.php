<?php

namespace App\Http\Controllers;

use App\Models\BazaarItem;
use App\Models\CraftingRecipe;
use Inertia\Inertia;
use Inertia\Response;

class CraftingArbitrageController extends Controller
{
    public function index(): Response
    {
        $recipes = CraftingRecipe::orderBy('result_item_name')->get();

        // Fetch current Bazaar prices for all products referenced in recipes
        $allProductIds = collect();
        foreach ($recipes as $recipe) {
            $allProductIds->push($recipe->result_item_id);
            foreach ($recipe->ingredients as $ingredient) {
                $allProductIds->push($ingredient['product_id']);
            }
        }

        $prices = BazaarItem::whereIn('product_id', $allProductIds->unique()->toArray())
            ->get()
            ->keyBy('product_id');

        // Calculate arbitrage for each recipe
        $arbitrageData = $recipes->map(function ($recipe) use ($prices) {
            $resultItem = $prices[$recipe->result_item_id] ?? null;
            $sellPrice = $resultItem ? (float) $resultItem->sell_price : 0;
            $resultSellVolume = $resultItem ? $resultItem->sell_volume : 0;

            $craftCost = 0;
            $ingredientDetails = [];
            $allAvailable = true;

            foreach ($recipe->ingredients as $ingredient) {
                $item = $prices[$ingredient['product_id']] ?? null;
                $buyPrice = $item ? (float) $item->buy_price : 0;
                $qty = $ingredient['quantity'];
                $cost = $buyPrice * $qty;
                $craftCost += $cost;

                if (!$item) {
                    $allAvailable = false;
                }

                $ingredientDetails[] = [
                    'product_id' => $ingredient['product_id'],
                    'name'       => $ingredient['name'],
                    'quantity'   => $qty,
                    'unit_price' => $buyPrice,
                    'total_cost' => round($cost, 2),
                    'available'  => $item !== null,
                ];
            }

            $profit = ($sellPrice * $recipe->result_quantity) - $craftCost;
            $margin = $craftCost > 0 ? ($profit / $craftCost) * 100 : 0;

            return [
                'result_item_id'   => $recipe->result_item_id,
                'result_item_name' => $recipe->result_item_name,
                'result_quantity'  => $recipe->result_quantity,
                'category'         => $recipe->category,
                'sell_price'       => round($sellPrice, 2),
                'sell_volume'      => $resultSellVolume,
                'craft_cost'       => round($craftCost, 2),
                'profit'           => round($profit, 2),
                'margin'           => round($margin, 2),
                'ingredients'      => $ingredientDetails,
                'all_available'    => $allAvailable,
            ];
        });

        // Get unique categories for filtering
        $categories = $recipes->pluck('category')->filter()->unique()->sort()->values();

        return Inertia::render('Crafting/Index', [
            'recipes'    => $arbitrageData,
            'categories' => $categories,
        ]);
    }
}
