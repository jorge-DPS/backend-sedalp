<?php

namespace App\Http\Requests\Communication;

use App\Enums\Communication\NewsStatus;
use App\Models\Communication\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('news.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'excerpt' => [
                'sometimes',
                'required',
                'string',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
            ],

            'content' => [
                'sometimes',
                'required',
                'array',
            ],

            'content.type' => [
                'required_with:content',
                'string',
                'in:doc',
            ],

            'content.content' => [
                'nullable',
                'array',
            ],

            'status' => [
                'sometimes',
                Rule::enum(NewsStatus::class),
            ],

            'published_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /*
                 * Si status o published_at ya fallaron
                 * en sus reglas básicas, evitamos agregar
                 * errores derivados.
                 */
                if (
                    $validator->errors()->has('status')
                    || $validator->errors()->has('published_at')
                ) {
                    return;
                }

                $news = $this->route('news');

                if (! $news instanceof News) {
                    return;
                }

                /*
                 * Determinamos el estado final.
                 *
                 * Si el request manda status, usamos ese.
                 * Si no, conservamos el estado actual.
                 */
                $currentStatus = $news->status instanceof NewsStatus
                    ? $news->status->value
                    : (string) $news->status;

                $finalStatus = $this->input(
                    'status',
                    $currentStatus
                );

                /*
                 * Determinamos published_at final.
                 *
                 * Si el request incluye published_at,
                 * incluso si viene null, usamos ese valor.
                 *
                 * Si no viene, conservamos la fecha actual.
                 */
                $input = $this->all();

                $finalPublishedAt = array_key_exists(
                    'published_at',
                    $input
                )
                    ? $input['published_at']
                    : $news->published_at;

                /*
                 * Invariante de negocio:
                 *
                 * Toda noticia publicada debe tener
                 * fecha de publicación.
                 */
                if (
                    $finalStatus === NewsStatus::PUBLISHED->value
                    && blank($finalPublishedAt)
                ) {
                    $validator->errors()->add(
                        'published_at',
                        'La fecha de publicación es obligatoria cuando la noticia está publicada.'
                    );
                }
            },
        ];
    }
}
