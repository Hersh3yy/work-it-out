<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Limitations\ResolveLimitation;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\LimitationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Profile\ProfileFacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the app shows: the person, their computed facts, the written notes,
 * active limitations and their messages. Same data as /me in chat.
 */
final class OverviewController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $version = $user->profileVersions()->first();

        return response()->json([
            'user' => new UserResource($user),
            'facts' => ProfileFacts::for($user)->toArray(),
            'notes' => [
                'updated_at' => $version?->created_at?->toIso8601String(),
                'kind' => $version?->kind,
                'items' => $version?->notes ?? [],
            ],
            'limitations' => LimitationResource::collection($user->limitations()->active()->latest('started_on')->get()),
            'recent_logs' => ActivityLogResource::collection(
                $user->activityLogs()->with(['exerciseEntries.setRows', 'limitation'])->limit(10)->get(),
            ),
        ]);
    }

    public function logs(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ActivityLogResource::collection(
            $user->activityLogs()->with(['exerciseEntries.setRows', 'limitation'])->paginate(20),
        );
    }

    public function resolveLimitation(Request $request, int $limitation, ResolveLimitation $resolve): Response
    {
        /** @var User $user */
        $user = $request->user();

        $resolve->handle($user->limitations()->active()->findOrFail($limitation));

        return response()->noContent();
    }
}
