# Ultima Vox CMS — исполнимый план разработки до production 1.0

Статус: **проект плана для утверждения**, не заявление о готовности каких-либо новых модулей.
Дата ревизии: 2026-10-19.
Исходный код: https://github.com/ultima-vox/cms
Связанные документы: docs/architecture/hostcms-roadmap.md, docs/architecture/product-variants.md, docs/architecture/core-module-boundary.md, docs/architecture/infrastructure-scaling.md, docs/database-drivers.md, docs/UPDATES.md.

## 0. Миссия и границы релиза

Разработать самостоятельную модульную CMS, удобную для редактора как HostCMS, но с более предсказуемой архитектурой, простым развёртыванием, меньшим количеством лишнего кода и современными интерфейсами. Целевые сайты: корпоративные, контентные и полноценные небольшие интернет-магазины.

**Production 1.0 означает**: CMS можно установить, настроить, обновить, резервировать, восстановить и эксплуатировать на реальном сайте без разработчика, а редактор выполняет основные ежедневные задачи через интерфейс; коммерческий сайт может оформлять заказы без риска потери денег или двойных заказов.

**Production 1.0 НЕ означает** полную функциональную эквивалентность всем редакциям HostCMS, собственную CRM/ERP/WMS, маркетплейс, SEO-комбайн, Manticore или бессрочный SLA поддержки всех конфигураций хостинга. Эти расширения допускаются только отдельными пакетами и после 1.0.

Профили приёмки:
- Content: корпоративный сайт и публикации, меню, документы, изображения, SEO, поиск, переводы и региональные варианты.
- Commerce: каталог, модификации, фасеты, корзина, checkout, заказы, минимальные платежи/доставка, налоговые и валютные настройки без самодельного банковского эквайринга.
- Platform: многосайтовость, редакторские роли, управляемая установка/обновление, управление лицензиями модулей, backup/restore, диагностика.

## 1. Исходная точка: что уже реализовано и что нельзя считать готовым

В текущем main уже есть PHP 8.4, маршрутизация и site context, Structure, Documents с версиями, Infosystem с группами/JSON-свойствами, нативные PHP layouts, API расширений, generic bindings/page stages, модульный lifecycle/migrations, RBAC/CSRF, базовый кеш, установщик для двух webroot layout, обновлятор, PostgreSQL primary + MySQL/MariaDB legacy, CI и MVP acceptance.

Не объявлять завершёнными без отдельной проверки: визуальный редактор блоков, редактор меню, медиабиблиотека, SEO-центр, публичный поиск, локализация контента, мультирегиональность, commerce, документные действия и приём заявок, marketplace/entitlements, полноценный backup/restore. Название модуля/страница в админке само по себе не является подтверждением полноты функциональности.

Существующий HostCMS-inspired roadmap остаётся стратегическим документом; этот документ задаёт **исполняемую последовательность**. Перед каждой задачей агент сверяет актуальную ветку main и открытые PR, исключает дублирование и фиксирует реальный gap.

## 2. Какие решения брать из HostCMS

| Практика / сценарий | Берём | Не переносим |
| --- | --- | --- |
| Рабочий стол, быстрое меню, списки/фильтры, групповые операции, избранное | общий admin shell и унифицированный CRUD UX | тяжёлую систему классов для рендеринга каждой формы |
| Структура сайта + документы + информационные системы | один понятный пользовательский путь; страницу связываем с объектом через page type/binding | текст в core.nodes, XML/XSLT и PHP в карточке структуры |
| Настраиваемые свойства и списки значений | типизированные схемы полей, валидаторы, фасеты и ссылки между сущностями | универсальный EAV для всех свойств и многократные JOIN без профилирования |
| SEO-мета, шаблоны, SEO-фильтр | централизованная политика canonical/indexation/redirects + управляемые SEO-посадочные | автоматическую индексацию бесконечных комбинаций фильтров |
| Модификации, быстрый фильтр, заказы | SKU-варианты, фасетный индекс, состояния заказа, проверяемые скидки | склад/закупки/CRM как обязательные зависимости магазина |
| Каталог модулей, редакции и установка пакетов | независимые модули, подписанные пакеты, права, совместимость | установщики, свободно запускающие произвольные PHP-скрипты или меняющие Core |
| Простая установка и резервирование | webroot ZIP + preflight, recovery и restore drill | обещание безопасности любой произвольной конфигурации nginx |

