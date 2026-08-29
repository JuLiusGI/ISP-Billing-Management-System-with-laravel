<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn (Builder $q) => $q->withRole($request->string('role')))
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('display_name')->get(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::orderBy('display_name')->get(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->except('roles'));
            $user->roles()->sync($request->input('roles'));

            return $user;
        });

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->full_name} has been added.");
    }

    public function show(User $user): View
    {
        return view('users.show', [
            'user' => $user->load('roles.permissions'),
        ]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('display_name')->get(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->guardAgainstSelfLockout($request, $user);

        DB::transaction(function () use ($request, $user): void {
            $attributes = $request->safe()->except(['roles', 'password']);

            if ($request->filled('password')) {
                $attributes['password'] = $request->input('password');
            }

            $user->update($attributes);
            $user->roles()->sync($request->input('roles'));
        });

        return redirect()
            ->route('users.show', $user)
            ->with('success', "{$user->full_name} has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->isLastSuperAdmin($user)) {
            return back()->with('error', 'The last super admin cannot be deleted.');
        }

        // Soft delete: the user owns audit and payment history that must stay
        // attributable after the account is retired.
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "{$user->full_name} has been deactivated.");
    }

    /**
     * Stops an administrator from removing their own last way back in, by
     * suspending themselves or dropping their own super admin role.
     */
    private function guardAgainstSelfLockout(Request $request, User $user): void
    {
        if (! $request->user()->is($user)) {
            return;
        }

        $stillSuperAdmin = in_array(
            (string) Role::where('name', Role::SUPER_ADMIN)->value('id'),
            array_map('strval', $request->input('roles', [])),
            true
        );

        abort_if(
            $request->input('status') !== UserStatus::Active->value,
            403,
            'You cannot change your own account away from active.'
        );

        abort_if(
            $user->isSuperAdmin() && ! $stillSuperAdmin && $this->isLastSuperAdmin($user),
            403,
            'You are the last super admin and cannot remove your own role.'
        );
    }

    private function isLastSuperAdmin(User $user): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        return User::withRole(Role::SUPER_ADMIN)->count() <= 1;
    }
}
