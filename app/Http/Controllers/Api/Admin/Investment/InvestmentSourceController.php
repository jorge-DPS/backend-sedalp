<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\IndexInvestmentSourceRequest;
use App\Http\Requests\Investment\StoreInvestmentSourceRequest;
use App\Http\Requests\Investment\UpdateInvestmentSourceRequest;
use App\Http\Resources\Investment\InvestmentSourceResource;
use App\Models\Investment\InvestmentSource;
use App\Services\Investment\InvestmentSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvestmentSourceController extends Controller
{
    public function __construct(private readonly InvestmentSourceService $sources) {}

    public function index(IndexInvestmentSourceRequest $request): AnonymousResourceCollection
    {
        return InvestmentSourceResource::collection($this->sources->paginate($request->validated()));
    }

    public function store(StoreInvestmentSourceRequest $request): JsonResponse
    {
        return (new InvestmentSourceResource($this->sources->create($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(InvestmentSource $source): InvestmentSourceResource
    {
        return new InvestmentSourceResource($source->load('institution'));
    }

    public function update(UpdateInvestmentSourceRequest $request, InvestmentSource $source): InvestmentSourceResource
    {
        return new InvestmentSourceResource($this->sources->update($source, $request->validated()));
    }
}
