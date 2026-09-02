<?php

namespace App\Http\Controllers\Api\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\UpdateStaffMemberStatusRequest;
use App\Http\Resources\People\StaffMemberResource;
use App\Models\People\StaffMember;
use App\Services\People\StaffMemberStatusService;

class StaffMemberStatusController extends Controller
{
    public function __construct(
        private readonly StaffMemberStatusService $staffMemberStatusService
    ) {}

    public function __invoke(
        UpdateStaffMemberStatusRequest $request,
        StaffMember $staffMember
    ): StaffMemberResource {
        $staffMember = $this->staffMemberStatusService
            ->updateStatus(
                actor: $request->user('api'),
                staffMember: $staffMember,
                active: $request->boolean('active'),
                reason: $request->validated('reason'),
            );

        return new StaffMemberResource($staffMember);
    }
}
