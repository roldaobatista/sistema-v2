<?php

/**
 * Routes: Email Integration
 * Extracted from api.php - Phase 10 Modularization
 * Original lines: 2096-2183
 */

use App\Http\Controllers\Api\V1\Email\EmailAccountController;
use App\Http\Controllers\Api\V1\Email\EmailActivityController;
use App\Http\Controllers\Api\V1\Email\EmailController;
use App\Http\Controllers\Api\V1\Email\EmailNoteController;
use App\Http\Controllers\Api\V1\Email\EmailRuleController;
use App\Http\Controllers\Api\V1\Email\EmailSignatureController;
use App\Http\Controllers\Api\V1\Email\EmailTagController;
use App\Http\Controllers\Api\V1\Email\EmailTemplateController;
use Illuminate\Support\Facades\Route;

// â”€â”€â”€ EMAIL INTEGRATION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Inbox (view, read, star, archive, batch)
Route::middleware('check.permission:email.inbox.view')->group(function () {
    Route::get('emails', [EmailController::class, 'index']);
    Route::get('emails/stats', [EmailController::class, 'stats']);
    Route::get('emails/{email}', [EmailController::class, 'show']);
});
Route::middleware('check.permission:email.inbox.manage')->group(function () {
    Route::post('emails/{email}/toggle-star', [EmailController::class, 'toggleStar']);
    Route::post('emails/{email}/mark-read', [EmailController::class, 'markRead']);
    Route::post('emails/{email}/mark-unread', [EmailController::class, 'markUnread']);
    Route::post('emails/{email}/archive', [EmailController::class, 'archive']);
    Route::post('emails/{email}/link-entity', [EmailController::class, 'linkEntity']);
    Route::middleware('check.permission:admin.settings.manage')->post('emails/batch-action', [EmailController::class, 'batchAction']);
});
// Send / Reply / Forward
Route::middleware('check.permission:email.inbox.send')->group(function () {
    Route::post('emails/compose', [EmailController::class, 'compose']);
    Route::post('emails/{email}/reply', [EmailController::class, 'reply']);
    Route::post('emails/{email}/forward', [EmailController::class, 'forward']);
});
// Create task/chamado from email
Route::middleware('check.permission:email.inbox.create_task')->post('emails/{email}/create-task', [EmailController::class, 'createTask']);
// Email Accounts (admin)
Route::middleware('check.permission:email.account.view')->group(function () {
    Route::get('email-accounts', [EmailAccountController::class, 'index']);
    Route::get('email-accounts/{emailAccount}', [EmailAccountController::class, 'show']);
});
Route::middleware('check.permission:email.account.create')->post('email-accounts', [EmailAccountController::class, 'store']);
Route::middleware('check.permission:email.account.update')->group(function () {
    Route::put('email-accounts/{emailAccount}', [EmailAccountController::class, 'update']);
    Route::post('email-accounts/{emailAccount}/test-connection', [EmailAccountController::class, 'testConnection']);
});
Route::middleware('check.permission:email.account.sync')->post('email-accounts/{emailAccount}/sync', [EmailAccountController::class, 'syncNow']);
Route::middleware('check.permission:email.account.delete')->delete('email-accounts/{emailAccount}', [EmailAccountController::class, 'destroy']);
// Email Rules (automation)
Route::middleware('check.permission:email.rule.view')->group(function () {
    Route::get('email-rules', [EmailRuleController::class, 'index']);
    Route::get('email-rules/{emailRule}', [EmailRuleController::class, 'show']);
});
Route::middleware('check.permission:email.rule.create')->post('email-rules', [EmailRuleController::class, 'store']);
Route::middleware('check.permission:email.rule.update')->group(function () {
    Route::put('email-rules/{emailRule}', [EmailRuleController::class, 'update']);
    Route::post('email-rules/{emailRule}/toggle-active', [EmailRuleController::class, 'toggleActive']);
});
Route::middleware('check.permission:email.rule.delete')->delete('email-rules/{emailRule}', [EmailRuleController::class, 'destroy']);

// â”€â”€â”€ EMAIL ADVANCED (Phase 2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Templates
Route::middleware('check.permission:email.template.view')->group(function () {
    Route::get('email-templates', [EmailTemplateController::class, 'index']);
    Route::get('email-templates/{emailTemplate}', [EmailTemplateController::class, 'show']);
});
Route::middleware('check.permission:email.template.create')->post('email-templates', [EmailTemplateController::class, 'store']);
Route::middleware('check.permission:email.template.update')->put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update']);
Route::middleware('check.permission:email.template.delete')->delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy']);

// Signatures
Route::middleware('check.permission:email.signature.view')->get('email-signatures', [EmailSignatureController::class, 'index']);
Route::middleware('check.permission:email.signature.manage')->group(function () {
    Route::post('email-signatures', [EmailSignatureController::class, 'store']);
    Route::put('email-signatures/{emailSignature}', [EmailSignatureController::class, 'update']);
    Route::delete('email-signatures/{emailSignature}', [EmailSignatureController::class, 'destroy']);
});

// Tags
Route::middleware('check.permission:email.tag.view')->get('email-tags', [EmailTagController::class, 'index']);
Route::middleware('check.permission:email.tag.manage')->group(function () {
    Route::post('email-tags', [EmailTagController::class, 'store']);
    Route::put('email-tags/{emailTag}', [EmailTagController::class, 'update']);
    Route::delete('email-tags/{emailTag}', [EmailTagController::class, 'destroy']);
    Route::post('emails/{email}/tags/{emailTag}', [EmailTagController::class, 'toggleTag']);
});

// Notes
Route::middleware('check.permission:email.inbox.view')->get('emails/{email}/notes', [EmailNoteController::class, 'index']);
Route::middleware('check.permission:email.inbox.manage')->group(function () {
    Route::post('emails/{email}/notes', [EmailNoteController::class, 'store']);
    Route::delete('email-notes/{emailNote}', [EmailNoteController::class, 'destroy']);
});

// Activity / Assignment / Snooze
Route::middleware('check.permission:email.inbox.view')->get('emails/{email}/activities', [EmailActivityController::class, 'index']);
Route::middleware('check.permission:email.inbox.manage')->group(function () {
    Route::post('emails/{email}/assign', [EmailController::class, 'assign']);
    Route::post('emails/{email}/snooze', [EmailController::class, 'snooze']);
});
