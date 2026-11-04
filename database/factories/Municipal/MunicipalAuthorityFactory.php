<?php

namespace Database\Factories\Municipal;

use App\Enums\Municipal\MunicipalAuthorityRole;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Territory\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MunicipalAuthority> */
class MunicipalAuthorityFactory extends Factory
{
    protected $model = MunicipalAuthority::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'full_name' => fake()->name(),
            'role' => MunicipalAuthorityRole::COUNCIL_MEMBER,
            'is_current' => false,
            'is_published' => false,
        ];
    }

    public function mayor(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => MunicipalAuthorityRole::MAYOR]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes): array => ['is_current' => true]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => true]);
    }
}
