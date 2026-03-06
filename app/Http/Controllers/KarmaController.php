<?php

namespace App\Http\Controllers;

use App\Models\KarmaVote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KarmaController extends Controller
{
    /**
     * Cast or update a vote on a target user.
     *
     * POST /api/karma/vote
     * { target_id: int, value: 1 | -1 }
     */
    public function vote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|integer|exists:users,id',
            'value'     => 'required|integer|in:-1,1',
        ]);

        $voterId  = $request->user()->id;
        $targetId = $validated['target_id'];

        // Cannot vote for yourself
        if ($voterId === $targetId) {
            return response()->json(['error' => 'Cannot vote for yourself.'], 422);
        }

        $existing = KarmaVote::where('voter_id', $voterId)
            ->where('target_id', $targetId)
            ->first();

        $oldValue = 0;

        if ($existing) {
            // If same vote, remove it (toggle off)
            if ($existing->value === $validated['value']) {
                $oldValue = $existing->value;
                $existing->delete();

                User::where('id', $targetId)->decrement('karma_score', $oldValue);

                $target = User::find($targetId);
                return response()->json([
                    'karma_score' => $target->karma_score,
                    'my_vote'     => 0,
                ]);
            }

            // Otherwise update the vote direction
            $oldValue = $existing->value;
            $existing->update(['value' => $validated['value']]);
        } else {
            KarmaVote::create([
                'voter_id'  => $voterId,
                'target_id' => $targetId,
                'value'     => $validated['value'],
            ]);
        }

        // Adjust the cached karma_score: remove old value, add new value
        $delta = $validated['value'] - $oldValue;
        User::where('id', $targetId)->increment('karma_score', $delta);

        $target = User::find($targetId);

        return response()->json([
            'karma_score' => $target->karma_score,
            'my_vote'     => $validated['value'],
        ]);
    }

    /**
     * Get the current user's vote on a target.
     *
     * GET /api/karma/{targetId}
     */
    public function status(Request $request, int $targetId): JsonResponse
    {
        $vote = KarmaVote::where('voter_id', $request->user()->id)
            ->where('target_id', $targetId)
            ->first();

        $target = User::find($targetId);

        return response()->json([
            'karma_score' => $target?->karma_score ?? 0,
            'my_vote'     => $vote?->value ?? 0,
        ]);
    }
}
