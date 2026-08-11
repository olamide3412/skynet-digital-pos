<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'admin'          => AdminMiddleware::class,
            'auth'           => Authenticate::class,
            'guest'          => RedirectIfAuthenticated::class,
            'pos.role'       => \App\Http\Middleware\PosRoleMiddleware::class,
            'superadmin'     => \App\Http\Middleware\SuperAdminMiddleware::class,
            'resolve.branch' => \App\Http\Middleware\ResolveBranchMiddleware::class,
            'branch.scope'   => \App\Http\Middleware\BranchScopeMiddleware::class,
            'role'           => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'     => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except:[
            'flw-webhook',
            'webhook/paystack',
            'webhook/flutterwave'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            $status = $response->getStatusCode();

            // Only intercept standard HTTP error codes for Inertia/web requests
            if (in_array($status, [403, 404, 500, 503]) && !$request->expectsJson()) {
                return \Inertia\Inertia::render('Error', [
                    'status'  => $status,
                    'message' => match($status) {
                        403 => $e->getMessage() ?: 'You do not have permission to access this page.',
                        404 => 'The page you are looking for could not be found.',
                        500 => 'An unexpected server error occurred. Please try again.',
                        503 => 'The service is temporarily unavailable. Please try again later.',
                        default => 'An error occurred.',
                    },
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
