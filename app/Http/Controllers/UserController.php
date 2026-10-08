<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Depot;
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
            ->with('depot')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => UserRole::options()]);
    }

    public function create(): View
    {
        return $this->form(new User(['role' => UserRole::Staff, 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->is($request->user())) {
            // Prevent administrators from locking themselves out.
            $data['is_active'] = true;
            $data['role'] = $user->role->value;
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User account saved.');
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'depots' => Depot::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'depot_id' => ['nullable', 'required_unless:role,admin', 'exists:depots,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
