<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\RedeemLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app door for a sign-in link from /login in chat: the code becomes an
 * API token, in the same shape as password login.
 */
final class MagicLoginController extends Controller
{
    public function __construct(
        private readonly RedeemLoginCode $redeem,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:32'],
            'device_name' => ['sometimes', 'string', 'max:60'],
        ]);

        $user = $this->redeem->handle($request->string('token')->value());

        if ($user === null) {
            return response()->json(['message' => 'That sign-in link has expired or was already used. Send /login to the bot for a new one.'], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->string('device_name', 'feetness-app')->value())->plainTextToken,
        ]);
    }
}
