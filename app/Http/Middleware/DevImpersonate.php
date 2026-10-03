<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 開発用: ?as={userId} または X-Dev-User ヘッダーで、そのリクエストだけ指定ユーザーとして認証する。
 * セッションを使わないので、同じブラウザの別タブで別ユーザーとして同時に操作できる。localのみ有効。
 */
class DevImpersonate
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            $id = $request->header('X-Dev-User') ?? $request->input('as');
            if ($id !== null && ctype_digit((string) $id)) {
                Auth::onceUsingId((int) $id);
            }
        }

        return $next($request);
    }
}
