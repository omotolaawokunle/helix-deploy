<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Organizations\Controllers\OrganizationController;
use App\Modules\Organizations\Controllers\OrganizationMemberController;
use App\Modules\Provisioning\Controllers\ProvisioningController;
use App\Modules\Servers\Controllers\ServerController;
use App\Modules\Deployments\Controllers\DeploymentController;
use App\Modules\Deployments\Controllers\DeploymentStreamController;
use App\Modules\CronJobs\Controllers\CronJobController;
use App\Modules\Daemons\Controllers\DaemonController;
use App\Modules\Sites\Controllers\EnvVarController;
use App\Modules\Sites\Controllers\NginxConfigController;
use App\Modules\Sites\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->middleware('web')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
    });

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

Route::middleware(['web', 'auth:sanctum', 'verified'])->prefix('v1')->group(function (): void {
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{org}', [OrganizationController::class, 'show']);
    Route::patch('/organizations/{org}', [OrganizationController::class, 'update']);
    Route::get('/organizations/{org}/members', [OrganizationMemberController::class, 'index']);
    Route::post('/organizations/{org}/invitations', [OrganizationMemberController::class, 'invite']);
    Route::patch('/organizations/{org}/members/{user}', [OrganizationMemberController::class, 'update']);
    Route::delete('/organizations/{org}/members/{user}', [OrganizationMemberController::class, 'destroy']);
    Route::post('/organizations/{org}/switch', [OrganizationController::class, 'switchOrganization']);
    Route::get('/organizations/{org}/servers', [ServerController::class, 'index']);
    Route::post('/organizations/{org}/servers', [ServerController::class, 'store']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    Route::patch('/servers/{server}', [ServerController::class, 'update']);
    Route::delete('/servers/{server}', [ServerController::class, 'destroy']);
    Route::post('/servers/{server}/test-connection', [ServerController::class, 'testConnection']);
    Route::post('/servers/{server}/provision', [ProvisioningController::class, 'provision']);
    Route::get('/organizations/{org}/sites', [SiteController::class, 'index']);
    Route::get('/servers/{server}/sites', [SiteController::class, 'indexForServer']);
    Route::post('/servers/{server}/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site}', [SiteController::class, 'show']);
    Route::delete('/sites/{site}', [SiteController::class, 'destroy']);
    Route::get('/sites/{site}/nginx-config', [NginxConfigController::class, 'show']);
    Route::put('/sites/{site}/nginx-config', [NginxConfigController::class, 'update']);
    Route::get('/sites/{site}/env-vars', [EnvVarController::class, 'index']);
    Route::post('/sites/{site}/env-vars', [EnvVarController::class, 'store']);
    Route::patch('/sites/{site}/env-vars/{credential}', [EnvVarController::class, 'update']);
    Route::delete('/sites/{site}/env-vars/{credential}', [EnvVarController::class, 'destroy']);
    Route::get('/sites/{site}/env-vars/{credential}/reveal', [EnvVarController::class, 'reveal']);
    Route::post('/sites/{site}/env-vars/sync', [EnvVarController::class, 'sync']);
    Route::get('/servers/{server}/cron-jobs', [CronJobController::class, 'index']);
    Route::post('/servers/{server}/cron-jobs', [CronJobController::class, 'store']);
    Route::patch('/servers/{server}/cron-jobs/{cronJob}', [CronJobController::class, 'update']);
    Route::delete('/servers/{server}/cron-jobs/{cronJob}', [CronJobController::class, 'destroy']);
    Route::post('/servers/{server}/cron-jobs/{cronJob}/toggle', [CronJobController::class, 'toggle']);
    Route::post('/servers/{server}/cron-jobs/sync', [CronJobController::class, 'sync']);
    Route::get('/servers/{server}/daemons', [DaemonController::class, 'index']);
    Route::post('/servers/{server}/daemons', [DaemonController::class, 'store']);
    Route::post('/servers/{server}/daemons/{daemon}/restart', [DaemonController::class, 'restart']);
    Route::post('/servers/{server}/daemons/{daemon}/start', [DaemonController::class, 'start']);
    Route::post('/servers/{server}/daemons/{daemon}/stop', [DaemonController::class, 'stop']);
    Route::delete('/servers/{server}/daemons/{daemon}', [DaemonController::class, 'destroy']);
    Route::post('/sites/{site}/deployments', [DeploymentController::class, 'store']);
    Route::get('/deployments/{deployment}', [DeploymentController::class, 'show']);
    Route::post('/deployments/{deployment}/rollback', [DeploymentController::class, 'rollback']);
    Route::get('/deployments/{deployment}/stream', [DeploymentStreamController::class, 'stream']);
});

Route::get('/v1/organizations/invitations/accept', function () {
    return response()->json([
        'message' => 'Invitation acceptance flow is not implemented yet.',
    ], 501);
})->name('organizations.invitations.accept');
