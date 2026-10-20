# G0 — карта функций Ultima Vox CMS

Дата: 2026-10-19. **Проверенный main: `c56e50b1fbd4416179c8a9207a3a08e79008cb73`**. Исходный G0 runtime baseline: `f5eecbbc8e6d7257687fb828c57fb268b73427bb`; план вошёл через PR101. После baseline объединены исправление доступности PR132, детерминированный TTL test PR135 и нативная страница элемента Infosystem PR136, перенос Mechaniki PR96 и поставка ресурсов PR138. Core `0.4.0`, Extension API `1.0.0`; применённые миграции не менялись.

Основание: [утверждённый пользователем источник ТЗ](https://github.com/ultima-vox/cms/blob/d0c4f16550a8dee6eb764cef9e22729c9a037171/docs/roadmap/production-1.0-agent-plan.md), [epic102](https://github.com/ultima-vox/cms/issues/102), [G0-01](https://github.com/ultima-vox/cms/issues/104). Ветка G0 сверена с runtime mainc56e50b; отдельная работа PR136, перенос Mechaniki PR96 и поставочные ресурсы PR138 сохранены, включая новые CI checks. [Main CI39255125805](https://github.com/ultima-vox/cms/actions/runs/39255125805) и [MVP39255125931](https://github.com/ultima-vox/cms/actions/runs/39255125931) — success. Эта карта заменяет предварительный материал PR99 для формальной приёмки G0; PR129/130/133 интегрированы в mainf3185b6; приёмка и её границы записаны в release-gates.md.

## Как читать статусы и доказательства

- `implemented` — указан конкретный существующий сценарий, код и проверка. Это не сертификат production-готовности всего модуля.
- `partial` — часть сценария работает, перечисленные пробелы остаются.
- `missing` — владельца/реализации/приёмочного сценария в просмотренном git tree нет; это не равнозначно дефекту работающего механизма.
- Для отсутствующей функции не придумывается тест: подтверждение — инвентарь пакетов и существующей suite; её приёмочный тест пока отсутствует.
- Блокировки, неизвестные измерения и функции вне 1.0 указаны отдельно. Проценты code coverage не измерялись.
- Относительные ссылки ниже относятся к source tree этой ревизии. Исторические утверждения привязаны к baseline SHA, будущие изменения требуют новой ревизии карты.

## Baseline CI и границы проверки

[CI584 / 39243238740](https://github.com/ultima-vox/cms/actions/runs/39243238740) и [MVP65 / 39243238633](https://github.com/ultima-vox/cms/actions/runs/39243238633) на runtime baseline f5eecbb завершены `success`. Новый candidate G0 проверяется отдельно на дереве с планом из актуального main.

| Сценарий | Подтверждение | Ограничение |
| --- | --- | --- |
| PHP 8.4 syntax, Composer, hygiene, архитектурные smoke | [ci.yml](../../.github/workflows/ci.yml), [runtime_smoke.php](../../tests/runtime_smoke.php), [core_module_boundary_smoke.php](../../tests/core_module_boundary_smoke.php) | `php -l` не является type/static analysis; PHPStan/Psalm отсутствуют |
| PostgreSQL 16 | Полная test job CI584; [database_driver_smoke.php](../../tests/database_driver_smoke.php), [content_folders_smoke.php](../../tests/content_folders_smoke.php) | Legacy не запускает всю PostgreSQL-specific suite |
| MySQL 8.0/8.4, MariaDB 10.11/11.4 | Четыре legacy jobs CI584; [mysql_update_smoke.php](../../tests/mysql_update_smoke.php), [mysql_migration_failure_smoke.php](../../tests/mysql_migration_failure_smoke.php), [legacy_http_smoke.py](../../tests/legacy_http_smoke.py) | HTTP legacy — webroot; симметричный public install/upgrade/recovery ещё не принят (#106) |
| Apache public/webroot, 128M PHP | Две apache-hosting jobs CI584; [fixture](../../tests/fixtures/apache/Dockerfile), [private probes](../../tests/apache_private_files_smoke.py), [installer refusal](../../tests/installer_hosting_http_smoke.py) | Не подтверждает nginx/FPM/reverse proxy/FASTPANEL и всю TLS-матрицу (#106) |
| Browser-install, editor, site isolation, browser widths | [mvp_http_smoke.py](../../tests/mvp_http_smoke.py), [browser_mvp.cjs](../../tests/browser_mvp.cjs); MVP65, public/webroot | 390/1024/1440 px — проверенные размеры, не полная WCAG AA/визуальная приёмка |
| Реальный upgrade со старой сборки | [prepare_upgrade_runtime.php](../../tests/prepare_upgrade_runtime.php), [update_existing_runtime_smoke.php](../../tests/update_existing_runtime_smoke.php), [HTTP](../../tests/update_existing_runtime_http.py); MVP65 | PG webroot baseline v0.1.20; не все прошлые версии/legacy layouts |

Полный tracked tree получен через git; просмотрены manifests, API/boot/routing, владельцы контента, установка/обновление/миграции, безопасность и связанные тесты. Это направленный аудит возможностей и границ, не независимый pentest и не доказательство всех возможных путей исполнения. В исходном baseline 138 PHP-файлов Core; main68cd931 содержит 139 после generic PageNotFoundException. SQL-файлов по-прежнему 43 в Core/модулях с учётом отдельных legacy-схем; эти числа — инвентарь, не метрика сложности или покрытия.

## Platform

| ID / возможность | Статус | Код | Проверки и незакрытая часть |
| --- | --- | --- | --- |
| P01 HTTP router, typed registries, заморозка boot | implemented | [Router](../../core/Router.php), [Core](../../core/Extension/Core.php), [RoutesApi](../../core/Extension/Api/RoutesApi.php) | [runtime](../../tests/runtime_smoke.php), [page registry](../../tests/page_execution_registry_smoke.php) |
| P02 Публичный versioned REST/JSON | missing | В [BuiltinRoutes](../../core/Bootstrap/BuiltinRoutes.php) есть health/admin/node, но нет ресурcного REST v1; Extension API — внутренний API | Приёмочного REST/auth-scopes теста нет; не путать JSON health с headless API |
| P03 Sites/domains и граница доступа | implemented | [SiteResolver](../../core/Site/SiteResolver.php), [SiteAccessRepository](../../core/Site/SiteAccessRepository.php), [SitesModule](../../modules/sites/src/SitesModule.php) | [site_resolution](../../tests/site_resolution_smoke.php), HTTP direct-ID/stale-site/grants в [MVP](../../tests/mvp_http_smoke.py) |
| P04 RBAC, CSRF, пароль, login throttling | implemented | [AuthService](../../core/Security/AuthService.php), [PermissionGate](../../core/Security/PermissionGate.php), [Csrf](../../core/Security/Csrf.php), [LoginAttemptRepository](../../core/Repository/LoginAttemptRepository.php) | [database contract](../../tests/database_driver_smoke.php), [MVP security paths](../../tests/mvp_http_smoke.py); не полный ASVS audit |
| P05 MFA и UI управления всеми ролями/пользователями | missing | Нынешний [AuthService](../../core/Security/AuthService.php) — password/session; site grants — отдельный реализованный сценарий | MFA acceptance/release exception отсутствуют; G10 |
| P06 Sanitizer, headers и запрет исполнения HTML как PHP | implemented | [HtmlSanitizer](../../core/Content/HtmlSanitizer.php), [SecurityHeaders](../../core/Security/SecurityHeaders.php), [SafeHtml](../../core/View/SafeHtml.php) | [sanitizer](../../tests/html_sanitizer_smoke.php), CSP/browser проверки [browser](../../tests/browser_mvp.cjs); не sandbox для доверенного PHP-макета |
| P07 Lifecycle/dependencies/API compatibility | implemented | [ModuleLoader](../../core/Extension/ModuleLoader.php), [ModuleManager](../../core/Extension/ModuleManager.php), [ManifestReader](../../core/Extension/ModuleManifestReader.php) | [lifecycle](../../tests/module_lifecycle_smoke.php), [manifest](../../tests/module_manifest_smoke.php); новые removal tests описаны ниже отдельно |
| P08 ZIP package install/update/remove/purge | partial | [Lifecycle](../../core/Extension/ModulePackageLifecycle.php), [Purger](../../core/Extension/ModulePackagePurger.php) | [inventory](../../tests/module_package_inventory_smoke.php), [purge](../../tests/module_package_purge_smoke.php); purge legacy запрещён, не обещана полная parity |
| P09 Подпись сторонних пакетов | implemented | [SignatureVerifier](../../core/Extension/ModulePackageSignatureVerifier.php) | [signature smoke](../../tests/module_package_signature_smoke.php); обязательность конфигурируется, это не лицензирование и не подпись всей CMS |
| P10 Миграции/checksum/dependency order | implemented | [MigrationRunner](../../core/MigrationRunner.php), [Coordinator](../../core/Extension/ModuleMigrationCoordinator.php), [MysqlMigrationRunner](../../core/Database/MysqlMigrationRunner.php) | [checksum](../../tests/migration_checksum_smoke.php), [coordinator](../../tests/module_migration_coordinator_smoke.php), [upgrade](../../tests/module_migration_upgrade_smoke.php) |
| P11 PG primary + legacy adapters | implemented | [Database](../../core/Database.php), [SqlDialect](../../core/Database/SqlDialect.php), [driver limits](../database-drivers.md) | Общие database/folders/document-list контракты в пяти DB jobs; DDL/JSON различия изолированы, конвертации PG↔MySQL нет |
| P12 ZIP + browser install, обе раскладки | implemented | [packager](../../tools/package_mvp.py), [InstallationService](../../core/Installer/InstallationService.php), [HostingProbe](../../core/Installer/HostingProbe.php) | [MVP](../../tests/mvp_http_smoke.py), [Apache refusal](../../tests/installer_hosting_http_smoke.py); self-HTTP/assets write/A-record ограничения в [MVP.md](../MVP.md) |
| P13 Online update/changelog/failure recovery | implemented | [UpdaterModule](../../modules/updater/src/UpdaterModule.php), [FileUpdater](../../modules/updater/src/FileUpdater.php), [DeploymentGuard](../../core/Http/DeploymentGuard.php) | [update_smoke](../../tests/update_smoke.php), [update_module](../../tests/update_module_smoke.php), [mysql_update](../../tests/mysql_update_smoke.php); CLI recovery не равен no-SSH web recovery |
| P14 Full backup/restore/no-SSH recovery | missing | Полного backup-модуля в инвентаре нет; [MysqlSnapshot](../../core/Database/MysqlSnapshot.php) и updater backup решают восстановление обновления | Backup/media/config restore drill отсутствует; [UPDATES](../UPDATES.md), #121 |
| P15 File cache, tags, shared render dependencies | implemented | [Core cache composition](../../core/Extension/Core.php), [RenderContext](../../core/View/Render/RenderContext.php), [DeliveryApi](../../core/Extension/Api/DeliveryApi.php) | [cache](../../tests/delivery_cache_smoke.php), [invalidation](../../tests/delivery_invalidation_routes_smoke.php), [page cache](../../tests/page_static_cache_smoke.php) |
| P16 Static publisher | partial | [StaticPagePublisher](../../core/Delivery/StaticPage/StaticPagePublisher.php), [Permalink](../../core/Routing/Permalink.php) | [static generation](../../tests/static_page_generation_smoke.php), [page_static_cache](../../tests/page_static_cache_smoke.php); CLI snapshots не включают автоматическую static serving/canonical URL migration |
| P17 Redis/cluster/object storage/jobs | partial | [CacheBackendInterface](../../core/Delivery/Cache/CacheBackendInterface.php), [RuntimeApi](../../core/Extension/Api/RuntimeApi.php), [scaling constraints](../architecture/infrastructure-scaling.md) | Контракт внедрения есть; Redis adapter, S3 media и multi-node deployment/recovery tests отсутствуют |
| P18 Аудит/health/observability | partial | [AuditLogRepository](../../core/Repository/AuditLogRepository.php), [HealthController](../../core/Controller/HealthController.php), [ErrorHandler](../../core/ErrorHandler.php) | HTTP health/audit в existing tests; correlation IDs, jobs/retry dashboard и полные эксплуатационные сценарии отсутствуют |
| P19 Подписанный release/SBOM/reproducible build policy | partial | [package_mvp](../../tools/package_mvp.py), [package_update](../../tools/package_update.py), [mvp workflow](../../.github/workflows/mvp.yml) | [update_release](../../tests/update_release_smoke.php), hashes/manifests есть; независимая CMS release signature/SBOM/reproducibility proof отсутствуют |

## Content

| ID / возможность | Статус | Код | Проверки и незакрытая часть |
| --- | --- | --- | --- |
| C01 Structure/page types/provisioning | implemented | [StructureController](../../core/Controller/StructureController.php), [ProvisioningService](../../core/Structure/StructurePageProvisioningService.php), [PagesApi](../../core/Extension/Api/PagesApi.php) | [provisioning](../../tests/structure_page_provisioning_smoke.php), [page selection](../../tests/page_type_selection_smoke.php) |
| C02 Полный Structure UX, keyboard moves/URL migrations | partial | [structure form](../../templates/admin/structure/form.php), [StructureRepository](../../core/Repository/StructureRepository.php) | [MVP](../../tests/mvp_http_smoke.py); keyboard acceptance/redirect consistency не завершены, PR85 отдельно |
| C03 Documents: drafts/publish/history timestamps | implemented | [DocumentRepository](../../modules/documents/src/Repository/DocumentRepository.php), [controller](../../modules/documents/src/Admin/DocumentController.php) | [domain](../../tests/documents_domain_smoke.php), [admin](../../tests/documents_admin_smoke.php), [frontend](../../tests/documents_frontend_smoke.php) |
| C04 WYSIWYG/autosave/preview/diff/restore/conflicts | partial | Documents revision guard/authors: [owner](../../modules/documents/src/Admin/DocumentAdminService.php), [form](../../modules/documents/templates/admin/documents/form.php) | [five-DB concurrency](../../tests/document_revision_smoke.php), [HTTP](../../tests/document_revision_http.py), [browser](../../tests/browser_document_revisions.cjs); preview #126, autosave/diff/restore missing; editor PR154 separate |
| C05 Папки документов/ИС/макетов, не URL | implemented | [FolderRepository](../../core/Content/FolderRepository.php), [FoldersApi](../../core/Extension/Api/FoldersApi.php), owner controllers | [content_folders](../../tests/content_folders_smoke.php), multisite HTTP [MVP](../../tests/mvp_http_smoke.py) |
| C06 Document bounded list/filter/sort | implemented | [DocumentListQuery](../../modules/documents/src/Admin/DocumentListQuery.php), [management repository](../../modules/documents/src/Repository/DocumentManagementRepository.php) | [document_list](../../tests/document_list_smoke.php), browser pagination [browser](../../tests/browser_mvp.cjs) |
| C07 IS/layout bounded lists + explicit-ID batch actions | partial | [IS list tests](../../tests/infosystem_list_smoke.php), [layout list tests](../../tests/layout_list_smoke.php): SQL count/LIMIT/OFFSET, scoped search/folders, stable sort | #123 delivered PR98; #124 bounded layouts; #127 explicit-ID bulk acceptance remains missing |
| C08 ИС: группы/элементы/JSON properties | implemented | [IS management](../../modules/infosystem/src/Repository/InfosystemManagementRepository.php), [FieldSchema](../../modules/infosystem/src/FieldSchema.php) | [infosystem module](../../tests/infosystem_module_smoke.php), [admin search](../../tests/admin_search_smoke.php), [JSONB](../../tests/jsonb_repository_smoke.php), common DB contract |
| C09 ИС 2.0 types/defaults/media/references/presets | partial | FieldSchema поддерживает text/textarea/number/boolean/select/date + required/filterable/options | [DB contract](../../tests/database_driver_smoke.php) покрывает subset; decimal/media/reference/defaults/presets отсутствуют. Date/finite-number validation исправлена в G1 #108; exact decimal остаётся missing |
| C10 Menu render/dynamic sources/bridge | implemented | [MenuModule](../../modules/menu/src/MenuModule.php), [bridge](../../modules/menu-infosystem/src/MenuInfosystemModule.php) | [menu_frontend](../../tests/menu_frontend_smoke.php), [structure source](../../tests/menu_structure_source_smoke.php), [bridge](../../tests/menu_infosystem_bridge_smoke.php) — преимущественно PG suite |
| C11 Полноценный menu editor | missing | Menu runtime зарегистрирован, owner admin CRUD/editor отсутствует | Нет E2E редактора; #110, не добавлять меню в Core |
| C12 PHP layouts/hierarchy/stages/facades | implemented | [LayoutHierarchyResolver](../../core/Layout/LayoutHierarchyResolver.php), [PageRuntime](../../core/Page/PageRuntime.php), [PhpRenderer](../../core/View/PhpRenderer.php) | [hierarchy](../../tests/layout_hierarchy_smoke.php), [layout code](../../tests/layout_code_smoke.php), [runtime continuation](../../tests/page_runtime_continuation_smoke.php) |
| C13 Typed block/page builder/asset registry | missing | Render composition/stages — техническая база, не редактор конфигураций блоков | Нет builder E2E/fingerprinting acceptance; #112 |
| C14 Media upload/reuse/variants/usage | missing | `media` отсутствует в manifests baseline | Upload package tests не являются media tests; #111 |
| C15 Полноценный SEO/redirects/sitemap | partial | [PageViewModel](../../core/View/PageViewModel.php), [main layout](../../templates/layouts/main.php) дают title/description | Canonical/robots/sitemap inheritance/OG/hreflang owner отсутствует; будущие тесты #113 |
| C16 Published full-site search | missing | Admin navigation/list search не public search; `search` отсутствует в manifests | Нет indexing/query acceptance; #114 |
| C17 Typed document Actions + notifications | missing | Actions/notifications owner отсутствует; HTML sanitizer не является dispatch API | Нет submission/SMTP/idempotency/retention acceptance; #115; Forms не обязательная зависимость |
| C18 Localization + regions | missing | `localization`/`regions` отсутствуют; [product variants](../architecture/product-variants.md) — план | Нет 2×2×2 acceptance; #116. Язык UI не равен языку публикации |
| C19 Mechaniki reference product | partial | [MechanikiModule](../../modules/mechaniki/src/MechanikiModule.php) — opt-in пилот /mechaniki, Documents | [MVP pilot](../../tests/mvp_http_smoke.py); перенос в корень и реальные IS resources объединены PR96; [integration](../../tests/mechaniki_stage_smoke.php) и [distribution](../../tests/mechaniki_distribution_smoke.py) добавлены. Runtime формы не разрешены; staging mock не доказывает обработку заявки. Live deployment этой ревизии не проверен |
| C20 Общий admin shell/a11y/UX | partial | [AdminPhpRenderer](../../core/View/AdminPhpRenderer.php), [AdminApi](../../core/Extension/Api/AdminApi.php), [design rules](../admin-design-system.md) | [browser](../../tests/browser_mvp.cjs), [module template scope](../../tests/module_template_scope_smoke.php); три browser flows на 390/1024/1440 с actions/errors/screenshot измерены в PR130; доступность collapsed/mobile controls исправлена PR132; axe/WCAG и полные пользовательские walkthrough остаются release gate |
| C21 Published Infosystem item page | implemented | [executor](../../modules/infosystem/src/InfosystemItemPageExecutor.php), [bounded repository](../../modules/infosystem/src/Repository/InfosystemRepository.php), [generic not-found contract](../../core/Page/PageNotFoundException.php) | PR136/main68cd931; [module tests](../../tests/infosystem_module_smoke.php) проверяют site/publication/group visibility, view и cache dependencies. Полный новый сценарий — PG smoke; legacy общий subset не заменяет отдельный item HTTP contract |

## Commerce и коммерческая упаковка

| ID / возможность | Статус | Подтверждение отсутствия и критерий |
| --- | --- | --- |
| S01 Product/category/SKU/prices/availability | missing | Нет catalog owner в manifests; нет product/SKU acceptance. #117 |
| S02 Indexed facets/SEO landings/CSV import | missing | IS JSON filter не Catalog facet index; нет import/resume/price parity tests. #117 + #113 |
| S03 Cart/checkout/orders/stock/idempotency | missing | Нет commerce owner и transactional order tests. #118 |
| S04 Payments/webhooks/refunds/delivery | missing | Нет provider implementation/sandbox acceptance. #119; approval before critical provider work |
| S05 Editions/bundles/standalone entitlements | missing | [product-variants](../architecture/product-variants.md) фиксирует модель продаж, но licensing/market владельцев нет; #120 |
| S06 Независимое распространение ZIP-модулей | partial | Package lifecycle/signatures реализованы (P08/P09), каталог покупок и лицензий отсутствует |

Источник для negative inventory: [ModuleLoader](../../core/Extension/ModuleLoader.php), семь фактических manifests ниже, [CI suite](../../.github/workflows/ci.yml). `shop.product` в runtime smoke — синтетический render source теста, не реализованный магазин.

## Пакеты, зависимости и владельцы

Все manifests baseline требуют Core >=0.4.0 и Extension API ^1.0. `migration_after` — порядок SQL, а не runtime dependency.

| Пакет / manifest | Версия | Default | Runtime requires кроме Core | Page/facade/routes |
| --- | --- | --- | --- | --- |
| [documents](../../modules/documents/module.json) | 1.4.0 (G1 #149; baseline 1.3.0) | on | нет; migration_after infosystem | documents.page (default), documents facade, /admin/documents |
| [infosystem](../../modules/infosystem/module.json) | 1.3.0 (G1 #149; baseline 1.2.0) | on | нет | infosystem.list + infosystem.item (PR136), infosystems facade, /admin/infosystems |
| [menu](../../modules/menu/module.json) | 1.1.0 | on | нет | menus facade, navigation source; admin editor отсутствует |
| [menu-infosystem](../../modules/menu-infosystem/module.json) | 1.1.0 | off | menu ^1.0, infosystem ^1.0 | bridge navigation source, не отдельное хранилище |
| [sites](../../modules/sites/module.json) | 1.2.0 | on | нет | /admin/sites; Core владеет site identity/access primitives |
| [updater](../../modules/updater/module.json) | 1.3.0 | on | нет | /admin/updates; global permission |
| [mechaniki](../../modules/mechaniki/module.json) | 1.2.0 | off | documents ^1.0 + infosystem ^1.2 | opt-in pilot и root-site import, reusable public IS views; sidebar скрыт |

Core владеет Structure/layouts, users/RBAC, site identity, generic bindings, folders, routing/render/cache/lifecycle. Удаляемые домены владеют собственными controllers/templates/tables. Providers ИС: `InfosystemProvider` вызывает `InfosystemModule`, затем регистрирует page type — это используемый код, не доказанный dead code. `module.php` — поддерживаемый fallback reader; не удалять только из-за наличия JSON.

## Реальные API (не обещания REST)

[Core](../../core/Extension/Core.php) экспортирует typed API; [freeze tests](../../tests/runtime_smoke.php), [page registry](../../tests/page_execution_registry_smoke.php) подтверждают boot contract.

| Группа | Контракты | Подтверждение |
| --- | --- | --- |
| Routes/admin/permissions/folders | get/post/add; navigation; permission definitions; folder scope definitions | [RoutesApi](../../core/Extension/Api/RoutesApi.php), [AdminApi](../../core/Extension/Api/AdminApi.php), [PermissionsApi](../../core/Extension/Api/PermissionsApi.php), [FoldersApi](../../core/Extension/Api/FoldersApi.php); manifest/lifecycle/folders tests |
| Pages/templates/content | page type/executor/provisioner/stages; facade/view; render source | [PagesApi](../../core/Extension/Api/PagesApi.php), [TemplatesApi](../../core/Extension/Api/TemplatesApi.php), [ContentApi](../../core/Extension/Api/ContentApi.php); page/runtime/layout tests |
| Events/experimental registry | typed-object listen/dispatch; register/get/all | [EventsApi](../../core/Extension/Api/EventsApi.php), [ExtensionsApi](../../core/Extension/Api/ExtensionsApi.php); runtime/menu bridge tests |
| Runtime/sites/cache/delivery | shared PDO/root/backend, site context, cache/tags, static publisher | [RuntimeApi](../../core/Extension/Api/RuntimeApi.php), [SitesApi](../../core/Extension/Api/SitesApi.php), [CacheApi](../../core/Extension/Api/CacheApi.php), [DeliveryApi](../../core/Extension/Api/DeliveryApi.php); database/site/cache tests |

## PR и коллизии при сверке baseline

| PR | В baseline? | Правило интеграции |
| --- | --- | --- |
| [85](https://github.com/ultima-vox/cms/pull/85) permalink conflicts | нет | Сверить с URL/SEO задачами, не заявлять canonical migration готовым |
| [96](https://github.com/ultima-vox/cms/pull/96) Mechaniki root | да, mainbd5c875 | Documents + реальные Infosystems, owner views/import; private resources дополнены PR138. Live deployment не подтверждён; не менять mechaniki.ru и не включать server forms |
| [98](https://github.com/ultima-vox/cms/pull/98) IS lists | нет | Сохранить работающий код, интеграция #123 после G0, CI на combined head |
| [99](https://github.com/ultima-vox/cms/pull/99) preliminary audit | нет | Источник предварительных находок; формальные файлы этой карты — отдельный PR104-task |
| [101](https://github.com/ultima-vox/cms/pull/101) full plan | да, main0a10ade | Документ вошёл во время G0; runtime не изменён, pinned revision остаётся источником ТЗ |

## Архитектура, дефекты и риски

| Находка | Класс/важность | Доказательство / действие |
| --- | --- | --- |
| Прямых business module imports или literal domain SQL в текущем Core не найдено | Проверенный bounded scan, не абсолютное доказательство | [existing guard](../../tests/core_module_boundary_smoke.php); новый [SQL/import guard](../../tools/check_core_boundaries.py) + [negative self-test](../../tests/core_boundaries_check_test.py). Dynamic SQL требует review |
| Отсутствие физического пакета не покрывалось full-application fresh-process проверкой | G0 regression gap | Новый [module_removal_smoke](../../tests/module_removal_smoke.php): isolated copies, dependent closure, Core/admin/independent page, two sites, all seven single-package cases + all/baseline. Таблицы не удаляются; это не purge/data-migration acceptance |
| Исторические Core migrations создают IS domain tables | Документированный migration debt | [001](../../database/migrations/001_initial.sql), [005](../../database/migrations/005_infosystems.sql), [Documents migration ownership](../../modules/documents/migrations/001_documents.sql). Не переписывать применённые файлы; runtime ownership и история различаются |
| Календарные даты и диапазон чисел ИС | G1 #108: исправление baseline-дефекта | [FieldSchema](../../modules/infosystem/src/FieldSchema.php) проверяет checkdate, finite float и underflow; [property smoke](../../tests/infosystem_property_validation_smoke.php), [HTTP](../../tests/infosystem_property_http.py) и browser regression. Существующие некорректные записи автоматически не исправляются |
| TTL=1 second fresh-read тест зависел от wall-clock; исправлено | Resolved #107 | [page_static_cache_smoke](../../tests/page_static_cache_smoke.php), исторический run39242873559 failure. PR135/mainf1855d1 заменил ожидание на явные fixture states и проверку точного expiry; main CI39254909750/MVP39254909774 success. [Issue107](https://github.com/ultima-vox/cms/issues/107) закрыт |
| No-SSH crash recovery отсутствует | Production operational gap | [UPDATES](../UPDATES.md), [recover CLI](../../bin/update-recover.php); #121. Unknown commit остаётся maintenance |
| MySQL snapshot имеет ограничения и implicit DDL | Compatibility/update risk | [MysqlSnapshot](../../core/Database/MysqlSnapshot.php): 256MiB, InnoDB/dedicated DB, без routines/events/views; dirty migration refuses retry. Тесты mysql_update/failure; #106 |
| Self-HTTP hosting proof может отказать на корректном, но недоступном серверу origin | Hosting support limitation | HostingProbe + [MVP](../MVP.md): outbound/CA/assets/A-record/worker constraints, нет unsafe bypass; #106 |
| Release integrity не равна подписи publisher | Release gate gap | SHA/TLS есть, CMS release signature/SBOM нет; #120/#122 |
| Общий static/type/security analysis и полный unused-code audit не выполнены | Evidence gap | Composer/CI не содержит PHPStan/Psalm/SAST/dependency-audit gate; targeted source checks не заменяют их; G10/#122 |
| UX/performance baseline измерен, интеграция отдельно | Evidence ready / review #105 | PR130: 9 browser observations, screenshots и actions/errors. PR133: Content 1k/10k, пять DB, cold/warm CLI p50/p95/SQL/allocated memory, raw provenance; head7760377 CI39255626188/MVP39255626206/G039255626397 success. CLI не равен HTTP/FPM/FASTPANEL; provisional thresholds не приняты |

Нового подтверждённого P0 data-loss/RCE/cross-site дефекта в просмотренных сценариях не найдено. Это не security sign-off: #108 исправлен в G1; отдельная security review и ещё не реализованные домены остаются gates. Условия перехода — [release-gates.md](release-gates.md).

## Очередь после G0

- G0: inventory, UX/performance evidence и executable guards интегрированы PR129/130/133; technical acceptance разрешает G1 в пределах release-gates.md. #106 сохраняет явные compatibility/release ограничения; это не приёмка production1.0.
- G1: #109 → маленькие задачи #123 (существующий IS list), #124 (layouts), #125 (document revisions/conflicts), #126 (preview; после #125), #127 (Documents bulk move), #128 (Structure UX), #142 (Documents list return); #108 — validation fix.
- Эпики плана02–15: #109–#122. Зависимости и acceptance находятся в issue bodies и epic102. Никакая из этих функций не помечена реализованной из-за созданного issue.
- CRM/ERP/WMS, AI editor, frontend SPA-builder, full Forms-конструктор и обязательный external search — вне 1.0 по выбранному ТЗ.

### Последующий main: Mechaniki media

PR140 добавляет проверенную локальную синхронизацию 14 ресурсов (`modules/mechaniki/site/sync-media.php`, `SiteMedia.php`) и `tests/mechaniki_media_smoke.php`; соответствующий CI step сохранён. Это доставка ресурсов пилотного сайта, а не самостоятельная медиабиблиотека: C14 остаётся missing. Ни deployment, ни обработка заявок этим не подтверждаются.

## Наборы приёмки

[Fixture profiles](../../tests/fixtures/g0/profiles.json) связывает реальный Content-v1 fixture с измеренным snapshot. Multilingual-corporate и commerce-site имеют статус blocked, явные зависимости #116–#119 и критерии будущих данных/проверок. Они не заселены и не считаются пройденными до появления владельцев доменов.

## G1: capability editor links (#149)

Core0.5.0 / Extension API1.1.0 — additive optional page-editor capability; [contract](../architecture/core-module-boundary.md#g1-pagecontent-editor-handoff-149). Documents1.4.0 и Infosystem1.3.0 требуют Core>=0.5 / API^1.0 (совместимость API1.x). Прямой переход и проверенный обратный контекст реализованы отдельно от WYSIWYG/preview/autosave. Полная production приёмка остаётся NO-GO.

### G1 delta: monetary property editing

Infosystem `money` properties accept major-unit editor input (`100.05`, `1 234,56`) and store exact integer minor units in JSON. `MoneyValue`, `FieldSchema::normalizeEditorProperties`, the item/filter templates and `infosystem-money.js` own parsing, comma normalization and grouping; ordinary approximate `number` properties retain their existing contract. Two decimal places, nonnegative amounts, maximum `999 999 999 999.99`; malformed grouping, exponent notation, excess precision and overflow are rejected rather than rounded.

New owner migrations `004_money_editor.sql` / `mysql/002_money_editor.sql` update only the original `mechaniki_prices.amount_minor` field definition with its original name/type. Item amounts, custom fields, permissions and publication state are not rewritten. Mechaniki 1.3 requires Infosystem ^1.4; its price views use the current amount/currency/from flag, with textual fallback for entries without an amount. Custom renamed price fields require deliberate schema selection rather than a guessed conversion.

Evidence: `tests/infosystem_money_smoke.php` (exact values, PG/MySQL/MariaDB JSON persistence, populated schema upgrade, repeat execution and site isolation), `tests/infosystem_money_smoke.cjs` (immediate comma replacement, caret, grouping), `tests/infosystem_property_http.py` (real create/update/422, atomic rejection, correction and existing security guards), `tests/browser_mvp.cjs` (money editing at 1440/1024/390px). Production release gates remain open; these checks do not prove deployment to FASTPANEL.

### G1 delta: bounded Infosystem lists (#123 / PR98)

The Infosystem index now uses `InfosystemListQuery` and `InfosystemManagementRepository::page()` rather than loading every system and filtering folders in PHP. SQL applies the current site and selected folder, literal name/code search, activity filter, whitelisted stable sort and a limit of 25/50/100 rows. Count/row predicates are shared, out-of-range pages clamp to the last page, and pagination retains filter/folder state. Internal `all()` callers remain compatible. Core, config and schema are unchanged; Infosystem 1.5.0 includes the preceding 1.4 monetary-property support.

C07 remains **partial** overall: this closes the Infosystem-list portion; layouts (#124) and explicit-ID bulk operations (#127) still require their own acceptance. The original PR98 implementation is reused and refreshed against current main rather than replaced by a competing PR.

Evidence: `tests/infosystem_list_smoke.php` covers folder/root/all scope, two-site isolation, Unicode and literal wildcard search, active/inactive states, non-overlapping pages, deterministic equal-name ordering, limits, malformed inputs and foreign folders on five DBs. `tests/infosystem_list_http.py` exercises the real list and navigation through PG/legacy installations, including XSS escaping and cross-site refusal. `tests/browser_mvp.cjs` checks the filter/page journey at 1440/1024/390px. These are bounded editor-list checks, not a production concurrency or FASTPANEL deployment claim.


### G1 UX rebaseline — 2026-10-20

На main e0476aff проведено сопоставление редакторских процессов с официальными источниками: [HostCMS UX parity](hostcms-ux-parity.md), [ADR навигации](../architecture/admin-ux-navigation.md). Это корректировка очереди и критериев, без runtime/schema changes. Flat admin navigation остаётся partial; favorites, saved list views/columns, shared sticky actions, dirty guard и revision-aware form recovery не считаются реализованными. Завершённые #123/#142/#149 сохраняют свою bounded приёмку. #156/P0 и #158/P0 предшествуют следующему UI расширению; #157/#159 дополняют #109. G1 остаётся открытым, production1.0 — NO-GO.

### G1 delta: global/local admin navigation (#156)

Core0.6/API1.2 adds compatible owner-declared sections; Documents1.5, Infosystem1.6, Sites1.3 and Updater1.4 require Core>=0.6 / API^1.0. The shared shell groups permission-filtered items, omits empty sections, derives the active section from the route owner's `admin_active`, and preserves the full Ctrl+K registry. The left panel contains only the current section; native mobile disclosures retain labels and work without scripts. No new Core/config files, schema changes or applied migration edits. Legacy extensions default to Services.

Evidence: `tests/admin_navigation_smoke.php` checks actual limited-role authentication, owner registrations, legacy compatibility, rendered active navigation, empty/forbidden sections and a forbidden second site on five DBs. `tests/browser_mvp.cjs` checks 390/1024/1440, primary→local links, cross-section palette, reload, obsolete collapse preference, Escape/focus, denied storage and no-JS; 720px checks effective 200% reflow. Existing HTTP site/CSRF/owner-denial, disable/removal, editor/list-return and populated-update checks remain required. This closes the navigation slice only: sticky form actions #158, favorites #157, dirty guard/autosave and complete G1 acceptance remain open. No production deployment or WCAG certification is claimed.

### G1 UX correction: vertical grouped navigation

After testing v0.1.178, the owner rejected the horizontal global/local arrangement. #156 is reopened for a single vertical sidebar: native section disclosures with nested owner links, active group opened from the route, one Overview link and no horizontal section bar. Existing Core0.6/API1.2 metadata, permission filtering, current-site context and full Ctrl+K remain. This supersedes the layout portion of the preceding navigation delivery; production/G1 acceptance is not inferred from the earlier green CI. See the updated navigation ADR and U01 parity row.

### G1 delta: Documents long-form actions (#158, first owner)

Documents1.5.1 requires Core>=0.6.1/API^1.0. One native action group remains inside the owner form; shared CSS docks it at the bottom of the viewport while its form is in view. There are no cloned controls or generic business dispatcher. Submit intent (`save_mode=draft|publish`), native validation, CSRF/site and return contexts are unchanged. Core0.6.1 shared pending UI keeps the successful submitter enabled, marks controls aria-disabled and rejects repeated submit events; canceled events and pageshow clear the pending state. This is a client guard, not server idempotency, conflict detection or autosave.

Resize/focus handling reserves scroll margin for fields, and short visual viewports use normal flow rather than a docked overlay. No-JS native forms remain functional. `browser_form_actions.cjs` checks real native draft/publication POSTs, native repeated-submit rejection, canceled submit recovery, retained 422 inputs, keyboard focus, top/middle/bottom geometry, 390/1024/1440 and effective 720px/200% reflow. Short viewport is an automated geometry check, not certification on real OS keyboards. Existing five-DB HTTP return-context tests assert one action set and both owner submit modes. Infosystem adoption is the separate second PR; #158 remains open until both owners are delivered.

### G1 delta: Infosystem action forms (#158, second owner)

Infosystem1.6.1 requires Core>=0.6.1/API^1.0 and adopts the same native action-bar/pending presentation in schema, group and item forms. Publication continues to use the owner status/date controls; the item action group states this explicitly. Delete/danger-zone forms stay independent and unchanged. No migrations, Core/business API changes or editor replacement.

`browser_infosystem_actions.cjs` creates an isolated 33-property schema through the real authenticated owner endpoint and checks long item-form top/middle/bottom geometry, one action set, schema/group native saves, rejected money precision with inline 422/focus/correction, preserved published/draft statuses, no-JS and 390/1024/1440/effective-200% reflow. Existing five-DB property HTTP tests verify the action group on create/422 together with unchanged CSRF/site rejection and atomicity. This completes the Documents/Infosystem presentation slice only; revision-aware autosave/dirty guard/conflicts remain missing or partial under their existing tasks.

Action-bar update compatibility: Core0.6.2 versions the shared admin CSS/JS URLs. Documents1.5.2 and Infosystem1.6.1 require >=0.6.2/API^1.0 so an owner cannot opt into this contract before the corrected shared shell. The browser test serves the old submitter-disabling code at the obsolete unversioned URL; native publish intent must still survive, proving the new page loads a fresh shared handler. This is scoped to shared admin assets, not the full G2 asset registry/fingerprinting API.

### G1 delta: bounded layout list (#124)

Core0.6.3 keeps template infrastructure in its existing owner: `LayoutRepository::page()` applies shared site/folder/search predicates to count and LIMIT/OFFSET, with literal Unicode name/code search, allowlisted stable ordering and 25/50/100 rows. Internal `all()` template selectors are unchanged. No schema, migration, configuration or Extension API change; no new Core files or general query framework. `LayoutController` retains `layouts.manage` before folder lookup and renders escaped filter/pagination controls. Pagination preserves folder/search/sort/page size; edit/save return context is not added by this slice.

[Repository/permission tests](../../tests/layout_list_smoke.php) cover five supported DBs, malformed/huge input, equal-name ordering, literal wildcards, foreign site/scope folders and direct IDs. [HTTP journeys](../../tests/layout_list_http.py) run on clean PostgreSQL and legacy installs; [browser checks](../../tests/browser_mvp.cjs) cover 390/1024/1440, filter retention, next/previous and overflow. Existing module-removal/security/install/update suites remain required. Layout CSS URLs include the Core version to refresh existing browsers. G1 stays open for Structure measurement, IS item lists/bulk operations and other editor requirements.

### G1 delta: Documents revision guard and authors (#125)

Documents1.6.0 owns the additive `007_version_author.sql` (PG) / `mysql/002_version_author.sql` migrations. Nullable `author_user_id` references Core users with ON DELETE SET NULL; previous/system versions stay unknown, not falsely attributed. History displays escaped current display names or “Не указан”; content/time/version data remains intact. No new Core/config files or Extension API changes. `createDraftVersion()` adds an optional author argument for compatible trusted callers; the internal admin save now requires the expected version ID.

The owner reads name/folder/content/base version in one SQL snapshot. The form posts its original expected version, including 0 for a versionless provisioned document. Save takes the site-scoped document lock and a current locking read of the latest version before metadata/content/publication writes, including under InnoDB REPEATABLE READ. Stale/missing/malformed tokens return409; rejected text and tab-local list/page context remain, and the old token is never silently replaced. Authorship comes from authentication, never a form user ID. Explicit review/reload opens a new tab; no force overwrite, merge, autosave, diff or preview is introduced.

[Five-DB concurrency contracts](../../tests/document_revision_smoke.php) launch two independent PHP/DB workers and require one save plus one conflict with preserved publication/history/metadata. [HTTP](../../tests/document_revision_http.py) verifies unchanged rejected audit/state, server authors, token validation and correction; [browser](../../tests/browser_document_revisions.cjs) verifies three widths, two tabs, retry/context/text preservation and no-JS. Existing delivery tests assert rejected409 does not evict winner cache/audit successful writes. The populated v0.1.20 PG update journey verifies historic unknown authors and guarded saves; this does not close the full legacy populated-release/hosting matrix in #106.

Recovery: PG migration is transactional. Legacy uses one ALTER under the existing dirty-migration journal; after interrupted DDL preserve DB/journal and restore a consistent backup, not blind retry. Columns are additive and historical content is not transformed. Prefer forward repair; reverting the owner also removes its concurrency protection, so keep editing paused during a recovery. Forms opened before upgrade must reopen the current editor. Existing trusted low-level repository writes remain compatible; this guard governs administrative saves, not a universal entity revision API. #126 preview follows this owner contract.
