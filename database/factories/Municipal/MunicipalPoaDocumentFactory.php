<?php

namespace Database\Factories\Municipal;

use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MunicipalPoaDocument> */
class MunicipalPoaDocumentFactory extends Factory
{
    protected $model = MunicipalPoaDocument::class;

    public function definition(): array
    {
        return [
            'municipal_poa_version_id' => MunicipalPoaVersion::factory(),
            'title' => fake()->sentence(4),
            'pdf_path' => 'municipal/poas/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
        ];
    }

    public function forPoa(MunicipalPoa $poa, int $number = 1): static
    {
        return $this->state(fn (): array => [
            'municipal_poa_version_id' => MunicipalPoaVersion::factory()->for($poa)->create([
                'version_number' => $number, 'document_type' => $number === 1 ? 'initial' : 'reformulated',
            ])->id,
        ]);
    }

    public function published(): static
    {
        return $this->afterCreating(function (MunicipalPoaDocument $document): void {
            $document->version->update(['published_at' => now()]);
            $document->refresh();
        });
    }

    public function current(): static
    {
        return $this->published()->afterCreating(function (MunicipalPoaDocument $document): void {
            $document->version->update(['is_current' => true]);
            $document->refresh();
        });
    }
}
