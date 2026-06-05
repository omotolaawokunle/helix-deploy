<?php

use App\Exceptions\HelixExceptionRenderer;
use App\Http\Middleware\AttachRequestId;
use App\Modules\Commands\Exceptions\DangerousCommandException;
use App\Modules\Credentials\Exceptions\CredentialAccessDeniedException;
use App\Modules\CronJobs\Exceptions\InvalidCronExpressionException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Packages\SSH\Exceptions\SSHConnectionException;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Modules/Audit/Commands',
        __DIR__.'/../app/Modules/Credentials/Commands',
        __DIR__.'/../app/Modules/CronJobs/Commands',
        __DIR__.'/../app/Modules/Deployments/Commands',
        __DIR__.'/../app/Modules/Servers/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(prepend: [
            AttachRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidCronExpressionException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => [
                        'expression' => [$exception->getMessage()],
                    ],
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (SSHFingerprintMismatchException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->render($exception, $request);
        });

        $exceptions->render(function (SSHConnectionException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->renderSshConnection($exception, $request);
        });

        $exceptions->render(function (DangerousCommandException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->renderDangerousCommand($exception, $request);
        });

        $exceptions->render(function (CredentialAccessDeniedException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->renderCredentialAccessDenied($exception, $request);
        });

        $exceptions->render(function (ReleaseNotFoundException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->renderReleaseNotFound($exception, $request);
        });

        $exceptions->render(function (\LogicException $exception, Request $request) {
            return app(HelixExceptionRenderer::class)->renderAuditLogImmutable($exception, $request);
        });
    })->create();