Официальные ориентиры (использовать как поведенческие референсы, **не копировать исходный код**):
- https://www.hostcms.ru/hostcms/interface/
- https://www.hostcms.ru/documentation/modules/structure/
- https://www.hostcms.ru/documentation/modules/informationsystem/
- https://www.hostcms.ru/documentation/modules/informationsystem/item/property/
- https://www.hostcms.ru/documentation/modules/shop/fast-filter/
- https://www.hostcms.ru/documentation/modules/shop/item/modification/
- https://www.hostcms.ru/documentation/modules/shop/orders/orders/
- https://www.hostcms.ru/documentation/modules/market/market-item-install/
- https://www.hostcms.ru/documentation/modules/backup/create/
- https://www.hostcms.ru/documentation/introduction/install/step-by-step/install/
- https://www.hostcms.ru/api7/

Не портировать API HostCMS буквально: классы Core_*, TДC, XML entities, XSL templates, SQL-таблицы и лицензирование стороннего продукта не являются архитектурой Ultima Vox CMS.

## 3. Инварианты архитектуры (без исключений для новых функций)

1. Core владеет HTTP, site context, авторизацией/RBAC, рендерингом, generic bindings, event/API contracts, кэшем, аудитом, миграционным движком и lifecycle. Business data живёт в модуле. Core не импортирует бизнес-модуль и не SQL-запрашивает его таблицы.
2. Один модуль = связный домен, а не десятки микропакетов. Межмодульные зависимости отражены в manifest и покрыты проверкой зависимостей/удаления. Module removed -> неповязанные страницы и admin продолжают работать.
3. PHP 8.4+, strict_types=1, PSR-12, типизированные DTO/интерфейсы, SQL prepared statements. Презентация — нативный PHP с безопасными text/html helpers; API-first через application services и сериализуемые DTO. Не вводить второй template engine.
4. PostgreSQL 16+ — default/main. MySQL 8.0/8.4 и MariaDB 10.11/11.4 — явно выбранная legacy ветка. Сохранить эквивалентное пользовательское поведение в заявленном поддерживаемом subset; отличия DDL/JSON/indexing покрывать dialect + CI matrix. Никакой автоматической конвертации PG↔MySQL.
5. Совместимость shared-хостинга **без изменения домашней директории**. Поддерживать две сборки: управляемый public/ и безопасный webroot с закрытым app/. Нельзя заявлять поддержку nginx, если приватные файлы реально доступны по HTTP.
6. Без Redis/worker/search daemon сайт должен корректно работать. Redis, object storage, отдельный индекс и cron — опциональные адаптеры. Ограниченные synchronous batches + resumable jobs для shared-хостинга.
7. Изменения опубликованных данных должны иметь audit, cache invalidation и site isolation. Multi-site never leaks; locale/region overlays не пересекают границу site.
8. Не переписывать уже применённые SQL migration: новый файл, схема backfill->dual read/write только если оправдано->cutover->cleanup, rollback/forward recovery documented. Перед разрушительным DDL — recovery test.
9. Критический код commerce, лицензирования и обработчиков действий не должен исполнять PHP из пользовательского HTML и не должен требовать прав суперпользователя БД.
10. Каждый модуль сам регистрирует admin UI, public/API endpoints, миграции, permission scopes и diagnostics. Удаление модуля не требует изменения Core.

## 4. Рекомендуемая компоновка пакетов

