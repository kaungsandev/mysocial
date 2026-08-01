<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlgorithmSelectionController extends Controller
{
    public function show(): View
    {
        return view('algorithm.select');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'algorithm' => 'required|in:collaborative,content',
        ]);

        session([
            'recommendation_algorithm' => $request->input('algorithm'),
            'algorithm_selected_at' => now()->timestamp,
        ]);

        return redirect()->route('home');
    }
}
