<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id'           => $role->id,
                'name'         => $role->name,
                'label'        => $role->label,
                'users_count'  => $role->users_count,
                'permissions'  => $role->permissions->pluck('name'),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::orderBy('group')->orderBy('label')->get(['id', 'name', 'label', 'group']);

        return Inertia::render('admin/roles/create', [
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'label'       => ['required', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name'  => $validated['name'],
            'label' => $validated['label'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return to_route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        $permissions = Permission::orderBy('group')->orderBy('label')->get(['id', 'name', 'label', 'group']);

        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id'          => $role->id,
                'name'        => $role->name,
                'label'       => $role->label,
                'permissions' => $role->permissions()->pluck('permissions.id'),
            ],
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'label'         => ['required', 'string', 'max:255'],
            'permissions'   => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update(['label' => $validated['label']]);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return to_route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return to_route('admin.roles.index')->with('status', 'Role deleted.');
    }
}
