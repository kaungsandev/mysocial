<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $posts = Post::all();

        return view('post.index');
    }

    public function show(Request $request, Post $post): \Illuminate\Contracts\View\View
    {
        return view('post.show');
    }
}
