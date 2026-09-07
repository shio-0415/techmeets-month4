<?php

namespace App\Repositories\Contracts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PostRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): Post;

    public function create(array $data): Post;

    public function update(Post $post, array $data): Post;

    public function delete(Post $post): void;

    /**
     * 指定ユーザーによる「いいね」をトグルする。
     * すでにいいね済みなら取り消し、未いいねなら追加する。
     *
     * @return bool トグル後にいいね済みなら true、いいね解除なら false
     */
    public function toggleLike(Post $post, User $user): bool;

    /**
     * 投稿の「いいね」数を取得する。
     */
    public function likesCount(Post $post): int;
}
