# HostCMS → Ultima Vox CMS: редакторские сценарии и UX

Ревизия: 2026-10-20. Проверенный source baseline: `e0476affb4c3ae959823794b5aa66a1537272c13`, dev v0.1.169. Родители: [#102](https://github.com/ultima-vox/cms/issues/102), [#109](https://github.com/ultima-vox/cms/issues/109). Дополнение к [feature matrix](feature-matrix.md), а не замена production-ТЗ.

Статусы относятся к цельному сценарию: `implemented` — указан работающий код и regression test; `partial` — работает только перечисленная часть; `missing` — в проверенных владельцах/шаблонах/JS нет реализации, приёмочного теста ещё нет. Проектное решение, открытый PR или установленная библиотека сами по себе не дают `implemented`. Это source audit; нового human walkthrough и сравнительного benchmark HostCMS здесь нет.

## Официальные источники

- H1: [Интерфейс HostCMS](https://www.hostcms.ru/hostcms/interface/): закладки, списки, персонализация, фильтры, действия, защита форм.
- H2: [Информационные элементы](https://www.hostcms.ru/documentation/modules/informationsystem/item/): группы/элементы и их поиск.
- H3: [Поля форм администрирования](https://www.hostcms.ru/documentation/modules/admin-form/field/): представление, сортировка, фильтрация и редактирование полей.
- H4: [Добавление элемента](https://www.hostcms.ru/documentation/modules/informationsystem/item/add/): вкладки и параметры публикации.
- H5: [Верхняя панель клиентского раздела](https://www.hostcms.ru/documentation/step-by-step/top-panel/): переходы от публичной страницы к редактированию.
- H6: [Перенос выбранных элементов](https://www.hostcms.ru/documentation/modules/informationsystem/frontend/move-to-another-is/): пример редакторского действия; не доказательство, что такая операция встроена без расширения.
- H7: [Импорт CSV](https://www.hostcms.ru/documentation/modules/informationsystem/import-is/): редакторский импорт данных.

Из H1 заимствуются пользовательские процессы; из H3 не переносятся конфигурационный движок форм, callbacks и выполнение кода. XML/XSLT и архитектура HostCMS не входят в runtime Ultima Vox.

## Матрица приёмки

| ID / сценарий-референс | Наш статус и source evidence | Проверка / пробел / задача |
| --- | --- | --- |
| U01 / разделы и контекст, H1/H4 | `partial`: [header](../../templates/admin/_header.php), [AdminApi](../../core/Extension/Api/AdminApi.php) выводят плоскую регистрацию; [shell](../../core/View/AdminShellContext.php) проверяет права | [browser](../../tests/browser_mvp.cjs) проверяет доступность текущего меню, но не разделы; **P0 #156**, [ADR](../architecture/admin-ux-navigation.md) |
| U02 / выбранный сайт и доступ, H1 | `implemented` в bounded-сценариях: [site context](../../core/View/AdminShellContext.php), [selector](../../core/Site/AdminSiteSelector.php) | [HTTP](../../tests/mvp_http_smoke.py), [legacy](../../tests/legacy_http_smoke.py), [removal](../../tests/module_removal_smoke.php); не считать это приёмкой будущих locale/region |
| U03 / личные закладки, H1 | `missing`: [dashboard](../../templates/admin/index.php) содержит общий быстрый доступ; [admin.js](../../public/assets/admin.js) хранит только collapse preference | Личные разрешённые закладки user/site: #157; entity favorites — только с resolver владельца |
| U04 / сохранённые представления, H1 | `missing`: [Documents query](../../modules/documents/src/Admin/DocumentListQuery.php), [IS query](../../modules/infosystem/src/Admin/InfosystemListQuery.php) принимают URL-состояние, сохранения личного вида нет | #157; валидированные фильтры и сброс; чужой/удалённый folder не восстанавливается |
| U05 / настройка колонок, H1/H3 | `missing`: [Documents list](../../modules/documents/templates/admin/documents/index.php), [IS list](../../modules/infosystem/templates/admin/infosystems/index.php) имеют фиксированные колонки | #157, сначала Documents; IS/layout отдельными PR; обязательные идентификатор/действия нельзя потерять |
| U06 / фильтр, сортировка, страницы, H1/H3 | `partial` для всей CMS: Documents и IS реализованы, [layouts](../../core/Controller/LayoutController.php) ещё загружает all()+PHP filter | [Documents SQL](../../tests/document_list_smoke.php), [IS SQL](../../tests/infosystem_list_smoke.php)/[HTTP](../../tests/infosystem_list_http.py)/browser; **#123 закрыта**, остаётся #124 |
| U07 / возврат в прежний список | `partial`: Documents сохраняет контекст вкладки, глобального last-list pointer нет; единая приёмка IS/layout отсутствует | [HTTP return](../../tests/document_context_http.py), browser; при расширении #157/124 сохранять owner DTO и site marker |
| U08 / путь и дерево, H1/H2 | `partial`: [IS browser](../../modules/infosystem/templates/admin/infosystems/manage.php) имеет breadcrumbs; общего owner-aware контекста нет | #128 для Structure, #156 для shell; большие деревья требуют DOM-измерения |
| U09 / поиск групп вместе с элементами, H2 | `partial`: [item search](../../modules/infosystem/src/Repository/InfosystemItemSearchRepository.php) ищет элементы; это не единая выдача групп/элементов | G1/Infosystem follow-up в #109; одинаковая site/publication/rights семантика |
| U10 / действия строки, H1/H3 | `partial`: ссылки редактирования/удаления существуют; единая owner-specific панель не принята | #159; клавиатура и обычные кнопки обязательны, right-click только дополнительный способ |
| U11 / выбранные записи, H1/H6 | `missing` для полного редакторского batch workflow: [IS management](../../modules/infosystem/src/Repository/InfosystemManagementRepository.php) предоставляет одиночные операции | #127 Documents, затем #159 IS. Только bounded explicit IDs, атомарность и проверка всех объектов/назначения |
| U12 / закреплённые действия, H1 | `missing` как общий сценарий: [.admin-actions](../../public/assets/admin.css) — обычный flex; [Document form](../../modules/documents/templates/admin/documents/form.php) ставит действия после контента | **P0 #158**; проверять длинную форму, focus, клавиатуру, safe area и 200% zoom |
| U13 / предупреждение об уходе, H1 | `missing`: формы и общий JS не реализуют dirty guard | Отдельный UI-PR после/вокруг #125; подтверждение при уходе/смене сайта, без случайной публикации |
| U14 / восстановление черновика, H1 | `missing`: сохранённая версия Documents не равна автосохранённой незавершённой форме | После #125 отдельный revision-aware autosave/recovery PR; два пользователя/сайта/вкладки, stale draft, cache/PII |
| U15 / история и защита одновременного редактирования | `partial`: [Document versions](../../modules/documents/src/Repository/DocumentManagementRepository.php), [history UI](../../modules/documents/templates/admin/documents/form.php) есть; author/conflict/diff/restore ещё не приняты | [domain tests](../../tests/documents_domain_smoke.php); #125 author/conflict первым; diff/restore отдельным follow-up |
| U16 / реальный preview публикации | `partial`: у IS есть sandbox iframe [editor](../../public/assets/infosystem-editor.js); защищённого рендеринга неопубликованной страницы это не доказывает | #126 после #125, полный PageRuntime/layout, срок действия, noindex/private cache, опубликованная версия неизменна |
| U17 / удобный визуальный редактор, H4 | `partial`: Documents — HTML textarea, IS — существующий редактор; PR154 с TipTap/CodeMirror ещё вне baseline | Продолжить PR154 и защиту импорта PR147; не создавать ещё один конкурирующий редактор. Library mount не равен готовому authoring workflow |
| U18 / вкладки и публикация, H4 | `partial`: [IS form](../../modules/infosystem/templates/admin/infosystems/item_form.php) имеет вкладки/status/publish_at; дата окончания не подтверждена | [validation HTTP](../../tests/infosystem_property_http.py), browser; expiry и workflow согласовать с #125 и SEO #113 |
| U19 / понятные поля, свойства и пресеты, H3/H4 | `partial`: [FieldSchema](../../modules/infosystem/src/FieldSchema.php) поддерживает ограниченный набор, включая money; media/reference/presets не готовы | [typed validation](../../tests/infosystem_property_validation_smoke.php), [money](../../tests/infosystem_money_smoke.php); дальнейший Infosystem 2.0 в #109, Media #111 |
| U20 / переход «страница → содержимое» | `implemented` в проверенных Documents/IS сценариях: [generic link contract](../../core/Page/PageEditorLinkProviderInterface.php), владельцы ссылок | [handoff SQL](../../tests/page_editor_handoff_smoke.php), [HTTP](../../tests/page_editor_handoff_http.py), browser и removal; #149 закрыта |
| U21 / редактировать из публичной страницы, H5 | `missing`: U20 работает внутри админки; приватной frontend toolbar нет | После #126/owner resolver; G2 composition. Не кешировать персональную toolbar/CSRF в публичном HTML, не переносить XML/debug controls |
| U22 / визуальное публичное меню | `missing` для редактора: [MenuModule](../../modules/menu/src/MenuModule.php) предоставляет runtime/facade | #110, существующие [menu tests](../../tests/menu_frontend_smoke.php) не являются editor E2E. Это отдельный домен от U01 |
| U23 / повторное использование медиа | `missing` для редактора: импортированные Mechaniki assets не равны медиабиблиотеке | G2/#111, затем блоки #112; ALT/usage/delete/site scope по ТЗ |
| U24 / импорт/экспорт, H7 | `missing` для общего редакторского IS CSV workflow: проектные CLI imports не заменяют UI/import validation | Owner follow-up #109 после typed schema; отдельные preview/validation/batches, не переносить CSV-поля в Core |

Для `missing` выполнен source search по `core`, `modules`, `templates`, `public/assets`, `tests`: bookmarks/favorites, saved filters, column preferences, beforeunload/autosave/recovery. В baseline нет соответствующей законченной реализации/приёмки. Отсутствие найденной строки не является общим security audit. Recycle/restore удалённых сущностей, сложное inline editing и расширенный CSV остаются отдельными design/backlog-пунктами #109; не объявлять их встроенными на основании внешнего примера или одного названия.

## Исправленный порядок разработки

1. **#156/P0**: metadata/совместимость → новый общий shell. Не расширять плоский sidebar новыми модулями. Сначала отдать проверяемую навигацию.
2. **#158/P0**: доступные действия форм; сохранить текущие save/publish semantics. Интеграция выбранного готового редактора идёт в существующем PR154.
3. **#124**: bounded layout list; **#125**: автор/expected revision; **#128**: Structure, keyboard/tree/context. Это независимые вертикали, а не повод создать универсальный UI framework.
4. **#126** после #125; dirty guard, autosave/recovery и diff/restore — небольшими следующими PR, не смешивать их с конфликтами сохранения.
5. **#157**: личные shortcuts и сохранённые виды, сначала Documents, затем владельцы IS/layout. **#127**, затем **#159**: selected-ID операции.
6. **#110**: редактор публичного меню с опубликованными same-site selectors. Сохраняется зависимость от #109 по действующему плану. #109 — подэтап редакторских контрактов (task 02), #110 — следующий подэтап того же G1 (task 03); общий чекбокс G1 в #102 закрывается только после обоих и остальных обязательных сценариев. Так не создаётся цикл «#109 ждёт меню, меню ждёт #109».
7. G2–G10 продолжаются по исходным зависимостям: media, blocks, SEO/search, actions, locale/region, commerce и эксплуатация. Не создавать пустые пункты меню для этих функций заранее.

## Сценарии, обязательные для нового UI

- Создать страницу, открыть её owner editor, сохранить черновик, вернуться к странице и проверить статус. Отдельно — preview без публикации.
- Найти материал на второй странице списка, изменить его, вернуться в ту же папку/поиск/сортировку. Независимая вкладка и другой сайт не меняют этот контекст.
- Сохранить длинную форму на мобильном с ошибкой 422; фокус и действия остаются доступны. Восстановить незавершённую форму только после реализации U14.
- Выбрать явно показанные ID и переместить их, убедиться, что остальные и другой сайт не изменились. Этот сценарий пока `missing`.
- Editor с ограниченными правами, superadmin, два сайта, выключенный/удалённый модуль, keyboard-only, 390/1024/1440, zoom 200%, JS/storage unavailable.

Расширить существующий [observation harness](../../tests/browser_editor_baseline.cjs) для **нового измерения**. Исторические G0 click counts не переписывать. Число действий, ошибки, потеря состояния, DOM/overflow и артефакт с SHA фиксируются отдельно. Малое число кликов не заменяет ясность и human walkthrough G10. Production 1.0 остаётся NO-GO до всех release gates.
