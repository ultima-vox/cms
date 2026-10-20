<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Page\PageEditorLinkProviderInterface;
use Core\Repository\NodeModuleBindingRepository;
use Core\Security\AuthService;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final readonly class InfosystemPageEditorLinkProvider implements PageEditorLinkProviderInterface
{
    public function __construct(
        private InfosystemRepository $systems,
        private InfosystemManagementRepository $management,
        private NodeModuleBindingRepository $bindings,
        private int $siteId,
    ) {
    }

    public function editorUrl(array $node, AuthService $auth): ?string
    {
        if (!$auth->can('infosystems.manage') || (int) ($node['site_id'] ?? 0) !== $this->siteId) {
            return null;
        }
        $code = $this->bindings->findTargetKey((int) $node['id'], 'infosystem', 'primary');
        $system = $code !== null ? $this->systems->findActiveByCode($this->siteId, $code) : null;
        if ($system === null) {
            return null;
        }
        $config = $node['page_config'] ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true, flags: JSON_THROW_ON_ERROR);
        }
        if (!is_array($config)) {
            return null;
        }
        if ($node['page_type'] === 'infosystem.list') {
            (new InfosystemPageConfigurationValidator())->validate($config);
            return '/admin/infosystems/' . (int) $system['id'];
        }
        $config = (new InfosystemItemPageConfigurationValidator())->validate($config);
        $id = $this->management->findItemIdByPath((int) $system['id'], $config['item_path']);
        return $id !== null ? '/admin/infosystems/' . (int) $system['id'] . '/items/' . $id . '/edit' : null;
    }
}
