<?php

namespace App\Http\Controllers\Api\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\IndexPositionRequest;
use App\Http\Requests\People\StorePositionRequest;
use App\Http\Requests\People\UpdatePositionRequest;
use App\Http\Resources\People\PositionResource;
use App\Models\People\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PositionController extends Controller
{
    public function index(
        IndexPositionRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $search = $filters['search'] ?? null;

        $perPage = (int) (
            $filters['per_page']
            ?? 15
        );

        $positions = Position::query()
            ->when(
                filled($search),
                fn ($query) => $query->where(
                    'name',
                    'ILIKE',
                    "%{$search}%"
                )
            )
            ->when(
                array_key_exists(
                    'active',
                    $filters
                ),
                fn ($query) => $query->where(
                    'active',
                    $filters['active']
                )
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return PositionResource::collection(
            $positions
        );
    }

    public function store(
        StorePositionRequest $request
    ): JsonResponse {
        $position = Position::create(
            $request->validated()
        );

        return (new PositionResource($position))
            ->response()
            ->setStatusCode(
                Response::HTTP_CREATED
            );
    }

    public function show(
        Position $position
    ): PositionResource {
        return new PositionResource(
            $position
        );
    }

    public function update(
        UpdatePositionRequest $request,
        Position $position
    ): PositionResource {
        $position->update(
            $request->validated()
        );

        return new PositionResource(
            $position->refresh()
        );
    }

    public function destroy(
        Position $position
    ): Response|JsonResponse {
        if (
            $position
                ->staffMembers()
                ->exists()
        ) {
            return response()->json([
                'message' => 'El cargo tiene personal asociado. Desactívelo en lugar de eliminarlo.',
            ], Response::HTTP_CONFLICT);
        }

        $position->delete();

        return response()->noContent();
    }
}
