<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\PostController
 */
final class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_displays_view(): void
    {
        $posts = Post::factory()->count(3)->create();

        $response = $this->get(route('posts.index'));

        $response->assertOk();
        $response->assertViewIs('post.index');
    }


    #[Test]
    public function show_displays_view(): void
    {
        $post = Post::factory()->create();

        $response = $this->get(route('posts.show', $post));

        $response->assertOk();
        $response->assertViewIs('post.show');
    }
}
