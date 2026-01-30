<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissionsList = [
            ['identifier' => 'manager.video', 'description' => 'Accès à la gestion des vidéos'],
            ['identifier' => 'manager.video.upload', 'description' => 'Téléversement de vidéos'],
            ['identifier' => 'manager.playlist', 'description' => 'Gestion des playlists'],
            ['identifier' => 'manager.category', 'description' => 'Gestion des catégories'],
            ['identifier' => 'manager.stat', 'description' => 'Accès aux statistiques'],
            ['identifier' => 'manager.design', 'description' => 'Gestion de la présentation'],
        ];

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $permissionsList,
        ]);
    }

    public function byIdentifier(Request $request, string $identifier)
    {
        $permissions = UserPermission::where('identifier', $identifier)->get();

        return Inertia::render('Admin/Permissions/Identifier', [
            'identifier' => $identifier,
            'permissions' => $permissions,
        ]);
    }

    public function byUser(Request $request, string $username)
    {
        $targetUser = User::where('username', $username)->firstOrFail();
        $permissions = UserPermission::where('username', $username)->get();

        return Inertia::render('Admin/Permissions/User', [
            'targetUser' => $targetUser,
            'permissions' => $permissions,
        ]);
    }

    public function grant(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => 'required|string|exists:clap_user,username',
            'identifier' => 'required|string',
        ]);

        UserPermission::firstOrCreate([
            'username' => $validated['username'],
            'identifier' => $validated['identifier'],
        ], ['created_by' => $user->username]);

        return back()->with('success', 'Permission accordée');
    }

    public function revoke(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => 'required|string',
            'identifier' => 'required|string',
        ]);

        if ($user->username == $validated['username'] && $validated['identifier'] == 'admin') {
            return back()->with('error', 'Impossible de s\'enlever la permission admin');
        }

        UserPermission::where('username', $validated['username'])
            ->where('identifier', $validated['identifier'])
            ->delete();

        return back()->with('success', 'Permission révoquée');
    }

    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');
        $limit = min($request->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::where(function ($q) use ($query) {
            $q->where('username', 'LIKE', "%{$query}%")
                ->orWhere('first_name', 'LIKE', "%{$query}%")
                ->orWhere('last_name', 'LIKE', "%{$query}%");
        })
            ->orderBy('username')
            ->limit($limit)
            ->get(['id', 'username', 'first_name', 'last_name', 'promo']);

        return response()->json(['users' => $users]);
    }
}