Сначала описать файловую структуру и контракты каждого нового модуля в ADR, затем код. Следующие пути — целевая структура, а не утверждение, что они уже существуют:

    core/Extension/Api/                  # устойчивые extension contracts
    core/Actions/                        # минимальный реестр и dispatch зарегистрированных действий
    core/Delivery/                       # кеш, теги инвалидации, response/static adapters
    core/Site/                           # сайт, домены, контекст (контекст языка/региона только generic)
    core/Security/                       # CSRF, auth, permissions, rate limit primitives
    modules/documents/                   # документы, версии, workflow публикации
    modules/infosystem/                  # универсальный структурированный контент
    modules/menu/                        # редактирование и рендер меню
    modules/media/                       # media objects, upload, variants, usage
    modules/seo/                         # metadata, redirects, sitemap, indexation rules
    modules/search/                      # site-scoped indexing/query
    modules/localization/                # локализованные версии и UI language packs
    modules/regions/                     # региональные варианты одного сайта
    modules/catalog/                     # товары, варианты SKU, атрибуты, цены, фасеты
    modules/commerce/                    # cart, checkout, orders, promotion policies
    modules/notifications/               # письмо/уведомление и delivery statuses
    modules/backup/                      # backup jobs, archive verification, restore workflow
    modules/licensing/                   # entitlements, plans, signed package licenses
    modules/market/                      # каталог/доставка модулей, совместимость
    docs/architecture/                   # ADR, extension API, ownership matrix
    tests/                               # contract, security, browser, migration, performance

Не создавать перечисленные каталоги заглушками. Каждый возникает только при первой работающей вертикали. Например, payments/shipping — интерфейсы adapters внутри commerce, а отдельные модули только для реальных провайдеров. Внешний ИС-контент не дублируется в Core Documents.

## 5. Специальное решение: действия и отправка из документа БЕЗ обязательного модуля Forms

Пользователь хочет встраивать действие формы непосредственно в документ. Рассмотреть варианты:

A. Выполнять произвольный PHP метод/код из текста документа — **запрещено**. RCE/XSS, непредсказуемые привилегии и невозможность безопасности контент-редактора.

B. **Рекомендуется для 1.0**: typed Document Actions API. Документ включает безопасную декларативную ссылку на заранее зарегистрированный action, например условное обозначение action:contact.request. Ядро разрешает только известные handler IDs и routes, а бизнес-обработчик регистрируется модулем/проектом. В теле документа нет PHP и SQL, пользовательское поле не может назвать произвольный метод. Рендерер выдаёт форму с отдельным CSRF/bootstrap механизмом и разметкой. Request идёт по versioned same-origin endpoint с site-bound DTO validation, Origin/CSRF policy, honeypot, rate limiting, max payload, idempotency key, consent version, статусом и audit, без HTML-исполнения. Кеш публичного HTML не должен содержать общий персональный CSRF. Транспорт email / lead storage подключается через разрешённый handler/notification API. Отсутствие handler — контролируемая недоступность, не fallback к PHP.

C. Отдельный full-featured модуль визуальных форм — **после 1.0**, если сценарии требуют конструктора полей, маршрутизации заявок и workflow. Он использует тот же Actions API, но не обязателен для простой формы обратной связи.

Гейты: формы работают с выключенным JS (server-rendered submission), с JS — progressive enhancement; нет фиктивного успеха; письма в staging не уходят; один request с повтором не создаёт две заявки; consent и retention подтверждены; HTML sanitizer не допускает произвольные event-handlers/form actions.

## 6. Фазы работ и зависимости

Для каждой фазы агент создаёт один ADR/definition of done и последовательность маленьких PR. Не объединять миграцию нескольких доменов, дизайн overhaul и новый API в один большой PR.

### G0 — baseline, тестовые стенды и заморозка контрактов [BLOCKING, первая фаза]

Зависимости: только текущий main.

- Inventory модулей, зависимостей, admin routes, неиспользуемого кода, test coverage, известных багов/открытых PR. Зафиксировать feature matrix: реализовано / частично / отсутствует / вне 1.0.
- Провести baseline UX: три редакторских сценария, число кликов, ошибки, 390/1024/1440 px; записать performance baseline с одинаковыми fixtures и cold/warm cache.
- Завести отдельные фикстуры: content-site, multilingual-corporate, commerce-site; использовать hostname/site isolation и публичный CI baseline.
- CI gates: lint, static analysis, PHPUnit или существующий smoke suite, браузерные тесты, schema migrations PostgreSQL + поддерживаемые legacy DB, package install/upgrade/uninstall, removal boundary check, no tracked vendor.
- Зафиксировать versioning policy и совместимость API/DB: stable extension API, versioned API endpoints, deprecation и upgrade notes.
- Не блокировать дальнейший прогресс рефакторингом ради эстетики. Блокер только реальное нарушение данных/безопасности/установки.

