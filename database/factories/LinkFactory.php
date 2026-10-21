<?php

namespace Database\Factories;

use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Link> */
class LinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => strtolower(fake()->unique()->bothify('?????#')),
            'destination_url' => fake()->url(),
        ];
    }
}
