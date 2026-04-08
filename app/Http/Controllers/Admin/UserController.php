<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query();

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'ilike', "%{$request->search}%")
                  ->orWhere('last_name', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhere('phone', 'ilike', "%{$request->search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['category', 'role', 'search']),
            'roleCategories' => [
                'internal' => User::ROLES_INTERNAL,
                'participant' => User::ROLES_PARTICIPANT,
                'attendee' => User::ROLES_ATTENDEE,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roleCategories' => [
                'internal' => User::ROLES_INTERNAL,
                'participant' => User::ROLES_PARTICIPANT,
                'attendee' => User::ROLES_ATTENDEE,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $allRoles = implode(',', array_merge(
            User::ROLES_INTERNAL, User::ROLES_PARTICIPANT, User::ROLES_ATTENDEE
        ));

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => "required|in:{$allRoles}",
            'status' => 'required|in:active,inactive,pending,registered',
            'profile' => 'nullable|array',
        ]);

        $request->validate([
            'sales_type' => 'nullable|required_if:role,sales|in:lider,asesor',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'sales_type' => $request->role === 'sales' ? $request->sales_type : null,
                'status' => $request->status,
            ]);

            $this->syncProfile($user, $request->input('profile', []));
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    public function show(User $user): Response
    {
        $user->load([
            'modelProfile',
            'designerProfile',
            'pressProfile',
            'sponsorProfile',
            'shows.eventDay.event',
            'designedShows.eventDay.event',  // now via show_designer pivot
            'orders',
        ]);

        return Inertia::render('Admin/Users/Show', [
            'user' => $user->append('role_category'),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load(['modelProfile', 'designerProfile', 'pressProfile', 'sponsorProfile']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->append('role_category'),
            'roleCategories' => [
                'internal' => User::ROLES_INTERNAL,
                'participant' => User::ROLES_PARTICIPANT,
                'attendee' => User::ROLES_ATTENDEE,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $allRoles = implode(',', array_merge(
            User::ROLES_INTERNAL, User::ROLES_PARTICIPANT, User::ROLES_ATTENDEE
        ));

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => "nullable|string|unique:users,phone,{$user->id}",
            'role' => "required|in:{$allRoles}",
            'status' => 'required|in:active,inactive,pending,registered',
            'profile' => 'nullable|array',
        ]);

        $request->validate([
            'sales_type' => 'nullable|required_if:role,sales|in:lider,asesor',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8|confirmed']);
        }

        DB::transaction(function () use ($request, $user) {
            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'sales_type' => $request->role === 'sales' ? $request->sales_type : null,
                'status' => $request->status,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            $user->update($updateData);
            $this->syncProfile($user, $request->input('profile', []));
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario eliminado.');
    }

    private function syncProfile(User $user, array $profile): void
    {
        if (empty($profile)) return;

        $clean = array_filter($profile, fn($v) => $v !== null && $v !== '');

        match ($user->role) {
            'model' => $user->modelProfile()->updateOrCreate(['user_id' => $user->id], $clean),
            'designer' => $user->designerProfile()->updateOrCreate(['user_id' => $user->id], $clean),
            'press' => $user->pressProfile()->updateOrCreate(['user_id' => $user->id], $clean),
            'sponsor' => $user->sponsorProfile()->updateOrCreate(['user_id' => $user->id], $clean),
            default => null,
        };
    }
}
