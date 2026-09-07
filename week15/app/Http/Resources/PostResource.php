<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
            ],
            'created_at' => $post->created_at->format('Y-m-d H:i'),
        ];
    }
}
