<?php

declare(strict_types=1);

use Core\Database;
use UltimaVox\Modules\Infosystem\FieldSchema;
use UltimaVox\Modules\Infosystem\MoneyValue;
use UltimaVox\Modules\Infosystem\PropertyValidationException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;
use UltimaVox\Modules\Mechaniki\PriceLabel;
use UltimaVox\Modules\Sites\SitesManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
foreach (['infosystem', 'mechaniki', 'sites'] as $module) {
    require dirname(__DIR__) . '/modules/' . $module . '/autoload.php';
}
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$fields = new FieldSchema();
$schema = $fields->decode('[{"code":"amount_minor","name":"Стоимость, ₽","type":"money"}]');
foreach (['0' => 0, '0.01' => 1, '100.05' => 10005, '100,05' => 10005,
    '1 234.5' => 123450, "1\u{00a0}234,56" => 123456, "35\u{202f}000" => 3500000,
    '999999999999.99' => MoneyValue::MAX_MINOR] as $raw => $expected) {
    $actual = $fields->normalizeEditorProperties($schema, ['amount_minor' => (string) $raw])['amount_minor'];
    $assert($actual === $expected, 'Editor amount changed: ' . $raw);
    $assert(MoneyValue::parse(MoneyValue::format($actual)) === $actual, 'Format round trip changed cents');
}
foreach (['100.005', '1,2.3', '1e3', '-1', '+1', '1 23', '1.234,56', '1000000000000', 'NaN', [], true, 100.05] as $raw) {
    try {
        $fields->normalizeEditorProperties($schema, ['amount_minor' => $raw]);
    } catch (PropertyValidationException $e) {
        $assert($e->fieldCode === 'amount_minor', 'Money error lost field identity');
        continue;
    }
    throw new RuntimeException('Malformed or inexact amount accepted');
}
$assert($fields->normalizeEditorProperties($schema, ['amount_minor' => '']) === [], 'Optional blank became zero');
$assert(MoneyValue::format(3500000) === '35 000', 'Whole rubles must omit .00');
$assert(MoneyValue::format(10005) === '100.05', 'Fraction was lost');
$assert(PriceLabel::fromProperties(['amount_minor' => 10005, 'currency' => 'RUB', 'is_from' => true, 'price_label' => 'old']) === 'от 100.05 ₽', 'Public label ignored current numeric amount');
$assert(PriceLabel::fromProperties(['price_label' => 'Договорная']) === 'Договорная', 'Custom no-amount label lost');
$db = Database::connection();
$db->beginTransaction();
try {
    $site = (new SitesManagementRepository($db))->create('Money test', 'money-test');
    $otherSite = (new SitesManagementRepository($db))->create('Money other', 'money-other');
    $repo = new InfosystemManagementRepository($db, $site);
    $foreign = new InfosystemManagementRepository($db, $otherSite);
    $legacy = [['code' => 'category', 'name' => 'Категория', 'type' => 'text'],
        ['code' => 'amount_minor', 'name' => 'Стоимость в копейках', 'type' => 'number'],
        ['code' => 'custom', 'name' => 'Custom', 'type' => 'number']];
    $id = $repo->create('Prices', 'mechaniki_prices', null, $legacy, true);
    $foreignSchema = $legacy;
    $foreignSchema[1]['name'] = 'My custom numeric field';
    $foreignId = $foreign->create('Custom', 'mechaniki_prices', null, $foreignSchema, true);
    $data = ['name' => 'Money fixture', 'slug' => 'money', 'description' => null, 'content' => '',
        'meta_description' => null, 'status' => 'draft', 'is_active' => true, 'sorting' => 0, 'publish_at' => null];
    $item = $repo->saveItem(null, $id, null, $data + ['properties' => ['amount_minor' => 80000, 'custom' => 12.5]]);
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $migration = dirname(__DIR__) . '/modules/infosystem/migrations/'
        . ($driver === 'pgsql' ? '004_money_editor.sql' : 'mysql/002_money_editor.sql');
    $sql = file_get_contents($migration);
    $db->exec($sql);
    $migrated = $repo->find($id)['field_schema'];
    $assert($migrated[1]['type'] === 'money' && $migrated[1]['name'] === 'Стоимость, ₽', 'Existing price schema not upgraded');
    $assert($migrated[0] == $legacy[0] && $migrated[2] == $legacy[2], 'Migration touched unrelated fields/order');
    $assert($foreign->find($foreignId)['field_schema'] == $foreignSchema, 'Migration changed custom schema in another site');
    $assert($repo->findItem($id, $item)['properties']['amount_minor'] === 80000, 'Upgrade changed stored cents');
    $db->exec($sql);
    $assert($repo->find($id)['field_schema'] === $migrated, 'Schema migration is not idempotent');
    foreach (['0.01', '100.05', '35 000,50', '999999999999.99', '0', ''] as $input) {
        $properties = $fields->normalizeEditorProperties($schema, ['amount_minor' => $input]);
        $repo->saveItem($item, $id, null, $data + ['properties' => $properties]);
        $assert($repo->findItem($id, $item)['properties'] === $properties, 'DB changed exact minor-unit amount');
    }
    $assert($foreign->findItem($id, $item) === null, 'Money item leaked between sites');
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
echo "INFOSYSTEM MONEY OK: exact units, comma/grouped input, rejection, JSON round trips, schema upgrade/idempotence and site isolation\n";
