<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\StoreTicketCategoryRequest;
use App\Http\Requests\Helpdesk\UpdateTicketCategoryRequest;
use App\Models\TicketCategory;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TicketCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = TicketCategory::with('slaPolicy')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($categories);
    }

    public function store(StoreTicketCategoryRequest $request)
    {
        $category = TicketCategory::create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->current_tenant_id,
        ]));

        return response()->json($category->load('slaPolicy'), 201);
    }

    public function show(TicketCategory $ticketCategory)
    {
        return response()->json($ticketCategory->load('slaPolicy'));
    }

    public function update(UpdateTicketCategoryRequest $request, TicketCategory $ticketCategory)
    {
        $ticketCategory->update($request->validated());

        return response()->json($ticketCategory->load('slaPolicy'));
    }

    public function destroy(TicketCategory $ticketCategory)
    {
        $ticketCategory->delete();

        return response()->noContent();
    }
}
