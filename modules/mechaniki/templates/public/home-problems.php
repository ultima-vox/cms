<?php declare(strict_types=1); ?>
<section class="section section--soft" id="symptoms"><div class="wrap"><div class="section-heading"><div><h2>С чем приезжать?</h2><p>Выберите знакомый симптом. Название детали знать необязательно.</p></div><a class="quiet-link" href="/problems">Все симптомы →</a></div><div class="symptoms">
<?php foreach (array_slice($items, 0, 4) as $item): ?>
<?php $p = is_array($item['properties'] ?? null) ? $item['properties'] : []; ?>
<a class="symptom" href="<?= url('/problems' . (string) $item['path']) ?>"><svg class="ui-icon" aria-hidden="true"><use href="/assets/mechaniki/icons.svg#Wrench"/></svg><div><h3><?= text((string) $item['name']) ?></h3><p><?= text((string) ($p['lead'] ?? '')) ?></p></div><span aria-hidden="true">→</span></a>
<?php endforeach; ?>
</div></div></section>
