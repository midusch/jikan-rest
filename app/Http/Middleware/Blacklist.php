<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Blacklist
{
    private $blacklist = [];

    public function handle(Request $request, Closure $next)
    {
        if (app('redis')->exists("blacklist:{$request->getClientIp()}")) {
            return response()
                ->json([
                    'status' => 403,
                    'type' => null,
                    'message' => 'You have been blocked from the service for breaching Terms of Use',
                    'error' => null
                ], 403);
        }

        return $next($request);
    }

    public static function loadList()
    {
        $blacklist = Redis::keys("blacklist:*");
        if (!empty($blacklist)) {
            return;
        }

        if (!file_exists(BLACKLIST_PATH)) {
            file_put_contents(BLACKLIST_PATH, json_encode([]));
        }

        //self::reloadList();
    }

    public static function reloadList()
    {
        self::flushList();

        $blacklist = json_decode(file_get_contents(BLACKLIST_PATH), true);
        if (empty($blacklist)) {
            return;
        }

        foreach ($blacklist as $ip) {
            Redis::set("blacklist:{$ip}", 0);
        }
    }

    public static function flushList()
    {
        $process = Process::fromShellCommandline('redis-cli --scan --pattern blacklist:* | xargs redis-cli del');
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