Приёмка G0: reproducible clean install на обоих webroot layout, upgrade старой установки и реестр P0/P1 issues; нет неподтверждённых заявлений о performance.

### G1 — редакторский UX и контентная модель [P0]

Зависимости: G0.

- Структура: drag/reorder c keyboard fallback, понятные типы страниц, видимые site + URL, breadcrumbs, масса действий без случайного пересечения сайтов; root и изменение URL безопасны.
- Общий интерфейс списков: search, filters, columns, sorting, pagination, batch select of explicit IDs, bulk publish/move/archive, сохранение фильтра после редактирования.
- Documents: preview draft без публикации, version diff/restore, schedule, optimistic concurrency conflict, editor guard unsaved changes, автосохранение черновика с контролем ревизии.
- Infosystem: схемы типов (text/number/decimal/bool/date/list/media/reference), группировка полей, валидация и default, наборы-пресеты News / Services / FAQ / Projects / Jobs; не отдельный storage engine на пресет.
- Menu: визуальный редактор, выбор опубликованных ресурсов текущего сайта, custom URL allowlist, сортировка, активный пункт, breadcrumbs/404. Существующий runtime сохраняется.

Приёмка G1: нет raw JSON в повседневных карточках; все операции проверены browser + site scope + CSRF + concurrency. Редактор способен собрать типовой корпоративный сайт без ручного SQL.

### G2 — media и композиция страниц [P0]

Зависимости: G1.

- Media: безопасная загрузка, MIME sniffing и декодирование, лимиты по пикселям/размеру, приватность, hash/dedup, папки, ALT, crop/focal point, варианты WebP/AVIF при доступных расширениях и fallback, usage references, garbage collection после retention.
- Pages: набор из 8-12 типизированных секций (hero, rich text, image/gallery, cards, CTA, FAQ, pricing table, form action, collection, testimonials, contacts/map, custom safe snippet). Порядок/видимость/desktop-mobile параметры на форме, не в JSON поле.
- Каждый блок владеет schema/renderer/assets, имеет статический HTML fallback, server-side validation, версионирование конфигурации и frontend cache tags. Пользовательский контент не выполняет PHP/JS.
- Layouts: наследование, theme tokens, registry CSS/JS asset bundles, content security policy. Никакого копирования шаблонов каждого модуля в Core.

Приёмка G2: редактор собирает 5 типовых страниц из блоков без создания PHP шаблона; операции media работают на shared-hosting без Redis и на low-memory PHP, битые изображения/HTML не попадают в паблик.

### G3 — SEO + навигация + поиск [P0 для Content]

Зависимости: G1 + G2.

- SEO: meta title/description, canonical, robots policy, sitemap XML, redirect history, OG, JSON-LD typed schema, hreflang source hook; режимы indexing staging/prod.
- URL: перенаправления 301/308, no redirect loops, нормализация slash/case policies, изменение slug обновляет карту без произвольной потери ссылок.
- Search: опубликованные Documents + Infosystem; language-aware поиск, paging, permission/site constraints, reindex batch. PostgreSQL full-text primary, legacy LIKE/indexed compatible bounded fallback, external search optional.
- SEO-фильтр каталога в G7: whitelist индексируемых facet landing pages, все остальные combinations noindex/canonical policy. Не копировать бесконечные HostCMS filter URL.

Приёмка G3: canonical/redirect/sitemap consistency, sitemap не содержит draft/inactive/foreign site, проверены XSS через meta/schema и производительность query search.

### G4 — безопасные документные действия и уведомления [P0]

Зависимости: G2.

- Ввести минимальный типизированный Actions API/registry (см. раздел 5) только с использованием реального contact/booking handler.
- Notifications: SMTP adapter, шаблоны, доставляемость/ошибки и безопасные credentials, очередь либо bounded retry через cron/ручной runner, отсутствие длительного письма в HTTP request при наличии queue.
- Contact/booking pipeline: validation, CSRF & SameSite/Origin, throttling, idempotency, PII minimization, retention, consent and audit; локальный sandbox transport.
- Провести интеграционную проверку на сайте «Механики» в ru.rewired.ru, не изменяя исходный mechaniki.ru; формы включить лишь после одобрения контракта и тестов.

