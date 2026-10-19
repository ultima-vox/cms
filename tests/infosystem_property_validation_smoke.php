<?php

declare(strict_types=1);

use Core\Database;
use UltimaVox\Modules\Infosystem\FieldSchema;
use UltimaVox\Modules\Infosystem\PropertyValidationException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;
use UltimaVox\Modules\Sites\SitesManagementRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
foreach (['infosystem', 'sites'] as $module) {
    require dirname(__DIR__) . '/modules/' . $module . '/autoload.php';
}

$fields = new FieldSchema();
$schema = $fields->decode('[{"code":"event_date","name":"Дата","type":"date","required":true},{"code":"amount","name":"Число","type":"number"}]');
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$reject = static function (array $input, string $code) use ($fields, $schema): void {
    try {
        $fields->normalizeProperties($schema, $input);
    } catch (PropertyValidationException $exception) {
        if ($exception->fieldCode === $code && $exception->getMessage() !== '') {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Invalid property accepted: ' . $code);
};
foreach (['0001-01-01', '9999-12-31', '2000-02-29', '2024-02-29', '2026-02-28'] as $date) {
    $assert($fields->normalizeProperties($schema, ['event_date' => $date])['event_date'] === $date, 'Valid calendar date changed');
}
foreach (['2026-02-31', '2026-02-29', '1900-02-29', '2100-02-29', '0000-01-01', '2026-00-10', '2026-13-01', '2026-04-31', '2026-01-00', '2026-1-01', '10000-01-01', '2026-01-01x', [], new stdClass(), null, ''] as $date) {
    $reject(['event_date' => $date], 'event_date');
}
foreach ([0, '-0.0', '0e9999', '-0e-9999', '-12.5', '1.25e3', ' 12.5 ', PHP_FLOAT_MAX, -PHP_FLOAT_MAX, '1.7976931348623157e308', '5e-324'] as $number) {
    $actual = $fields->normalizeProperties($schema, ['event_date' => '2000-02-29', 'amount' => $number])['amount'];
    $assert(is_float($actual) && is_finite($actual) && $actual === (float) $number, 'Valid approximate number changed');
}
foreach (['1e309', '-1e309', '1.7976931348623159e308', '1e-9999', '-2e-324', INF, -INF, NAN, 'NaN', 'Infinity', '12no', [], true] as $number) {
    $reject(['event_date' => '2000-02-29', 'amount' => $number], 'amount');
}
$optional = $fields->decode('[{"code":"optional_date","name":"Дата","type":"date"},{"code":"amount","name":"Число","type":"number"}]');
$assert($fields->normalizeProperties($optional, ['optional_date' => '', 'amount' => '']) === [], 'Optional blanks changed');

// Exercise the actual owner JSON persistence on every supported DB, inside rollback.
$db = Database::connection();
$db->beginTransaction();
try {
    $site = (new SitesManagementRepository($db))->create('Property validation', 'property-validation');
    $repository = new InfosystemManagementRepository($db, $site);
    $system = $repository->create('Properties', 'property-validation', null, $schema, true);
    $data = ['name' => 'Property fixture', 'slug' => 'property-fixture', 'description' => null, 'content' => '',
        'meta_description' => null, 'status' => 'draft', 'is_active' => true, 'sorting' => 0, 'publish_at' => null];
    $id = null;
    foreach (['12.5', '-1e3', PHP_FLOAT_MAX, -PHP_FLOAT_MAX, '5e-324', '0'] as $number) {
        $properties = $fields->normalizeProperties($schema, ['event_date' => '2000-02-29', 'amount' => $number]);
        $id = $repository->saveItem($id, $system, null, $data + ['properties' => $properties]);
        $saved = $repository->findItem($system, $id)['properties'];
        $assert($saved['event_date'] === '2000-02-29' && (float) $saved['amount'] === (float) $number, 'DB JSON round trip changed a valid boundary');
    }
    echo "INFOSYSTEM PROPERTY VALIDATION OK: real calendar, leap/year boundaries, finite float range/underflow, field identity, optional blanks and DB JSON round trips\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
