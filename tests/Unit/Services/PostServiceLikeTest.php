<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\PostService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * 【TDD 実践課題】いいね機能 (PostService::toggleLike) の単体テスト。
 *
 * これは Red → Green → Refactor の "Red" フェーズで最初に書いたテストです。
 * 実装 (PostService::toggleLike, PostRepositoryInterface::toggleLike/likesCount)
 * より先にこのファイルを書き、`php artisan test` が失敗することを確認してから
 * 実装を追加しました。詳細は README.md の「練習課題3: TDD実践」を参照してください。
 */
class PostServiceLikeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_toggle_like_likes_a_post_the_user_has_not_liked_yet(): void
    {
        $post = new Post();
        $user = new User();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('toggleLike')->once()->with($post, $user)->andReturn(true);
        $repository->shouldReceive('likesCount')->once()->with($post)->andReturn(1);

        $service = new PostService($repository);
        $result = $service->toggleLike($post, $user);

        $this->assertTrue($result['liked']);
        $this->assertSame(1, $result['likes_count']);
    }

    public function test_toggle_like_unlikes_a_post_the_user_already_liked(): void
    {
        $post = new Post();
        $user = new User();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('toggleLike')->once()->with($post, $user)->andReturn(false);
        $repository->shouldReceive('likesCount')->once()->with($post)->andReturn(0);

        $service = new PostService($repository);
        $result = $service->toggleLike($post, $user);

        $this->assertFalse($result['liked']);
        $this->assertSame(0, $result['likes_count']);
    }

    public function test_toggle_like_boundary_when_zero_likes_remain(): void
    {
        // 境界値: いいねを取り消した結果、件数が 0 になるケース
        $post = new Post();
        $user = new User();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('toggleLike')->once()->andReturn(false);
        $repository->shouldReceive('likesCount')->once()->andReturn(0);

        $service = new PostService($repository);
        $result = $service->toggleLike($post, $user);

        $this->assertSame(0, $result['likes_count']);
    }
}
