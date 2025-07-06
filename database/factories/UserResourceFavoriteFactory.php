<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserResourceFavorite>
 */
class UserResourceFavoriteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'resource_id' => Resource::factory(),
        ];
    }

    /**
     * Indicate specific user and resource.
     */
    public function forUserAndResource(User $user, Resource $resource): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'resource_id' => $resource->id,
        ]);
    }
}
