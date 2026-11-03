<?php

namespace Database\Factories\Territory;

use App\Enums\Territory\MembershipOriginType;
use App\Models\Territory\Municipality;
use App\Models\Territory\MunicipalityRegionMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MunicipalityRegionMembership> */
class MunicipalityRegionMembershipFactory extends Factory
{
    protected $model = MunicipalityRegionMembership::class;

    /** @return array{municipality_id: MunicipalityFactory, region_id: \Closure(array<string, mixed>): int, valid_from: string, valid_to: null, origin_type: MembershipOriginType, agreement_reference: null, agreement_date: null, notes: null} */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'region_id' => fn (array $attributes): int => Municipality::withTrashed()->findOrFail($attributes['municipality_id'])->region_id,
            'valid_from' => today()->subYear()->toDateString(),
            'valid_to' => null,
            'origin_type' => MembershipOriginType::BASELINE,
            'agreement_reference' => null,
            'agreement_date' => null,
            'notes' => null,
        ];
    }

    public function agreement(): static
    {
        return $this->state(fn (array $attributes): array => [
            'origin_type' => MembershipOriginType::AGREEMENT,
            'agreement_reference' => fake()->bothify('TEST-RES-####/????'),
            'agreement_date' => $attributes['valid_from'],
        ]);
    }
}
