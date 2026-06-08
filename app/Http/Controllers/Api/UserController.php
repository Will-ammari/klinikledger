<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeUserRoleRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'clinic_id' => $request->user()->clinic_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::from($validated['role']),
            'status' => UserStatus::from($validated['status'] ?? UserStatus::Active->value),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::UserCreated,
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
            request: $request
        );

        return response()->json([
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::UserViewed,
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $before = $user->only([
            'name',
            'email',
            'status',
        ]);

        $user->update($request->validated());

        $after = $user->fresh()->only([
            'name',
            'email',
            'status',
        ]);

        $changedFields = collect($before)
            ->filter(function ($oldValue, string $field) use ($after) {
                return (string) $oldValue !== (string) ($after[$field] ?? null);
            })
            ->keys()
            ->values()
            ->all();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::UserUpdated,
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
                'changed_fields' => $changedFields,
            ],
            request: $request
        );

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->role === UserRole::OwnerClinic) {
            $ownerCount = User::query()
                ->where('clinic_id', $user->clinic_id)
                ->where('role', UserRole::OwnerClinic->value)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['You cannot delete the last clinic owner.'],
                ]);
            }
        }

        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $targetUserId = $user->id;
        $targetUserRole = $user->role->value;

        $user->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::UserDeleted,
            auditable: $user,
            metadata: [
                'target_user_id' => $targetUserId,
                'role' => $targetUserRole,
                'deletion_type' => 'hard_delete',
            ],
            request: $request
        );

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    public function changeRole(ChangeUserRoleRequest $request, User $user)
    {
        $validated = $request->validated();

        $oldRole = $user->role;

        if ($user->role === UserRole::OwnerClinic && $validated['role'] !== UserRole::OwnerClinic->value) {
            $ownerCount = User::query()
                ->where('clinic_id', $user->clinic_id)
                ->where('role', UserRole::OwnerClinic->value)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['You cannot remove the last clinic owner.'],
                ]);
            }
        }

        $user->update([
            'role' => UserRole::from($validated['role']),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::UserRoleChanged,
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
                'old_role' => $oldRole->value,
                'new_role' => $validated['role'],
            ],
            request: $request
        );

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }
}
