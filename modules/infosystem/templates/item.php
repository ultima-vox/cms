<?php

declare(strict_types=1);

use Core\View\SafeHtml;

?>
<article class="uv-infosystem-item" data-infosystem="<?= text((string) $infosystem['code']) ?>">
    <h1><?= text((string) $item['name']) ?></h1>
    <?php if ((string) ($item['description'] ?? '') !== ''): ?>
        <div class="uv-infosystem-item__lead"><?= html(SafeHtml::fromTrustedStorage((string) $item['description'])) ?></div>
    <?php endif; ?>
    <?php if ((string) ($item['content'] ?? '') !== ''): ?>
        <div class="uv-infosystem-item__body"><?= html(SafeHtml::fromTrustedStorage((string) $item['content'])) ?></div>
    <?php endif; ?>
</article>
