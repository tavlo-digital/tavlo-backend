<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with('role')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name, 'label' => $user->role->label] : null,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]);

        $roles = Role::orderBy('label')->get(['id', 'name', 'label']);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load('role');
        $roles = Role::orderBy('label')->get(['id', 'name', 'label']);

        return Inertia::render('admin/users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name, 'label' => $user->role->label] : null,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user->update($validated);

        return to_route('admin.users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(Auth::user())) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return to_route('admin.users.index')->with('status', 'User deleted.');
    }
}
