<?php
namespace App\Http\Middleware;
use App\Support\ApiError;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
class BearerOperator {
    public function handle(Request $request, Closure $next, string $role='operator') {
        $token=$request->bearerToken();
        if(!$token) throw new ApiError('INVALID_TOKEN',401,'Bearer token is required.');
        $user=Jwt::verify($token);
        if($role==='admin' && $user['role']!=='admin') throw new ApiError('FORBIDDEN_ROLE',403,'Insufficient permissions.');
        $request->attributes->set('operator',$user);
        return $next($request);
    }
}
