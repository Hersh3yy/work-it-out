<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Stats\PersonalRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomRpgStatResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real, computed fitness stats — personal records derived from logged data
 * plus the gamified RPG stats. Everything the frontend needs to render a
 * stats screen in one call.
 */
final class StatsController extends Controller
{
    public function __construct(
        private readonly PersonalRecords $records,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'records' => $this->records->for($user),
            'rpg' => [
                'strength' => (int) $user->rpg_strength,
                'stamina' => (int) $user->rpg_stamina,
                'vitality' => (int) $user->rpg_vitality,
            ],
            'custom_stats' => CustomRpgStatResource::collection($user->customRpgStats),
        ]);
    }
}
