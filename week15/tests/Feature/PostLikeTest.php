<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 【TDD 実践課題】いいね機能のエンドポイント (POST /posts/{post}/like) の Feature テスト。
 */
class PostLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_like_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.like', $post));

        $response->assertRedirect();
        $this->assertDatabaseHas('post_likes', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_liking_an_already_liked_post_removes_the_like(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->post(route('posts.like', $post));
        $this->actingAs($user)->post(route('posts.like', $post));

        $this->assertDatabaseMissing('post_likes', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_like_a_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->post(route('posts.like', $post));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_multiple_users_can_like_the_same_post_independently(): void
    {
        // 境界値/エッジケース: 同じ投稿に複数ユーザーが独立していいねできる
        $post = Post::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->post(route('posts.like', $post));
        $this->actingAs($userB)->post(route('posts.like', $post));

        $this->assertDatabaseCount('post_likes', 2);
    }

    public function test_liking_a_nonexistent_post_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/posts/999999/like');

        $response->assertNotFound();
    }

    public function test_is_liked_by_reflects_the_current_like_state(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($post->isLikedBy($user));

        $this->actingAs($user)->post(route('posts.like', $post));

        $this->assertTrue($post->isLikedBy($user));
    }

    public function test_is_liked_by_returns_false_for_a_guest(): void
    {
        // 境界値/エッジケース: ユーザーが null（未ログイン）の場合は必ず false
        $post = Post::factory()->create();

        $this->assertFalse($post->isLikedBy(null));
    }
}
