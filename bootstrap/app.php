<?php
use App\Console\Commands\BootstrapAdmin;
use App\Http\Middleware\BearerOperator;
use App\Support\ApiError;
use App\Application\BusinessFailure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1'
    )
    ->withCommands([BootstrapAdmin::class])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['operator' => BearerOperator::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->is('health')) {
                return null;
            }
            if ($e instanceof BusinessFailure) {
                $status = match ($e->errorCode) {
                    'NOT_FOUND' => 404,
                    'INVALID_CREDENTIALS' => 401,
                    'USERNAME_TAKEN' => 409,
                    'MEMBER_HAS_OVERDUE', 'BOOK_NOT_AVAILABLE', 'ITEM_ALREADY_RETURNED', 'STOCK_CONFLICT' => 409,
                    default => 422,
                };
                return response()->json([
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'details' => $e->details,
                ], $status);
            }
            if ($e instanceof ApiError) {
                return response()->json(['code' => $e->errorCode, 'message' => $e->getMessage(), 'details' => $e->details], $e->httpStatus);
            }
            if ($e instanceof \Illuminate\Database\QueryException && $e->getCode() === '23505') {
                return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'A unique value already exists.', 'details' => []], 409);
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json(['code' => 'VALIDATION_ERROR', 'message' => 'Invalid request.', 'details' => collect($e->errors())->map(fn($issues,$field) => ['field'=>$field,'issue'=>implode('; ',$issues)])->values()->all()], 422);
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                return response()->json(['code' => match ($status) { 401 => 'INVALID_TOKEN',403 => 'FORBIDDEN_ROLE',404 => 'NOT_FOUND',default => 'VALIDATION_ERROR' }, 'message' => 'Request cannot be completed.', 'details' => []], $status);
            }
            report($e);
            return response()->json(['code'=>'INTERNAL_ERROR','message'=>'An unexpected error occurred.','details'=>[]],500);
        });
    })
    ->create();
