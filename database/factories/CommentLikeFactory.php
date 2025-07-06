<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentLike>
 */
class CommentLikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate specific comment and user.
     */
    public function forCommentAndUser(Comment $comment, User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }
}
