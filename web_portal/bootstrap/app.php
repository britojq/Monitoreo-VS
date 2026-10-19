<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        $middleware->web(append: [
            \App\Http\Middleware\CheckBannedIp::class,
            \App\Http\Middleware\EnsureStatelessTelemetry::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'cluster.token' => \App\Http\Middleware\ValidateClusterToken::class,
            'node.master' => \App\Http\Middleware\EnsureMasterNode::class,
            'telemetry.stateless' => \App\Http\Middleware\EnsureStatelessTelemetry::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $path = '/' . ltrim($request->path(), '/');
            $isTelemetry = str_contains($path, '/live') ||
                           str_contains($path, '/quick-status') ||
                           str_contains($path, '/top-talkers') ||
                           str_contains($path, '/telemetry');

            if ($request->hasSession()) {
                $intended = $request->session()->get('url.intended');
                if ($intended && (
                    str_contains($intended, '/live') ||
                    str_contains($intended, '/quick-status') ||
                    str_contains($intended, '/top-talkers') ||
                    str_contains($intended, '/telemetry')
                )) {
                    $request->session()->forget('url.intended');
                }
                if ($isTelemetry) {
                    $request->session()->forget('url.intended');
                }
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'success' => false,
                    'unauthenticated' => true,
                ], 401);
            }

            if ($isTelemetry) {
                return redirect()->route('login');
            }
        });
    })->create();
