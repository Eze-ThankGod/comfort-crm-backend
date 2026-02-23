<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationRuleController;
use App\Http\Controllers\Api\CallLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Comfort CRM
|--------------------------------------------------------------------------
*/

// ─── Public Routes ──────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/login',   [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // WhatsApp Webhook (no auth – verified by token)
    Route::match(['get', 'post'], '/webhooks/whatsapp', [WhatsAppController::class, 'webhook']);

    // ─── Protected Routes ────────────────────────────────────────────────────
    Route::middleware(['auth:api', 'active'])->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me',      [AuthController::class, 'me']);

        // ── Dashboard ─────────────────────────────────────────────────────────
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // ── Leads ─────────────────────────────────────────────────────────────
        Route::get   ('/leads',               [LeadController::class, 'index']);
        Route::post  ('/leads',               [LeadController::class, 'store']);
        Route::post  ('/leads/import',        [LeadController::class, 'import']);
        Route::post  ('/leads/bulk-assign',   [LeadController::class, 'bulkAssign']);
        Route::get   ('/leads/{lead}',        [LeadController::class, 'show']);
        Route::put   ('/leads/{lead}',        [LeadController::class, 'update']);
        Route::patch ('/leads/{lead}',        [LeadController::class, 'update']);
        Route::delete('/leads/{lead}',        [LeadController::class, 'destroy']);
        Route::patch ('/leads/{lead}/assign', [LeadController::class, 'assign']);

        // ── Tasks ─────────────────────────────────────────────────────────────
        Route::get   ('/tasks',                    [TaskController::class, 'index']);
        Route::post  ('/tasks',                    [TaskController::class, 'store']);
        Route::get   ('/tasks/{task}',             [TaskController::class, 'show']);
        Route::put   ('/tasks/{task}',             [TaskController::class, 'update']);
        Route::patch ('/tasks/{task}',             [TaskController::class, 'update']);
        Route::delete('/tasks/{task}',             [TaskController::class, 'destroy']);
        Route::patch ('/tasks/{task}/complete',    [TaskController::class, 'complete']);

        // ── Call Logs ─────────────────────────────────────────────────────────
        Route::get   ('/call-logs',            [CallLogController::class, 'index']);
        Route::post  ('/call-logs',            [CallLogController::class, 'store']);
        Route::get   ('/call-logs/{callLog}',  [CallLogController::class, 'show']);
        Route::put   ('/call-logs/{callLog}',  [CallLogController::class, 'update']);
        Route::patch ('/call-logs/{callLog}',  [CallLogController::class, 'update']);
        Route::delete('/call-logs/{callLog}',  [CallLogController::class, 'destroy']);

        // ── Activities ────────────────────────────────────────────────────────
        Route::get('/activities', [ActivityController::class, 'index']);

        // ── Notifications ─────────────────────────────────────────────────────
        Route::get   ('/notifications',                  [NotificationController::class, 'index']);
        Route::get   ('/notifications/unread',           [NotificationController::class, 'unread']);
        Route::patch ('/notifications/read-all',         [NotificationController::class, 'markAllAsRead']);
        Route::patch ('/notifications/{id}/read',        [NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{id}',             [NotificationController::class, 'destroy']);

        // ── WhatsApp ──────────────────────────────────────────────────────────
        Route::post('/whatsapp/send',              [WhatsAppController::class, 'send']);
        Route::get ('/whatsapp/history/{lead}',    [WhatsAppController::class, 'history']);

        // ── Admin / Manager Only Routes ───────────────────────────────────────
        Route::middleware(['role:admin,manager'])->group(function () {

            // Users
            Route::get   ('/users',                    [UserController::class, 'index']);
            Route::post  ('/users',                    [UserController::class, 'store']);
            Route::get   ('/users/agents',             [UserController::class, 'agents']);
            Route::get   ('/users/{user}',             [UserController::class, 'show']);
            Route::put   ('/users/{user}',             [UserController::class, 'update']);
            Route::patch ('/users/{user}',             [UserController::class, 'update']);
            Route::delete('/users/{user}',             [UserController::class, 'destroy']);
            Route::patch ('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('/agent-performance', [ReportController::class, 'agentPerformance']);
                Route::get('/leads-by-source',   [ReportController::class, 'leadsBySource']);
                Route::get('/call-outcomes',     [ReportController::class, 'callOutcomes']);
                Route::get('/task-completion',   [ReportController::class, 'taskCompletion']);
                Route::get('/lead-funnel',       [ReportController::class, 'leadFunnel']);
                Route::get('/calls-over-time',   [ReportController::class, 'callsOverTime']);
            });

            // Automation Rules
            Route::get   ('/automation-rules',               [AutomationRuleController::class, 'index']);
            Route::post  ('/automation-rules',               [AutomationRuleController::class, 'store']);
            Route::get   ('/automation-rules/{automationRule}', [AutomationRuleController::class, 'show']);
            Route::put   ('/automation-rules/{automationRule}', [AutomationRuleController::class, 'update']);
            Route::patch ('/automation-rules/{automationRule}', [AutomationRuleController::class, 'update']);
            Route::delete('/automation-rules/{automationRule}', [AutomationRuleController::class, 'destroy']);
            Route::patch ('/automation-rules/{automationRule}/toggle', [AutomationRuleController::class, 'toggle']);
        });
    });
});
