<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\StoreJourneyPolicyRequest;
use App\Http\Requests\Journey\UpdateJourneyPolicyRequest;
use App\Http\Resources\Journey\JourneyPolicyResource;
use App\Models\JourneyRule;
use App\Support\ApiResponse;

class JourneyPolicyController extends Controller
{
    /**
     * @return mixed
     */
    public function index()
    {
        $paginator = JourneyRule::active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator, resourceClass: JourneyPolicyResource::class);
    }

    /**
     * @return mixed
     */
    public function show(JourneyRule $journeyPolicy)
    {
        return new JourneyPolicyResource($journeyPolicy);
    }

    /**
     * @return mixed
     */
    public function store(StoreJourneyPolicyRequest $request)
    {
        if ($request->boolean('is_default')) {
            JourneyRule::where('is_default', true)->update(['is_default' => false]);
        }

        $rule = JourneyRule::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->current_tenant_id,
        ]);

        return (new JourneyPolicyResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return mixed
     */
    public function update(UpdateJourneyPolicyRequest $request, JourneyRule $journeyPolicy)
    {
        if ($request->boolean('is_default') && ! $journeyPolicy->is_default) {
            JourneyRule::where('is_default', true)
                ->where('id', '!=', $journeyPolicy->id)
                ->update(['is_default' => false]);
        }

        $journeyPolicy->update($request->validated());

        return new JourneyPolicyResource($journeyPolicy->fresh());
    }

    /**
     * @return mixed
     */
    public function destroy(JourneyRule $journeyPolicy)
    {
        $journeyPolicy->update(['is_active' => false]);
        $journeyPolicy->delete();

        return response()->noContent();
    }
}
