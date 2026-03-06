<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileStatsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('ProfileStats/Index', [
            'minecraftUsername' => $request->user()->minecraft_username,
        ]);
    }
}
