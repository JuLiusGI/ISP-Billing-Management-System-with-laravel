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
        $this->authorize('viewAny', User::class);

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
        $this->authorize('create', User::class);

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
        $this->authorize('view', $user);

        return view('users.show', [
            'user' => $user->load('roles.permissions'),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('display_name')->get(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // Depends on the submitted status and roles, so it cannot be settled
        // by update() alone.
        $this->authorize('saveWith', [
            $user,
            (string) $request->input('status'),
            (array) $request->input('roles', []),
        ]);

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

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // Soft delete: the user owns audit and payment history that must stay
        // attributable after the account is retired.
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "{$user->full_name} has been deactivated.");
    }
}
