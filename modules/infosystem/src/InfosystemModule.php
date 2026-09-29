<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Repository\InfosystemRepository;
use Core\View\Render\RenderNodeInterface;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;

final class InfosystemModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $repository = new InfosystemRepository($core->runtime()->database());
        $content = $core->content();
        $events = $core->events();

        $core->permissions()->define(
            'infosystems.manage',
            'Manage infosystems',
            ['superadmin', 'admin', 'editor'],
        );
        $core->admin()->navigation(
            'infosystems',
            'Инфосистемы',
            '/admin/infosystems',
            'infosystems.manage',
            30,
        );

        $content->source(
            'infosystem.items',
            static function (TemplateFacadeContext $context, array $options) use ($repository, $events): RenderNodeInterface {
                $infosystem = $options['infosystem'] ?? null;
                if (!is_array($infosystem) || !isset($infosystem['id'], $infosystem['code'])) {
                    throw new RuntimeException('Infosystem content source requires a resolved infosystem record.');
                }

                return new InfosystemItemsSource(
                    $context,
                    $repository,
                    $events,
                    $infosystem,
                );
            },
        );

        $core->templates()->view(
            'infosystem.list',
            'infosystem.items',
            'modules/infosystem/templates/list.html.php',
        );

        $core->templates()->facade(
            'infosystems',
            static fn (TemplateFacadeContext $context): InfosystemsFacade => new InfosystemsFacade(
                $repository,
                $content,
                $context,
            ),
        );

        $core->templates()->facadeProvider(
            static function (TemplateFacadeContext $context, array $facades): array {
                $registry = $facades['infosystems'] ?? null;
                if (!$registry instanceof InfosystemsFacade) {
                    return [];
                }

                $linked = $registry->linked();
                if ($linked === null) {
                    return [];
                }

                $code = $linked->code();
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,79}$/', $code)) {
                    return [];
                }
                if (array_key_exists($code, $context->variables())) {
                    return [];
                }
                if (in_array($code, ['core', 'page', 'site', 'node', 'content', 'items', 'infosystems'], true)) {
                    return [];
                }

                return [$code => $linked];
            },
        );
    }
}
