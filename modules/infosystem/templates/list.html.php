<?php if ($items !== []): ?>
<section class="uv-infosystem-list" data-infosystem="<?= text((string) ($infosystem['code'] ?? '')) ?>">
    <?php foreach ($items as $item): ?>
        <article class="uv-infosystem-list__item">
            <h2><?= text((string) ($item['name'] ?? '')) ?></h2>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
