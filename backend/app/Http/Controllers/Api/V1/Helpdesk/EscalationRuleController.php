<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\StoreEscalationRuleRequest;
use App\Http\Requests\Helpdesk\UpdateEscalationRuleRequest;
use App\Models\EscalationRule;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class EscalationRuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = EscalationRule::with('slaPolicy')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($rules);
    }

    public function store(StoreEscalationRuleRequest $request)
    {
        $rule = EscalationRule::create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->current_tenant_id,
        ]));

        return response()->json($rule->load('slaPolicy'), 201);
    }

    public function show(EscalationRule $escalationRule)
    {
        return response()->json($escalationRule->load('slaPolicy'));
    }

    public function update(UpdateEscalationRuleRequest $request, EscalationRule $escalationRule)
    {
        $escalationRule->update($request->validated());

        return response()->json($escalationRule->load('slaPolicy'));
    }

    public function destroy(EscalationRule $escalationRule)
    {
        $escalationRule->delete();

        return response()->noContent();
    }
}
