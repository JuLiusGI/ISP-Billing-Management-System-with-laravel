<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('display_name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('roles.create', [
            'permissionsByModule' => $this->permissionsByModule(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::create($request->safe()->except('permissions') + ['is_system' => false]);
            $role->permissions()->sync($request->input('permissions'));

            return $role;
        });

        return redirect()
            ->route('roles.index')
            ->with('success', "The {$role->display_name} role has been created.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissionsByModule' => $this->permissionsByModule(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role): void {
            // The machine name is left alone on update. Code and seeders refer
            // to roles by it, so renaming would break those references.
            $role->update($request->safe()->only(['display_name', 'description']));
            $role->permissions()->sync($request->input('permissions'));
        });

        return redirect()
            ->route('roles.index')
            ->with('success', "The {$role->display_name} role has been updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', "The {$role->display_name} role has been deleted.");
    }

    /**
     * Abilities grouped for the permission matrix.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    private function permissionsByModule()
    {
        return Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
    }
}