Приёмка G4: реальные request/retry/error paths, mock SMTP vs opted-in real SMTP; неактивный action возвращает контролируемую ошибку и не теряет текст страницы.

### G5 — языковые и региональные варианты [P0 продукта]

Зависимости: G1 + G3.

- Site = граница владения. Locale и region = варианты внутри site; язык admin UI независим от языка публикации.
- Localization: translation identity, status per locale, normalized locale URLs, hreflang, default/fallback policy, alt/media variants и словари UI ru/en на первом запуске.
- Regions: hierarchical region keys, контакты/доставка/цены/SEO overrides, host/path selector c явным приоритетом и white-listed domains, ручной выбор посетителя; geoIP только опциональный enhancement.
- Cache key/invalidation: site+locale+region+content version, строго проверять отсутствие утечек. Никаких fallback в другой site, скрытого дубля URL или неопубликованного текста в публичном выводе.
- API context явный и сериализуемый, но не добавлять обязательный JOIN во все исходные запросы.

Приёмка G5: 2 языка × 2 региона × 2 сайта; URL/canonical/hreflang/robots, переключатели, геозависимые контакты и отсутствие cross-site leak.

### G6 — каталог товаров, SKU и фасетный поиск [P0 Commerce]

Зависимости: G1 + G2 + G3 + G5 contracts для будущих локализаций.

- Один Catalog module с product, category, variants/SKU, option sets, attributes schema, media, SKU uniqueness, published visibility, availability, currency and price list, brand/vendor optional.
- Цена — decimal/integer minor units + currency, не float; цены нельзя доверять клиенту. Валидировать inheritance base product -> variants, без двойного опубликованного SKU.
- Фасеты: фильтрация по цене/числам/enum, подсчёты, ranges, incremental index, обновление после редактирования, limit cardinality; PostgreSQL index/explain и legacy backend. SEO landing whitelist из G3.
- Массовые операции, CSV import/export с preview/mapping и resume, dry-run конфликтных идентификаторов и транзакционными порциями.

Приёмка G6: каталог не менее 10k товаров / 30k SKU в нагрузочном fixture; price/visibility одинаковы в списке, карточке и API; индекс фасетов валиден после импорта/удаления.

### G7 — Commerce: cart, checkout, orders [P0 Commerce]

Зависимости: G6 + G4 notifications + G5 context.

- Cart and checkout: гостевой сценарий, secure cart session, draft snapshot, totals recalculated on server, promo policy, delivery method, address validation и consent.
- Orders: атомарная фиксация заказа; idempotent checkout with client request key, order line snapshots, state machine with audit, reservation/release stock policy, no negative available stock unless explicitly enabled.
- Payments: typed provider interface, signature verification of webhooks, replay protection, out-of-order events, confirmed vs pending vs failed/refunded, separate sandbox/prod credentials. Подключить хотя бы одного провайдера в sandbox с end-to-end contract; никакого собственного хранения данных банковских карт.
- Delivery: typed provider/flat-rate/pickup adapters, shipping price computed server-side, provider failures без потери заказа.
- Admin: списки заказов, фильтры, статусы, timeline, permission checks, ручная отмена и возмещение через provider-controlled workflow. Скидки сначала простой deterministic policy, не копировать весь бонусный движок HostCMS.

Приёмка G7: 0 потерянных/двойных заказов в retry+webhook tests; high-concurrency stock/order checks; расчёты сохраняют точность, нельзя клиентом подменить сумму; disabled commerce не ломает content site.

### G8 — редакции, лицензирование и магазин пакетов [P0 коммерциализации]

Зависимости: стабильный G0 module lifecycle и G6/G7 для полной редакции.

