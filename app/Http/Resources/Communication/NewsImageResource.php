<?php

namespace App\Http\Resources\Communication;

use App\Models\Communication\NewsImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NewsImageResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        $baseUrl = Storage::disk(
            config('media.disk', 'public')
        )->url(
            NewsImage::MEDIA_DIRECTORY
        );

        return [

            'id' => $this->id,

            /*
             * Sin extensión.
             */
            'filename' => $this->filename,

            /*
             * No está almacenado en PostgreSQL.
             * Laravel lo calcula.
             */
            'baseUrl' => rtrim(
                $baseUrl,
                '/'
            ),

            'alt' => $this->alt,

            'caption' => $this->caption,

            'position' => $this->position,
        ];
    }
}
