<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Extension\Core;

final class BuiltinExtensions
{
    public function register(Core $core): void
    {
        $permissions = $core->permissions();
        $admin = $core->admin();

        $permissions->define(
            'admin.access',
            'Access administration panel',
            ['superadmin', 'admin', 'editor'],
        );
        $permissions->define(
            'structure.manage',
            'Manage site structure',
            ['superadmin', 'admin', 'editor'],
        );
        $permissions->define(
            'layouts.manage',
            'Manage layouts',
            ['superadmin', 'admin'],
        );
        $permissions->define(
            'templates.code.edit',
            'Edit executable PHP templates',
            ['superadmin'],
        );
        $permissions->define(
            'users.manage',
            'Manage users',
            ['superadmin'],
        );

        $admin->navigation(
            'dashboard',
            'Обзор',
            '/admin',
            'admin.access',
            10,
        );
        $admin->navigation(
            'structure',
            'Структура',
            '/admin/structure',
            'structure.manage',
            20,
        );
        $admin->navigation(
            'layouts',
            'Макеты',
            '/admin/layouts',
            'layouts.manage',
            40,
        );
    }
}
