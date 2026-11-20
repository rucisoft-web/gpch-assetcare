<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Pelanggaran aturan alur kerja → pesan ramah (tanpa stack trace)
        $exceptions->render(function (\App\Services\WorkflowException $e, Request $request) {
            $code = in_array($e->getCode(), [403, 409, 422], true) ? $e->getCode() : 422;
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'request_id' => $request->attributes->get('request_id')], $code);
            }

            return back()->withErrors(['workflow' => $e->getMessage()]);
        });
    })->create();
