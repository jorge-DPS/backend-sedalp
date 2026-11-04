<?php

namespace Database\Factories\Municipal;

use App\Models\Municipal\MunicipalPoa;
use App\Models\Territory\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MunicipalPoa> */
class MunicipalPoaFactory extends Factory
{
    protected $model = MunicipalPoa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'fiscal_year' => 2026,
            'title' => fake()->sentence(4),
            'notes' => null,
        ];
    }
}
