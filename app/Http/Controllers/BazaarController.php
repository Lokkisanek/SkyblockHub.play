<?php

namespace App\Http\Controllers;

use App\Models\BazaarItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BazaarController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BazaarItem::query();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('product_id', 'ilike', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Sorting
        $sortBy = $request->input('sort', 'name');
        $sortDir = $request->input('dir', 'asc');
        $allowed = ['name', 'sell_price', 'buy_price', 'sell_volume', 'buy_volume', 'sell_moving_week', 'buy_moving_week'];
        if (in_array($sortBy, $allowed)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $items = $query->paginate(50)->withQueryString();

        return Inertia::render('Bazaar/Index', [
            'items'   => $items,
            'filters' => [
                'search'   => $search,
                'category' => $category,
                'sort'     => $sortBy,
                'dir'      => $sortDir,
            ],
        ]);
    }
}
