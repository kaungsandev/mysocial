<?php

namespace App\Http\Controllers;

use App\Models\Category;

class OnboardingController extends Controller
{
    public function interests()
    {
        $categories = Category::all();

        return view('onboarding.interests', compact('categories'));
    }

    public function storeInterests()
    {
        request()->validate([
            'interests' => 'required|array',
            'interests.*' => 'exists:categories,id',
        ]);

        $user = auth()->user();
        $user->interests()->syncWithPivotValues(request('interests'), ['weight' => 1]);
        $user->new_account = false;
        $user->save();

        return redirect()->route('home');
    }
}
