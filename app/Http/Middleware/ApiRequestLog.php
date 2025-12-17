<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Illuminate\Support\Facades\Log;

class ApiRequestLog {
  public function handle(Request $req, Closure $next) {
    $start = microtime(true);
    $res = $next($req);
    $ms = number_format((microtime(true) - $start)*1000,1);
    Log::info('API', ['m'=>$req->method(),'p'=>$req->path(),'s'=>$res->status(),'ms'=>$ms]);
    return $res;
  }
}
