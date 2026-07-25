<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users, optionally filtered by role. GET /api/admin/users?role=
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * Activate or deactivate a user account. PATCH /api/admin/users/{user}/toggle-active
     */
    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'User activated.' : 'User deactivated.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * There's no email service configured to power a self-service "forgot
     * password" flow, so a user who's locked out asks an admin, who resets
     * it here directly. PATCH /api/admin/users/{user}/reset-password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $fields = $request->validate(['new_password' => ['required', 'string', 'min:8']]);

        $user->update(['password' => Hash::make($fields['new_password'])]);

        return response()->json(['message' => "Password reset for {$user->name}."]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
