<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer tools exist only on a laptop (local) and in the test suite.
 * Anywhere else the route does not exist: 404, not 403.
 */
final class LocalOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local', 'testing'), 404);

        return $next($request);
    }
}
