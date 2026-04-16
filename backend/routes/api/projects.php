<?php

use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMilestoneController;
use App\Http\Controllers\Api\V1\Projects\ProjectResourceController;
use App\Http\Controllers\Api\V1\Projects\ProjectTimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.tenant'])->group(function () {
    Route::prefix('projects')->group(function () {
        Route::middleware('check.permission:projects.dashboard.view')->get('/dashboard', [ProjectController::class, 'dashboard']);
        Route::middleware('check.permission:projects.project.view')->get('/', [ProjectController::class, 'index']);
        Route::middleware('check.permission:projects.project.create')->post('/', [ProjectController::class, 'store']);
        Route::middleware('check.permission:projects.project.update')->post('/{project}/start', [ProjectController::class, 'start']);
        Route::middleware('check.permission:projects.project.update')->post('/{project}/pause', [ProjectController::class, 'pause']);
        Route::middleware('check.permission:projects.project.update')->post('/{project}/resume', [ProjectController::class, 'resume']);
        Route::middleware('check.permission:projects.project.update')->post('/{project}/complete', [ProjectController::class, 'complete']);
        Route::middleware('check.permission:projects.project.view')->get('/{project}/gantt', [ProjectController::class, 'gantt']);
        Route::middleware('check.permission:projects.milestone.manage')->get('/{project}/milestones', [ProjectMilestoneController::class, 'index']);
        Route::middleware('check.permission:projects.milestone.manage')->post('/{project}/milestones', [ProjectMilestoneController::class, 'store']);
        Route::middleware('check.permission:projects.milestone.manage')->put('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update']);
        Route::middleware('check.permission:projects.milestone.manage')->delete('/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy']);
        Route::middleware('check.permission:projects.milestone.complete')->post('/{project}/milestones/{milestone}/complete', [ProjectMilestoneController::class, 'complete']);
        Route::middleware('check.permission:projects.invoice.generate')->post('/{project}/milestones/{milestone}/invoice', [ProjectMilestoneController::class, 'generateInvoice']);
        Route::middleware('check.permission:projects.resource.manage')->get('/{project}/resources', [ProjectResourceController::class, 'index']);
        Route::middleware('check.permission:projects.resource.manage')->post('/{project}/resources', [ProjectResourceController::class, 'store']);
        Route::middleware('check.permission:projects.resource.manage')->put('/{project}/resources/{resource}', [ProjectResourceController::class, 'update']);
        Route::middleware('check.permission:projects.resource.manage')->delete('/{project}/resources/{resource}', [ProjectResourceController::class, 'destroy']);
        Route::middleware('check.permission:projects.time_entry.view')->get('/{project}/time-entries', [ProjectTimeEntryController::class, 'index']);
        Route::middleware('check.permission:projects.time_entry.create')->post('/{project}/time-entries', [ProjectTimeEntryController::class, 'store']);
        Route::middleware('check.permission:projects.time_entry.create')->put('/{project}/time-entries/{timeEntry}', [ProjectTimeEntryController::class, 'update']);
        Route::middleware('check.permission:projects.time_entry.create')->delete('/{project}/time-entries/{timeEntry}', [ProjectTimeEntryController::class, 'destroy']);
        Route::middleware('check.permission:projects.project.view')->get('/{project}', [ProjectController::class, 'show']);
        Route::middleware('check.permission:projects.project.update')->put('/{project}', [ProjectController::class, 'update']);
        Route::middleware('check.permission:projects.project.delete')->delete('/{project}', [ProjectController::class, 'destroy']);
    });
});
