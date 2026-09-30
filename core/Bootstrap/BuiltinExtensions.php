<?php

declare(strict_types=1);

namespace Core\Bootstrap;

use Core\Extension\Core;

final readonly class BuiltinExtensions
{
    public function register(Core $core): void
    {
        $core->permissions()->define(
            'templates.code.edit',
            'Edit trusted PHP templates',
            ['superadmin', 'admin'],
        );
        $core->permissions()->define(
            'modules.manage',
            'Manage installed modules and package lifecycle',
            ['superadmin'],
        );

        $core->admin()->navigation('structure', 'Структура', '/admin/structure', 'structure.manage', 10, 'structure');
        $core->admin()->navigation('layouts', 'Макеты', '/admin/layouts', 'layouts.manage', 20, 'layout');
        $core->admin()->navigation('modules', 'Модули', '/admin/modules', 'modules.manage', 90, 'module');
    }
}
