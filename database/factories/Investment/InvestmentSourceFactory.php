<?php

namespace Database\Factories\Investment;

use App\Models\Investment\Institution;
use App\Models\Investment\InvestmentSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestmentSource> */
class InvestmentSourceFactory extends Factory
{
    protected $model = InvestmentSource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'provided_by_name' => fake()->company(),
            'source_type' => 'test_report',
            'title' => fake()->sentence(5),
            'fiscal_year' => null,
            'document_reference' => null,
            'file_path' => null,
        ];
    }
}
