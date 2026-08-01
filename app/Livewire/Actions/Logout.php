<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke()
    {
        Auth::guard('web')->logout();
        Session::forget(['recommendation_algorithm', 'algorithm_selected_at']); // explicit, though invalidate() below also wipes it

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
