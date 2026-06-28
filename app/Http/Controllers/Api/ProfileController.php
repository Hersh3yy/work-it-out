<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateProfileTrainerRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = $data['password'];
            unset($data['current_password']);
        } else {
            unset($data['password'], $data['current_password']);
        }

        $request->user()->update($data);

        return new UserResource($request->user()->fresh());
    }

    public function updateTrainer(UpdateProfileTrainerRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->fresh());
    }
}