- Одна кодовая база. Редакции — преднастроенные bundles, а не switch/case в Core. Базовый сайт, контент, магазин различаются entitlements в лицензии.
- Каждый модуль продаётся отдельно; стоимость standalone модуля может быть выше доли в комплектной редакции — модель ценообразования, а не обход архитектуры. Цены и конкретные названия редакций согласовать отдельно.
- Signed manifests/packages + compatible Core/API/driver matrix, hash/signature verification before unpacking, anti path traversal/zip bombs, correct permissions/migrations, dry-run dependency graph.
- Licensing: signed offline-verifiable entitlements, site/install ID policies, expiry/grace mode; недоступный license server не должен мгновенно остановить уже опубликованный сайт. Не storing payment secrets in packages.
- Marketplace UI: доступные/установленные/купленные/требующие обновления, версия, permissions, dependencies, DB compatibility, conflict warning, changelog and rollback expectations.
- Экспорт демо-пакетов без embedded secrets и пользовательских данных. Третьесторонний пакет должен запускаться с минимальным доверием; marketplace не должен давать доступ к произвольному unreviewed install hook.

Приёмка G8: покупка/активация и обновление отдельного модуля, bundle upgrade, expired grace case, disabled module removal, attempted tampered signature rejection, no module code persistence when disabled.

### G9 — эксплуатация, безопасные обновления, backup/restore [P0]

Зависимости: все домены, поставляемые в 1.0.

- Модуль Backup: полный приватный набор БД + storage/media + config metadata без секретов в публичном архиве, проверяемые manifest/checksums, encryption option, rotation/retention, offsite copy adapter, test restore.
- Update: проверка предстоящих SQL и несовместимостей, snapshot/preflight, fail-closed maintenance mode, blue/green если инфраструктура позволяет, CLI/web recovery, защита от неполной замены файлов и сохранение пользовательских template overrides.
- Observability: структурированные логи + request/correlation ID, security audit, scheduled task status, queue retries, health panel без раскрытия секретов.
- Shared-host: ZIP без composer/node на сервере, preflight предупреждения, безопасная webroot сборка, ограниченная память, запрет публичной раздачи app/. Nginx без правил приватности не поддерживать фиктивно.
- Документировать эксплуатацию: установка, SSL, upgrade, rollback/recovery, backup restore, CRON optional, SMTP, платежи и права доступа.

Приёмка G9: restore drill на чистой установке, авария во время обновления, checksum migration tamper, недоступная почта/платёжный provider, нехватка места, отказ БД; отсутствие необратимой потери подтверждённого заказа.

### G10 — hardening, release candidate и выпуск 1.0 [BLOCKING]

Зависимости: G0-G9 закрыты по release scope.

- Три референсных проекта: corporate/content (настоящая редакторская работа), Mechaniki (CSS/JS/document actions, запись только после утверждения), demo shop с sandbox платежами; все 3 используют одну кодовую базу без ручных патчей Core.
- Security: OWASP ASVS L2-oriented checklist для доступных функций; SAST, dependency audit, input fuzzing в границах времени, session/CSRF/XSS/SQLi/uploads/SSRF/zip/path traversal/authorization tests, secrets scan, MFA для привилегированных ролей либо чёткое release exception.
- Accessibility: WCAG 2.2 AA targeted admin workflows, keyboard/focus, field errors, mobile 390/1024/1440. Lighthouse/axe в CI с зафиксированными тестовыми условиями.
- Performance: задать и документировать нагрузочный профиль (content 1k nodes/10k entries; catalog 10k products/30k SKUs; 20-50 параллельных посетителей на VPS), p50/p95, memory peak, SQL count, cache warm/cold. Целевые пороги утверждать после G0 baseline: для референсной публичной страницы provisional p95 <=400 ms warm на VPS при описанной нагрузке и мобильный Lighthouse >=90 на эталонной теме. Это критерии проверки, не обещанные измерения. Для shared-host отдельно фиксировать low-memory сценарии и отсутствие mandatory worker.
- Data correctness: migration upgrade с предыдущего release; downgrade SQL не обещается, обязателен recovery procedure; reproducible ZIP SHA/signatures/SBOM, dependency license review, release notes, example config, API docs.
- RC soak: не менее 7 календарных дней наблюдения на staging со сценариями редактирования/заказов; отсутствие P0/P1, отсутствие некорректных webhook retries и cross-site leak, успешный restore drill. После изменения P0/схемы RC окно начинается заново.
- Product: базовая документация администратора и разработчика, быстрый старт <=15 минут на поддерживаемом shared-host с заранее созданной БД, страница compatibility matrix, support runbook, license terms/privacy/docs по применимым требованиям.
- Выпустить tag v1.0.0 только после release checklist, подписи artifact, CI + security acceptance и явно утверждённого go/no-go. Выкладка на реальные чужие сайты требует отдельного одобрения владельца.

