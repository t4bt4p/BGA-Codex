<?php

use App\Http\Middleware\EnsureAdmin;
use App\Services\SecretRedactor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (ngrok/production load balancer) so generated
        // asset URLs use the public HTTPS scheme instead of HTTP.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['admin' => EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['User_password', 'POLYGON_PRIVATE_KEY', 'OPN_SECRET_KEY']);
        $exceptions->report(function (Throwable $error) {
            Log::error(
                app(SecretRedactor::class)->redact($error->getMessage()),
                ['exception_type' => get_class($error)],
            );

            return false;
        });
        $exceptions->render(function (Throwable $error, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($error instanceof ValidationException) {
                return null;
            }
            if ($error instanceof \App\Exceptions\WalletBalanceCorrected) {
                return response()->json([
                    'code' => 'wallet_balance_corrected',
                    'message' => $error->getMessage(),
                    'balance' => $error->balance,
                ], 409);
            }
            if ($error instanceof \App\Exceptions\TransactionDataRestored) {
                return response()->json(['code' => 'transaction_data_restored', 'message' => $error->getMessage()], 409);
            }
            if ($error instanceof AuthenticationException) {
                return response()->json(['message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ'], 401);
            }
            if ($error instanceof HttpExceptionInterface) {
                $status = $error->getStatusCode();
                $fallback = Response::$statusTexts[$status] ?? 'ไม่สามารถทำรายการได้';
                $message = $status === 500 ? 'ไม่สามารถทำรายการได้ กรุณาลองใหม่หรือติดต่อผู้ดูแล' : ($error->getMessage() ?: $fallback);

                return response()->json(['message' => app(SecretRedactor::class)->redact($message)], $status, $error->getHeaders());
            }

            return response()->json(['message' => 'ไม่สามารถทำรายการได้ กรุณาลองใหม่หรือติดต่อผู้ดูแล'], 500);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
