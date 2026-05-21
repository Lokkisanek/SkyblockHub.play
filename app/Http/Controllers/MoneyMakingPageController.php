<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyMakingPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $username = trim((string) $request->query('username', ''));

        return Inertia::render('MoneyMaking/Index', [
            'minecraftUsername' => $username !== '' ? $username : null,
        ]);
    }
}
