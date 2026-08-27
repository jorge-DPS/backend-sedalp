<?php

namespace App\Http\Controllers\Api\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\StoreProfessionRequest;
use App\Http\Requests\People\UpdateProfessionRequest;
use App\Http\Resources\People\ProfessionResource;
use App\Models\People\Profession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProfessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100
        );

        $search = trim(
            (string) $request->query('search', '')
        );

        $professions = Profession::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    'name',
                    'ILIKE',
                    "%{$search}%"
                )
            )
            ->when(
                $request->has('active'),
                fn ($query) => $query->where(
                    'active',
                    $request->boolean('active')
                )
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ProfessionResource::collection($professions);
    }

    public function store(
        StoreProfessionRequest $request
    ): JsonResponse {
        $profession = Profession::create(
            $request->validated()
        );

        return (new ProfessionResource($profession))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        Profession $profession
    ): ProfessionResource {
        return new ProfessionResource($profession);
    }

    public function update(
        UpdateProfessionRequest $request,
        Profession $profession
    ): ProfessionResource {
        $profession->update(
            $request->validated()
        );

        return new ProfessionResource(
            $profession->refresh()
        );
    }

    public function destroy(
        Profession $profession
    ): Response|JsonResponse {
        if ($profession->staffMembers()->exists()) {
            return response()->json([
                'message' => 'La profesión tiene personal asociado. Desactívela en lugar de eliminarla.',
            ], Response::HTTP_CONFLICT);
        }

        $profession->delete();

        return response()->noContent();
    }
}
