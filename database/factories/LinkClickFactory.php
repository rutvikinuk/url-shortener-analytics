<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LinkClickFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ip' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (test)',
            'referrer' => null,
            'created_at' => now(),
        ];
    }
}
