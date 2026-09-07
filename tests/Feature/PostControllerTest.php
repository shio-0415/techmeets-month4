<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 投稿機能 (Post) の CRUD・バリデーション・認可に関する Feature テスト。
 */
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 未認証ユーザー（エッジケース）
    // ============================================================

    public function test_guest_is_redirected_to_login_when_viewing_posts_index(): void
    {
        $response = $this->get(route('posts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_when_creating_a_post(): void
    {
        $response = $this->post(route('posts.store'), [
            'title' => 'タイトル',
            'content' => '本文',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('posts', 0);
    }

    // ============================================================
    // Read
    // ============================================================

    public function test_authenticated_user_can_view_posts_index(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('posts.index'));

        $response->assertOk();
        $response->assertViewIs('posts.index');
    }

    public function test_authenticated_user_can_view_a_single_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)->get(route('posts.show', $post));

        $response->assertOk();
        $response->assertSee($post->title);
    }

    public function test_authenticated_user_can_view_the_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('posts.create'));

        $response->assertOk();
        $response->assertViewIs('posts.create');
    }

    // ============================================================
    // Create
    // ============================================================

    public function test_authenticated_user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'はじめての投稿',
            'content' => '本文です。',
        ]);

        $response->assertRedirect(route('posts.index'));
        $this->assertDatabaseHas('posts', [
            'title' => 'はじめての投稿',
            'content' => '本文です。',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_a_post_requires_title_and_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => '',
            'content' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'content']);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_creating_a_post_with_title_at_max_length_succeeds(): void
    {
        // 境界値: title は max:255 → ちょうど255文字は許可される
        $user = User::factory()->create();
        $title = str_repeat('あ', 255);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => $title,
            'content' => '本文',
        ]);

        $response->assertRedirect(route('posts.index'));
        $this->assertDatabaseHas('posts', ['title' => $title]);
    }

    public function test_creating_a_post_with_title_over_max_length_fails(): void
    {
        // 境界値: 256文字は max:255 のバリデーションに違反し失敗する
        $user = User::factory()->create();
        $title = str_repeat('あ', 256);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => $title,
            'content' => '本文',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('posts', 0);
    }

    // ============================================================
    // Update
    // ============================================================

    public function test_owner_can_view_edit_form_for_their_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('posts.edit', $post));

        $response->assertOk();
        $response->assertViewIs('posts.edit');
    }

    public function test_owner_can_update_their_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => '更新後タイトル',
            'content' => '更新後本文',
        ]);

        $response->assertRedirect(route('posts.index'));
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => '更新後タイトル',
            'content' => '更新後本文',
        ]);
    }

    public function test_updating_a_post_requires_title_and_content(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => '',
            'content' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'content']);
    }

    public function test_updating_a_nonexistent_post_returns_404(): void
    {
        // エッジケース: 存在しない ID を更新しようとすると 404
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/posts/999999', [
            'title' => 'タイトル',
            'content' => '本文',
        ]);

        $response->assertNotFound();
    }

    // ============================================================
    // Delete
    // ============================================================

    public function test_owner_can_delete_their_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('posts.destroy', $post));

        $response->assertRedirect(route('posts.index'));
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    // ============================================================
    // 認可: 他ユーザーの投稿は編集・削除できない
    // ============================================================

    public function test_other_user_cannot_view_edit_form_for_someone_elses_post(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->get(route('posts.edit', $post));

        $response->assertForbidden();
    }

    public function test_other_user_cannot_update_someone_elses_post(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->put(route('posts.update', $post), [
            'title' => '不正な更新',
            'content' => '不正な本文',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $post->title,
        ]);
    }

    public function test_other_user_cannot_delete_someone_elses_post(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser)->delete(route('posts.destroy', $post));

        $response->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }
}
