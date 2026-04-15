<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    private const BCRYPT_COST = 12;

    private array $permissions = [
        'users.list'               => 'List and view users',
        'users.create'             => 'Create new users',
        'users.update'             => 'Update existing users and manage their roles',
        'roles.list'               => 'List and view roles and permissions',
        'roles.create'             => 'Create new roles',
        'roles.update'             => 'Update existing roles and their permissions',
        'service_accounts.create'  => 'Create and manage service accounts',
        'disciplinary.appeal'      => 'File or manage disciplinary appeals',
        'disciplinary.clear'       => 'Clear disciplinary records',
        'results.review'           => 'Review academic results',
        'subjects.view_pii'        => 'View personally identifiable information on subjects',
        'music.read'               => 'View songs, albums, and playlists',
        'music.create'             => 'Create songs, albums, and playlists',
        'music.update'             => 'Update songs, albums, and playlists',
        'music.delete'             => 'Delete draft songs, albums, and playlists',
        'music.publish'            => 'Publish and unpublish songs, albums, and playlists',
        'music.manage_all'         => 'Manage all music items regardless of ownership',
    ];

    private array $roles = [
        'admin'     => 'System administrator with full access',
        'analyst'   => 'Data analyst with read access',
        'librarian' => 'Librarian with record management access',
        'reviewer'  => 'Reviewer with results review access',
    ];

    private array $rolePermissions = [
        'admin'     => [
            'users.list', 'users.create', 'users.update',
            'roles.list', 'roles.create', 'roles.update',
            'service_accounts.create',
            'disciplinary.appeal', 'disciplinary.clear',
            'results.review',
            'subjects.view_pii',
            'music.read', 'music.create', 'music.update', 'music.delete', 'music.publish', 'music.manage_all',
        ],
        'analyst'   => ['users.list', 'roles.list', 'results.review', 'music.read'],
        'librarian' => ['users.list', 'roles.list', 'subjects.view_pii', 'music.read', 'music.create', 'music.update', 'music.delete', 'music.publish'],
        'reviewer'  => ['results.review', 'roles.list', 'music.read'],
    ];

    public function run(): void
    {
        // Create permissions
        $permissionModels = [];
        foreach ($this->permissions as $name => $description) {
            $permissionModels[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }

        // Create roles and assign permissions
        foreach ($this->roles as $roleName => $description) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => $description]
            );

            $permIds = collect($this->rolePermissions[$roleName] ?? [])
                ->map(fn ($p) => $permissionModels[$p]->id)
                ->all();

            $role->permissions()->syncWithoutDetaching($permIds);
        }

        // Create admin user
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'display_name' => 'System Administrator',
                'password_hash' => Hash::make('Admin@Password1', ['rounds' => self::BCRYPT_COST]),
                'is_active'    => true,
            ]
        );

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