## 7. Зависимости и рекомендуемая очередь PR

| Очередь | PR/эпик | Требует | Критерий выхода |
| --- | --- | --- | --- |
| 01 | feature matrix + compatibility/CI baseline | main | документированный G0 |
| 02 | admin list + Structure/Docs/Infosystem UX contracts | 01 | G1 с E2E |
| 03 | menu editor | 02 | source-agnostic menu E2E |
| 04 | media lifecycle | 01-02 | безопасный upload + variants |
| 05 | typed content blocks | 02,04 | composition E2E |
| 06 | SEO/router metadata + redirects | 02 | SEO contract tests |
| 07 | published content search | 02,06 | search/index integration |
| 08 | document Actions + notifications | 05 | contact/booking security E2E |
| 09 | localization and region context | 02,06 | 2x2x2 isolation tests |
| 10 | catalog + facets + CSV | 04,06,09 contracts | product/SKU acceptance |
| 11 | cart + checkout + orders | 08,10 | transactional order tests |
| 12 | payments/shipping adapters | 11 | verified sandbox/webhooks |
| 13 | entitlement and signed packages | module lifecycle stable | sales and tampering scenarios |
| 14 | backup and operational hardening | all production data owners | restore drill |
| 15 | release candidate and v1.0.0 | all previous gates | production release checklist |

Внутри каждой строки обычно несколько самостоятельных PR; таблица НЕ означает один огромный PR на все функции.

## 8. Обязательный контракт исполнения для агента

Перед началом задачи:
1. Сверить актуальный main, открытые PR/issues, результаты CI и API/SQL схемы; не верить устаревшей памяти и не создавать повторную реализацию.
2. Сформулировать UX scenario, trust boundary, domain ownership, API/DTO, cache tags и migration/rollback; назвать затрагиваемые пути и перечислить минимум 2 варианта решения там, где есть архитектурный выбор. Выбрать простейший работающий вариант и объяснить trade-off.
3. Создать отдельный issue с task ID, checklist и acceptance; выбрать короткую feature/fix branch от main. Не вести параллельные несовместимые изменения в одном файле.
4. Для нового модуля сначала зафиксировать module.json/архитектуру и дерево файлов/классов, затем implement domain->repository->application service->API/admin->frontend.
5. Написать migrations additively. Не менять SHA старой migration. Обязательно fresh install + upgrade fixture + site scoping + idempotency + legacy driver path.
6. Тесты: unit/domain, HTTP/API, browser/visual (где есть UI), security edge cases, module disable/remove. Произвести performance delta; при регрессии явно остановиться.
7. Выпустить PR до merge, указать screenshots, DB impact, API changes, test commands, risk notes, readme; дождаться всех обязательных GitHub checks. Зелёный CI — необходимое, но не достаточное условие.
8. Мержить обычные изолированные PR только после green и отсутствия unresolved reviews/conflicts. Destructive DB changes, payment/checkout, auth, signing/licensing, breaking API и production deployment — только с явным approval. Не bypass branch protection.
9. После merge закрыть issue, обновить feature matrix и release checklist, при необходимости создать следующую зависимую задачу. Не работать бесконечным PR.

Definition of Done для **каждого** вертикального модуля:
- инсталляция/обновление/отключение/удаление не ломают Core;
- админские права и site scope работают на HTTP, не только через unit;
- есть public/API paths и их негативные тесты;
- данные не исчезают от disabled и не публикуются без статуса published;
- есть пользовательский сценарий ошибок, doc, migration + восстановление;
- интерфейс соответствует единой admin design system и доступен с клавиатуры;
- функциональность не требует Redis/worker и не запускает N+1 запросы.

## 9. Release gates 1.0 — без компромиссов

