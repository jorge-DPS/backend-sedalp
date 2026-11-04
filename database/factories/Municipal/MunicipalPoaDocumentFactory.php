<?php

namespace Database\Factories\Municipal;

use App\Enums\Municipal\PoaDocumentType;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MunicipalPoaDocument> */
class MunicipalPoaDocumentFactory extends Factory
{
    protected $model = MunicipalPoaDocument::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'municipal_poa_id' => MunicipalPoa::factory(),
            'version_number' => 1,
            'document_type' => PoaDocumentType::INITIAL,
            'title' => fake()->sentence(4),
            'pdf_path' => 'municipal/poas/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'is_current' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()]);
    }

    public function current(): static
    {
        return $this->published()->state(fn (array $attributes): array => ['is_current' => true]);
    }
}
