<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DiaryController extends Controller
{
    /**
     * List diary entries (paginated, newest first).
     * Includes the associated three-coach feedback.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $entries = $user->diaryEntries()
            ->with('activityFeedback')
            ->paginate(20);

        return response()->json($entries);
    }
}
