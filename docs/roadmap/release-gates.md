# G0 и production 1.0 — release gates

Main review: `68cd931945ecb1afad608f22398cce84fa994063`; runtime CI baseline: `f5eecbbc8e6d7257687fb828c57fb268b73427bb`, 2026-10-19. После исходного baseline вошли plan PR101, a11y PR132, TTL-test PR135 и infosystem.item PR136; схема не менялась. Исторические CI ниже остаются привязаны к своим SHA. [Карта функций и доказательства](feature-matrix.md), [tracking102](https://github.com/ultima-vox/cms/issues/102), [план](https://github.com/ultima-vox/cms/blob/d0c4f16550a8dee6eb764cef9e22729c9a037171/docs/roadmap/production-1.0-agent-plan.md).

**Решение сейчас: NO-GO для v1.0.0. G0 inventory candidate — на review, G1 не начат.** Зелёный baseline CI доказывает перечисленные MVP-сценарии, не закрывает отсутствующие функции и неизвестные измерения. GitHub merge/tag не доказывает deployment на ru.rewired.ru или другом production URL.

## G0: что требуется до перехода

| Gate | Состояние baseline / candidate | Критерий закрытия / владелец задачи |
| --- | --- | --- |
| G0-A Source/dependencies/API/PR inventory | Подготовлено в candidate | Review feature-matrix и pinned plan; source/test links, no mixed unfinished branches; #104 |
| G0-B Reproducible install/upgrade baseline | Есть bounded MVP evidence | CI584 + MVP65 на точном SHA, PG both layouts/real v0.1.20 upgrade, four legacy contracts/webroot. Расширение матрицы #106 не выдавать за уже покрытое |
| G0-C Module removal / architecture executable checks | Добавлено в candidate, требуется green CI | Physical package removal + fresh application dispatch и two-site isolation на пяти DB jobs; targeted SQL/import guard и failing fixtures. Не считать это удалением domain tables или формальным анализом всех dynamic SQL |
| G0-D UX и performance baseline | Данные готовы в PR130/133, требуется интеграция/review | #105: фиксированные fixtures, три editor flows на 390/1024/1440, clicks/errors, cold/warm p50/p95, SQL count и peak memory, описанное окружение/нагрузка. Не выдумывать measurements |
| G0-E Bug/compatibility registry | Подготовлен, задачи открыты | #108 P1 data validation; #107 исправлен PR135 (main CI/MVP green); #106 hosting/legacy limitations. Подтверждённый критический security/data/installation bug запрещает переход; не объявлять неизвестный риск исправленным |
| G0-F Compatibility/version policy | Кандидат правил ниже, на review | Сохранить Extension API1 current behavior, additive migrations; versioned REST ещё missing; owner review перед breaking changes |

G0 не объявляется целиком принятым этим документом. Измеримые доказательства #105 готовы в отдельных PR130 (browser UX) и PR133 (Content 1k/10k CLI profiling); #107 уже закрыт. Следующий шаг — интеграция и явное решение по G0 gates. Пользовательские walkthrough G10/G-REL-07 и утверждение load budgets после G0 не вводятся как новые неоговорённые блокеры G0. G1 #109/#123–#128 остаётся blocked до приёмки G0 и отсутствия его критических проблем. #108 должен быть исправлен до production; отсутствие запланированного Commerce не является регрессией существующего MVP, но запрещает релиз 1.0.

## Полная матрица совместимости

| Поставка / механизм | PostgreSQL16 | MySQL8.0/8.4 | MariaDB10.11/11.4 |
| --- | --- | --- | --- |
| Syntax/metadata/repository subset | pass, CI584 | pass, CI584 | pass, CI584 |
| Fresh HTTP public | pass, MVP65 + Apache CI584 | не принято | не принято |
| Fresh HTTP webroot | pass, MVP65 + Apache CI584 | pass, legacy CI584 built-in server | pass, legacy CI584 built-in server |
| Update rollback/crash contracts | pass, updater suite | pass, mysql_update/failure | pass, mysql_update/failure |
| Real populated old-release HTTP upgrade | pass, PG webroot v0.1.20 | не принято | не принято |
| Public/webroot × DB × crash/restore full matrix | partial | partial | partial |
| Module package purge | PG suite pass | intentionally unsupported | intentionally unsupported |
| G0 candidate physical package removal | candidate CI required | candidate CI required | candidate CI required |

Private Apache denial accepts 403/404. Nginx/FPM/proxy and TLS wrong-name/untrusted-chain acceptance остаются #106. Installer фиксирует DNS-IP, не следует redirects, по умолчанию не обращается к private IP; environment allowlist для LAN не является обходом proof. Не ослаблять эти ограничения ради green tests.

## G-REL-01…10: критерии полного 1.0

| Gate | Статус сейчас | Требуемое доказательство | Зависимости |
| --- | --- | --- | --- |
| G-REL-01 Install/upgrade compatibility | partial | Fresh+populated upgrade+failure/recovery по обеим поставкам и всем заявленным СУБД; права минимальны, no mandatory Composer/SSH/Node | #106, #121 |
| G-REL-02 Compatible signed distribution/modules | partial | Manifest/Core/API/driver compatibility; disabled/removed modules не регистрируют callbacks/routes; artifact/package signatures и tamper tests | #104, #120, #122 |
| G-REL-03 Backup/restore drill | missing | Полное приватное резервирование БД/media/templates/config metadata, manifest/checksum, restore на чистой машине с публичным результатом | #121 |
| G-REL-04 Security/correctness | blocked | P0/P1=0; #108 закрыт regression тестом; threat model + ASVS-L2-oriented review, dependency audit/SAST/secret scan, upload/SSRF/XSS/SQLi/authz/zip tests; MFA или явно принятое исключение | #108, владельцы доменов, #122 |
| G-REL-05 Site/locale/region isolation | partial | Нынешние site tests + negative 2 sites × 2 locales × 2 regions, права, публикация, URLs/fallback/cache без чужих данных | #116 |
| G-REL-06 Safe commerce end-to-end | missing | Exact server totals, concurrent checkout/stock, idempotency, webhook signatures/replay/out-of-order, sandbox provider, retries без duplicate/lost orders | #117–#119 |
| G-REL-07 Editor/visual/a11y workflows | partial | Content/Commerce/Platform walkthroughs, keyboard/focus/errors, three widths, stable screenshots/axe; отсутствие raw JSON в обычных карточках | #105, #109–#112, #122 |
| G-REL-08 Performance | missing | Published environment/fixtures, cold/warm p50/p95, SQL count, peak memory; delta against approved baseline; shared-host low-memory and VPS concurrent load | #105, #117, #122 |
| G-REL-09 Artifact/runbooks/policy | partial | Reproducible artifact evidence, SBOM/manifest/checksum/signature, licenses, release notes, API/deprecation/DB policy, installation/update/recovery/restore runbooks | #120–#122 |
| G-REL-10 RC/go-no-go/deployment | missing | Three reference projects, минимум 7 календарных дней staging soak; reset после P0/schema change, restore drill, явное owner go/no-go. Production URL, artifact SHA, время и smoke — отдельно | Все gates, #122 |

Ориентиры p95≤400ms warm VPS / Lighthouse≥90 из ТЗ пока **provisional**, не измерены и не являются пройденными. Commerce fixture 10k products/30k SKU невозможно признать рабочим до владельцев каталога/заказов. Для multilingual и commerce сейчас фиксируются критерии fixtures, не пустые production-модули или фальшивые benchmarks.

## Versioning и безопасное обновление

- Текущее: [Core0.4.0](../../core/Version.php), [Extension API1.0.0](../../core/Extension/ExtensionApiVersion.php), manifest constraints и `VersionConstraint` проверяются loader. Dev CMS tags вида 0.1.N происходят из workflow run number; это не утверждение о стабильности production API.
- На review G0: SemVer для Core/module/API; добавление совместимых возможностей — minor, bugfix — patch; breaking contract требует отдельного ADR, migration/deprecation notes, dependency matrix и явного approval. Не обещать существующим third-party packages несуществующий REST API.
- `extensions()` — экспериментальный registry; typed API вводить для реального сценария, не каталог пустых интерфейсов. Существующий working API не переписывать ради единообразия.
- Применённые миграции неизменяемы. Новая схема — новый файл, backfill/cutover/cleanup только по потребности, fresh+upgrade tests, crash/recovery procedure. Автоматического PG↔MySQL conversion и общего SQL downgrade нет.
- PostgreSQL transactional DDL и legacy dirty marker/snapshot — разные механики. Unknown COMMIT сохраняет maintenance. Legacy snapshot restrictions/purge exception должны быть видны до операции, а не скрыты адаптером.
- Full backup и updater rollback — разные гарантии. Подписанный module ZIP не заменяет CMS release signature, HTTPS/SHA не заменяют independent publisher proof.
- Смена версии, HTTP contract, signing/licensing/auth/payment или destructive schema требует соответствующего review/approval из ТЗ; не bypass branch protection. Производственный deployment — отдельное явно разрешённое действие.

## Команды проверки G0 candidate

В изолированном CI после Composer и миграций, с `APP_ENV=testing` и одноразовой БД:

```sh
composer validate --strict
php tests/runtime_smoke.php
python3 tools/check_core_boundaries.py
python3 tests/core_boundaries_check_test.py
php tests/database_driver_smoke.php
php tests/content_folders_smoke.php
php tests/module_removal_smoke.php
```

Полный CI включает остальные migration/module/package/render/security tests и пять СУБД jobs; MVP acceptance — browser install/editor, existing upgrade и three-width browser suite. Эти команды не выполнять на production DB. `module_removal_smoke` работает с изолированными файловыми копиями и временными DB-фикстурами; реальные пакеты репозитория не удаляет, доменные таблицы не переименовывает/не удаляет.

Targeted architecture guard проверяет concrete module namespaces и literal SQL module-owned tables, найденные в module migrations. Его negative fixtures доказывают обнаружение quoted/qualified SQL и imports; dynamic SQL и общий PHP type analysis остаются review/static-analysis задачами.

## Шаблон закрытия gate

Записать: baseline/candidate SHA, точные CI run/job links, DB/layout/fixture, команды, результат, оставшиеся blockers с severity и issue, API/DB diff, cold/warm performance delta, evidence reviewer. Затем поставить gate passed. Production отдельно: URL + deployed artifact SHA + время + smoke + approval; если данных нет, писать «не проверено». Не менять checklist только из-за merge.
