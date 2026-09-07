<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\PostService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * PostService の単体テスト。
 *
 * PostService は DB に直接アクセスせず、PostRepositoryInterface に処理を委譲するだけの
 * ロジックを持つため、リポジトリを Mockery でモック化することで DB に依存しない
 * 純粋なユニットテストとして検証できる。
 *
 * テスト観点:
 *   - 正常系: 正しい入力で期待した結果が返るか
 *   - 異常系: 不正な入力・例外発生時に正しく伝播するか
 *   - 境界値: perPage / id の上限・下限付近で正しく動くか
 */
class PostServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(PostRepositoryInterface $repository): PostService
    {
        return new PostService($repository);
    }

    // ============================================================
    // 正常系
    // ============================================================

    public function test_get_paginated_posts_returns_paginator_from_repository(): void
    {
        $paginator = new ConcreteLengthAwarePaginator([], 0, 10);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('paginate')->once()->with(10)->andReturn($paginator);

        $service = $this->makeService($repository);
        $result = $service->getPaginatedPosts(10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_find_post_returns_post_from_repository(): void
    {
        $post = new Post(['title' => 'タイトル', 'content' => '本文']);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('findOrFail')->once()->with(1)->andReturn($post);

        $service = $this->makeService($repository);
        $result = $service->findPost(1);

        $this->assertSame($post, $result);
    }

    public function test_create_post_sets_user_id_and_delegates_to_repository(): void
    {
        $createdPost = new Post();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['title'] === 'タイトル'
                    && $data['content'] === '本文'
                    && $data['user_id'] === 5;
            }))
            ->andReturn($createdPost);

        $service = $this->makeService($repository);
        $result = $service->createPost(['title' => 'タイトル', 'content' => '本文'], 5);

        $this->assertSame($createdPost, $result);
    }

    public function test_update_post_delegates_to_repository(): void
    {
        $post = new Post();
        $updated = new Post(['title' => '更新後']);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($post, ['title' => '更新後'])
            ->andReturn($updated);

        $service = $this->makeService($repository);
        $result = $service->updatePost($post, ['title' => '更新後']);

        $this->assertSame($updated, $result);
    }

    public function test_delete_post_delegates_to_repository(): void
    {
        $post = new Post();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('delete')->once()->with($post);

        $service = $this->makeService($repository);
        $service->deletePost($post);

        // void メソッドなので、モックの期待値検証が通れば成功
        $this->assertTrue(true);
    }

    // ============================================================
    // 異常系
    // ============================================================

    public function test_find_post_throws_exception_when_post_does_not_exist(): void
    {
        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('findOrFail')
            ->once()
            ->with(9999)
            ->andThrow(new ModelNotFoundException());

        $this->expectException(ModelNotFoundException::class);

        $service = $this->makeService($repository);
        $service->findPost(9999);
    }

    public function test_create_post_with_empty_data_still_attaches_user_id(): void
    {
        // 異常に近いケース: バリデーション漏れなどで空配列が渡ってきても
        // Service層はuser_idを必ず付与してRepositoryに委譲する
        $createdPost = new Post();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(['user_id' => 1])
            ->andReturn($createdPost);

        $service = $this->makeService($repository);
        $result = $service->createPost([], 1);

        $this->assertSame($createdPost, $result);
    }

    // ============================================================
    // 境界値
    // ============================================================

    public function test_get_paginated_posts_with_minimum_per_page_of_one(): void
    {
        // 境界値: perPage の最小実用値 (1)
        $paginator = new ConcreteLengthAwarePaginator([], 0, 1);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('paginate')->once()->with(1)->andReturn($paginator);

        $service = $this->makeService($repository);
        $result = $service->getPaginatedPosts(1);

        $this->assertSame(1, $result->perPage());
    }

    public function test_get_paginated_posts_uses_default_per_page_when_not_specified(): void
    {
        // 境界値: 引数省略時のデフォルト値 (10) が使われる
        $paginator = new ConcreteLengthAwarePaginator([], 0, 10);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('paginate')->once()->with(10)->andReturn($paginator);

        $service = $this->makeService($repository);
        $service->getPaginatedPosts();

        // with(10) の期待値が満たされなければ Mockery が失敗させる
        $this->assertTrue(true);
    }

    public function test_get_paginated_posts_with_large_per_page(): void
    {
        // 境界値: 大きな値でもそのまま Repository に渡される
        $paginator = new ConcreteLengthAwarePaginator([], 0, 1000);

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('paginate')->once()->with(1000)->andReturn($paginator);

        $service = $this->makeService($repository);
        $result = $service->getPaginatedPosts(1000);

        $this->assertSame(1000, $result->perPage());
    }

    public function test_find_post_with_minimum_valid_id_of_one(): void
    {
        // 境界値: ID の最小値 (1)
        $post = new Post();

        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('findOrFail')->once()->with(1)->andReturn($post);

        $service = $this->makeService($repository);
        $result = $service->findPost(1);

        $this->assertSame($post, $result);
    }
}
