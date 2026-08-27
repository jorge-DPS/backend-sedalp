<?php

namespace App\Http\Controllers\Api\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\StoreStaffMemberRequest;
use App\Http\Requests\People\UpdateStaffMemberRequest;
use App\Http\Resources\People\StaffMemberResource;
use App\Models\People\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StaffMemberController extends Controller
{
    private const RELATIONS = [
        'organizationalUnit',
        'position',
        'profession',
        'user',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100
        );

        $search = trim(
            (string) $request->query('search', '')
        );

        $staff = StaffMember::query()
            ->with(self::RELATIONS)

            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'first_names',
                                    'ILIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'paternal_surname',
                                    'ILIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'maternal_surname',
                                    'ILIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'ci',
                                    'ILIKE',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'ILIKE',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->when(
                $request->filled('organizational_unit_id'),
                fn ($query) => $query->where(
                    'organizational_unit_id',
                    $request->integer('organizational_unit_id')
                )
            )

            ->when(
                $request->filled('position_id'),
                fn ($query) => $query->where(
                    'position_id',
                    $request->integer('position_id')
                )
            )

            ->when(
                $request->filled('profession_id'),
                fn ($query) => $query->where(
                    'profession_id',
                    $request->integer('profession_id')
                )
            )

            ->when(
                $request->has('active'),
                fn ($query) => $query->where(
                    'active',
                    $request->boolean('active')
                )
            )

            ->orderBy('paternal_surname')
            ->orderBy('first_names')

            ->paginate($perPage)
            ->withQueryString();

        return StaffMemberResource::collection($staff);
    }

    public function store(
        StoreStaffMemberRequest $request
    ): JsonResponse {
        $staffMember = StaffMember::create(
            $request->validated()
        );

        $staffMember->load(self::RELATIONS);

        return (new StaffMemberResource($staffMember))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        StaffMember $staffMember
    ): StaffMemberResource {
        return new StaffMemberResource(
            $staffMember->load(self::RELATIONS)
        );
    }

    public function update(
        UpdateStaffMemberRequest $request,
        StaffMember $staffMember
    ): StaffMemberResource {
        $staffMember->update(
            $request->validated()
        );

        return new StaffMemberResource(
            $staffMember
                ->refresh()
                ->load(self::RELATIONS)
        );
    }

    public function destroy(
        StaffMember $staffMember
    ): Response|JsonResponse {
        if ($staffMember->user()->exists()) {
            return response()->json([
                'message' => 'El personal tiene una cuenta de usuario asociada. Desactive o elimine primero la cuenta.',
            ], Response::HTTP_CONFLICT);
        }

        $staffMember->delete();

        return response()->noContent();
    }
}
