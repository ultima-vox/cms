<?php

declare(strict_types=1);

namespace Core\Page;

use Core\Security\AuthService;

interface PageEditorLinkProviderInterface
{
    /** Return a fixed internal admin path, or null when this user cannot edit this node's content.
     * @param array<string, mixed> $node Persisted node in the current admin site.
     */
    public function editorUrl(array $node, AuthService $auth): ?string;
}
