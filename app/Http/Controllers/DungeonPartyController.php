<?php

namespace App\Http\Controllers;

use App\Models\DungeonParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DungeonPartyController extends Controller
{
    public function index(Request $request): Response
    {
        $query = DungeonParty::with('user:id,name,discord_username,discord_avatar,karma_score')
            ->active()
            ->orderByDesc('created_at');

        if ($floor = $request->input('floor')) {
            $query->where('floor', $floor);
        }

        if ($class = $request->input('class')) {
            $query->where('class', $class);
        }

        $listings = $query->paginate(30)->withQueryString();

        // Get the current user's active listing (if any)
        $myListing = DungeonParty::where('user_id', $request->user()->id)
            ->active()
            ->first();

        return Inertia::render('DungeonParty/Index', [
            'listings'  => $listings,
            'myListing' => $myListing,
            'filters'   => [
                'floor' => $floor,
                'class' => $class,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'floor'           => 'required|string|max:10',
            'class'           => 'required|string|in:Healer,Berserker,Mage,Archer,Tank',
            'catacombs_level'  => 'required|integer|min:0|max:100',
            'note'            => 'nullable|string|max:255',
        ]);

        // Deactivate any existing active listing for this user
        DungeonParty::where('user_id', $request->user()->id)
            ->active()
            ->update(['is_active' => false]);

        DungeonParty::create([
            'user_id'         => $request->user()->id,
            'floor'           => $validated['floor'],
            'class'           => $validated['class'],
            'catacombs_level'  => $validated['catacombs_level'],
            'note'            => $validated['note'] ?? null,
            'is_active'       => true,
        ]);

        return redirect()->route('dungeon-party')->with('status', 'Listing created.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        DungeonParty::where('user_id', $request->user()->id)
            ->active()
            ->update(['is_active' => false]);

        return redirect()->route('dungeon-party')->with('status', 'Listing removed.');
    }
}
