<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\PublishMapLayerRequest;
use App\Http\Resources\Territory\MapLayerResource;
use App\Models\Territory\MapLayer;
use App\Services\Territory\MapPublicationService;

class MapPublicationController extends Controller
{
    public function __invoke(PublishMapLayerRequest $request, MapLayer $mapLayer, MapPublicationService $publications): MapLayerResource
    {
        return new MapLayerResource($publications->update(
            $mapLayer, $request->validated(), $request->user('api')->id, ! $request->isMethod('DELETE'),
        ));
    }
}
