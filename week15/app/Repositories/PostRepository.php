<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\User;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PostRepository implements PostRepositoryInterface
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        // N+1問題対策: with('user') で投稿者情報を事前に読み込む
        return Post::with('user')->latest()->paginate($perPage);
    }

    public function findOrFail(int $id): Post
    {
        return Post::with('user')->findOrFail($id);
    }

    public function create(array $data): Post
    {
        return Post::create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);
        return $post;
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    public function toggleLike(Post $post, User $user): bool
    {
        $alreadyLiked = $post->likedByUsers()->where('user_id', $user->id)->exists();

        if ($alreadyLiked) {
            $post->likedByUsers()->detach($user->id);
            return false;
        }

        $post->likedByUsers()->attach($user->id);
        return true;
    }

    public function likesCount(Post $post): int
    {
        return $post->likedByUsers()->count();
    }
}
