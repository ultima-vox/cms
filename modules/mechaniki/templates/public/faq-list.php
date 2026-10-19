<?php declare(strict_types=1); ?>
<section class="section section--soft" id="faq"><div class="wrap faq-layout"><div><p class="eyebrow">Перед визитом</p><h2>Частые вопросы</h2></div><div class="faq-list">
<?php foreach ($items as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<details class="faq-item"><summary><?= text((string) $item['name']) ?><svg class="ui-icon" aria-hidden="true"><use href="/assets/mechaniki/icons.svg#ChevronDown"/></svg></summary>
<p><?= text((string) ($p['answer'] ?? '')) ?></p></details>
<?php endforeach; ?>
</div></div></section>
