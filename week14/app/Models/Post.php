<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この投稿に「いいね」したユーザー一覧。
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }

    /**
     * 指定したユーザーがこの投稿に「いいね」済みかどうか。
     */
    public function isLikedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->likedByUsers()->where('user_id', $user->id)->exists();
    }
}
