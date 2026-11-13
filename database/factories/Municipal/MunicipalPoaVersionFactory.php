<?php

namespace Database\Factories\Municipal;

use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MunicipalPoaVersion> */
class MunicipalPoaVersionFactory extends Factory
{
    protected $model = MunicipalPoaVersion::class;

    public function definition(): array
    {
        return ['municipal_poa_id' => MunicipalPoa::factory(), 'version_number' => 1, 'document_type' => 'initial'];
    }

    public function reformulated(int $number = 2): static
    {
        return $this->state(fn (): array => ['version_number' => $number, 'document_type' => 'reformulated']);
    }
}
