<?php

namespace App\Http\Controllers\Api\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\StoreOrganizationalUnitRequest;
use App\Http\Requests\People\UpdateOrganizationalUnitRequest;
use App\Http\Resources\People\OrganizationalUnitResource;
use App\Models\People\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrganizationalUnitController extends Controller
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

        $units = OrganizationalUnit::query()
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

        return OrganizationalUnitResource::collection($units);
    }

    public function store(
        StoreOrganizationalUnitRequest $request
    ): JsonResponse {
        $unit = OrganizationalUnit::create(
            $request->validated()
        );

        return (new OrganizationalUnitResource($unit))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        OrganizationalUnit $organizationalUnit
    ): OrganizationalUnitResource {
        return new OrganizationalUnitResource(
            $organizationalUnit
        );
    }

    public function update(
        UpdateOrganizationalUnitRequest $request,
        OrganizationalUnit $organizationalUnit
    ): OrganizationalUnitResource {
        $organizationalUnit->update(
            $request->validated()
        );

        return new OrganizationalUnitResource(
            $organizationalUnit->refresh()
        );
    }

    public function destroy(
        OrganizationalUnit $organizationalUnit
    ): Response|JsonResponse {
        if ($organizationalUnit->staffMembers()->exists()) {
            return response()->json([
                'message' => 'La unidad organizacional tiene personal asociado. Desactívela en lugar de eliminarla.',
            ], Response::HTTP_CONFLICT);
        }

        $organizationalUnit->delete();

        return response()->noContent();
    }
}
