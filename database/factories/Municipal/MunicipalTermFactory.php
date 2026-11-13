<?php

namespace Database\Factories\Municipal;

use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MunicipalTerm> */
class MunicipalTermFactory extends Factory
{
    protected $model = MunicipalTerm::class;

    public function definition(): array
    {
        $start = fake()->numberBetween(1990, 2040);

        return ['municipality_id' => Municipality::factory(), 'start_year' => $start, 'end_year' => $start + 5];
    }
}
