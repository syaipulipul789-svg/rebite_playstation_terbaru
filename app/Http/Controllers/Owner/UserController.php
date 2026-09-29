<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount('shifts')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')->toString()))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('owner.users.index', [
            'users' => $users,
            'roleOptions' => UserRole::options(),
            'filters' => $request->only(['role']),
        ]);
    }

    public function create(): View
    {
        return view('owner.users.form', [
            'managedUser' => new User(['role' => UserRole::KASIR, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Checkbox tidak terkirim saat tidak dicentang, jadi normalkan dulu
        // agar field yang hilang tetap berarti "tidak aktif".
        $request->merge(['is_active' => $request->boolean('is_active')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['required', 'boolean'],
        ], [
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, garis, dan underscore.',
        ]);

        User::create($validated);

        return redirect()->route('owner.users.index')
            ->with('success', "Akun {$validated['username']} berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('owner.users.form', ['managedUser' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isSelf = $request->user()->is($user);

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($isSelf && $validated['role'] !== UserRole::OWNER->value) {
            return back()->with('error', 'Anda tidak bisa menurunkan role akun sendiri.')->withInput();
        }

        if ($isSelf && ! $validated['is_active']) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun sendiri.')->withInput();
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('owner.users.index')
            ->with('success', "Akun {$user->username} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }

        if ($user->shifts()->where('status', 'OPEN')->exists()) {
            return back()->with('error', "{$user->name} masih memiliki shift aktif. Tutup shift dulu.");
        }

        $username = $user->username;
        $user->delete();

        return back()->with('success', "Akun {$username} berhasil dihapus.");
    }
}
