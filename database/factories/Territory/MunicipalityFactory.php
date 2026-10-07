<?php

namespace Database\Factories\Territory;

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Municipality> */
class MunicipalityFactory extends Factory
{
    protected $model = Municipality::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'province_id' => Province::factory(),
            'name' => fake()->unique()->city(),
            'official_code' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