- G-REL-01: полные clean install и upgrade (PostgreSQL + заявленные MySQL/MariaDB режимы), обе webroot сборки.
- G-REL-02: все bundled modules version-compatible, signed distribution; выключенные модули не вмешиваются в runtime.
- G-REL-03: documented backup/restore rehearsal с реальным восстановлением данных/медиа и проверкой checksum.
- G-REL-04: P0/P1 bugs = 0, обязательные тесты зелёные, нет known exploitable critical/high security vulnerabilities без принятых компенсирующих мер.
- G-REL-05: no cross-site / cross-locale / cross-region data leak, negative permissions/CSRF tests.
- G-REL-06: end-to-end безопасный заказ: суммы, статусы, webhook replay и idempotency; неактивные провайдеры не принимают оплату.
- G-REL-07: стабильный visual/admin UX и пользовательские walkthrough для всех 3 профилей.
- G-REL-08: измеренные p95+SQL/memory, без критических регрессий относительно baseline.
- G-REL-09: reproducible artifact, SBOM/manifest/checksum, release notes, version policy, operating docs и migration/recovery runbook.
- G-REL-10: RC soak и явное go/no-go владельца; Git merge != деплой, staging != prod.

## 10. Варианты релизной стратегии (решение владельца)

Вариант A — сначала production 1.0 только для корпоративных/контентных сайтов; магазин в 1.1. Плюсы: заметно меньший time-to-market, рано получим production telemetry. Минус: первая версия не покрывает одну из трёх основных целей продукта.

Вариант B — **рекомендован**: внутренние релизы по профилям (Content RC, Commerce RC), но публичный v1.0.0 только после полного Commerce + Platform checklist. Плюсы: ранняя проверка движка на реальных сайтах без обещания готовности магазина; одна честная коммерческая версия 1.0. Минус: более длинный путь до публичной версии.

Вариант C — копировать весь HostCMS feature catalog до 1.0. Не рекомендован: постоянный scope creep, лишние домены, высокая стоимость поддержки.

Принять B по умолчанию. Календарные даты и человеко-часы согласовать после G0: количество разработчиков, поддерживаемых хостингов, коммерческих провайдеров и UI scope пока не определено.

## 11. Шаблон отчёта агента после каждого этапа

    Goal/Gate:
    Branch/PR:
    Previous main SHA -> new main SHA:
    Changed modules/files:
    API and DB migrations (fresh + upgrade):
    Security, permissions, site/locale/region boundary:
    CI, browser, legacy DB, performance delta:
    Remaining blockers with severity:
    Verified live deployment? (yes/no, exact URL):
    Release gate affected:
    Next issue in dependency order:

Запрещено маркировать «готово», если получен только commit, но отсутствуют подтверждающие тесты; нельзя маркировать «на production», если проверена только GitHub ветка.

## 12. Первая задача агенту, которую можно выполнять сразу

**G0 / #01 — feature matrix + release gate inventory.**

1. Получить полный git tree main и последние CI + MVP acceptance; разложить текущие готовые capabilities по трём профилям, дать каждому статус implemented/partial/missing и прямую ссылку на класс + тест.
2. Проверить collisions с текущими ветками (включая перенос Mechaniki / ru.rewired.ru), зафиксировать blocking bugs отдельно.
3. Создать docs/roadmap/feature-matrix.md и docs/roadmap/release-gates.md; добавить executable check/test для наиболее рискованного пробела G0.
4. Завести эпики по очереди 02-15, установить явные зависимости; не начинать Commerce, пока нет G0 и контрактов каталога.
5. Выдать отчёт по шаблону раздела 11, затем запросить review/merge. При отсутствии данных не угадывать.

## 13. Защита от scope creep

- Блоки страниц не должны становиться полнофункциональным page builder с произвольным React/JS или собственным DSL; достаточно типизированных конфигураций и нативных серверных view.
- CRM, склад, бонусы, маркетплейс товаров, рекламная система, helpdesk, AI-редактор, headless frontend builder и сложная товарная аналитика — backlog после 1.0.
- Не создавать новую общую абстракцию, пока её не используют два реальных сценария и контракт невозможно выразить существующими Extensions API.
- Поиск/индексация и jobs адаптируются к shared hosting; отдельный сервис ускорения не становится обязательным.
- Любая фича, для которой невозможно сформулировать безопасный restore/upgrade, не считается готовой для 1.0.
