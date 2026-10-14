<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
final class Jwt {
    private static function encode(array $v): string {return rtrim(strtr(base64_encode(json_encode($v,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');}
    private static function decode(string $v): ?array { $raw=base64_decode(strtr($v,'-_','+/'),true);$data=$raw===false?null:json_decode($raw,true);return is_array($data)?$data:null; }
    private static function secret(): string { $s=(string)env('JWT_SECRET',''); if(strlen($s)<32) throw new \RuntimeException('JWT_SECRET must contain at least 32 characters.');return $s;}
    public static function issue(string $id, string $role): string {
        $h=self::encode(['alg'=>'HS256','typ'=>'JWT']);$p=self::encode(['sub'=>$id,'role'=>$role,'iat'=>time(),'exp'=>time()+28800]);
        $sig=rtrim(strtr(base64_encode(hash_hmac('sha256',"$h.$p",self::secret(),true)),'+/','-_'),'=');
        return "$h.$p.$sig";
    }
    public static function verify(string $token): array {
        $parts=explode('.',$token);
        if(count($parts)!==3) throw new ApiError('INVALID_TOKEN',401,'Invalid or expired bearer token.');
        [$h,$p,$sig]=$parts;$header=self::decode($h);$payload=self::decode($p);
        $expected=rtrim(strtr(base64_encode(hash_hmac('sha256',"$h.$p",self::secret(),true)),'+/','-_'),'=');
        if(!$header || $header['alg']!=='HS256' || !hash_equals($expected,$sig) || !$payload || !isset($payload['sub'],$payload['role'],$payload['exp']) || !is_numeric($payload['exp']) || $payload['exp']<=time()) throw new ApiError('INVALID_TOKEN',401,'Invalid or expired bearer token.');
        $user=DB::table('library.user')->where('id',$payload['sub'])->first(['id','username','role']);
        if(!$user || $user->role!==$payload['role']) throw new ApiError('INVALID_TOKEN',401,'Invalid or expired bearer token.');
        return ['id'=>$user->id,'role'=>$user->role,'username'=>$user->username];
    }
}
