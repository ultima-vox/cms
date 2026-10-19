# G0: воспроизводимый Content baseline

Задача [#105](https://github.com/ultima-vox/cms/issues/105), epic [#102](https://github.com/ultima-vox/cms/issues/102). Исходный main: `16c660e8464ce046073b4ae23648e453409c48f2`. Этот стенд собирает данные для обсуждения порогов; не объявляет G0, весь продукт или FASTPANEL production-ready.

## Запуск

[Workflow](../../.github/workflows/g0-baseline.yml) выполняется на PR или вручную. Матрица: PHP8.4, PostgreSQL16 по умолчанию и отдельные legacy MySQL8.0/8.4, MariaDB10.11/11.4, GitHub Ubuntu runner, отдельный контейнер БД. Для legacy разрешение создания trigger включается только внутри одноразового CI-контейнера, как в существующих тестах; это не инструкция менять настройки хостинга.

Локальный запуск допускается только на **новой одноразовой** БД `cms_g0_perf` с тестовым пользователем, уже заданными `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, и установленными зависимостями PHP8.4. Для PostgreSQL:

```bash
export APP_ENV=testing G0_DISPOSABLE=1 DB_NAME=cms_g0_perf DB_DRIVER=pgsql
export APP_URL=http://g0-primary.test
php bin/console migrate
php bin/console modules:migrate --all
php bin/console extensions:sync
python3 tools/g0_content_baseline.py /tmp/g0-content-baseline
```

Не указывать существующую CMS database. Guard проверяет окружение, opt-in, точное имя БД и пустоту nodes/documents. Повторный запуск требует новой БД: миграции и тестовые данные не удаляются из неё автоматически. В CI контейнер уничтожается вместе с job. Временные cache/session/fixture-файлы удаляются в `finally`, session ID и пароль не попадают в артефакт.

## Набор данных Content-v1

[Fixture](../../tests/g0_content_fixture.php) использует существующую схему, а не новые миграции. Primary site: ровно 1000 узлов — корень, страница ИС, 10 разделов, 988 дочерних страниц; 999 документов с опубликованной версией; 10000 элементов одной ИС, 20 групп и три типизированных свойства. Каждый пятый элемент — draft, каждый седьмой — inactive, publish_at фиксирован в прошлом. HTML документов и записей имеет фиксированное содержимое и размер.

Secondary site: 2 узла, 1 документ, 20 элементов ИС и 20 групп. Те же публичные адреса и коды, собственный контент. Системный default site, созданный штатными миграциями, остаётся пустым и не включается в primary counts. Создаётся настоящий superadmin, вход через AuthService и выбор primary site. Пароль случайный, используется только при setup; не измеряется password_verify на каждом запросе.

`fixture_sha256` — хеш нормализованного описания Content-v1 и количества записей. Это идентификатор рецепта набора, **не** checksum дампа всей БД. Случайные пароли, session ID, sequences и timestamps не влияют на него. Изменение рецепта требует версии и descriptor update.

## Что измеряется

[Probe](../../tests/g0_dispatch_probe.php) выполняет настоящий `Application::run` в новом PHP-процессе для каждого образца:

- публичная Documents-страница в дереве из 1000 узлов;
- публичный список ИС с limit=25 из 10000 записей;
- административный список Documents с поиском, limit=25;
- административный список элементов ИС с limit=25;
- дерево Structure из 1000 узлов.

[Runner](../../tools/g0_content_baseline.py) собирает по **40 образцов** в каждом из двух состояний, concurrency=1. Итого 400 измеренных dispatch на СУБД; дополнительно 25 warmup и изоляционные запросы. p50/p95 рассчитываются методом nearest rank `ceil(p*n)`. Это описательная статистика конкретного CI-прогона, а не устойчивый SLA или сравнение скорости разных СУБД на одинаковом железе.

**Cold application cache:** перед каждым образцом удаляется только file-cache временной fixture. **Warm application cache:** cache сохраняется после пяти warmup-запросов на сценарий. Для публичных страниц дополнительно проверяется фактический page MISS/HIT через обёртку существующего CacheBackendInterface. Админка по-прежнему не кешируется как публичная страница. Buffer cache БД, OS cache и сгенерированные layout PHP-файлы не сбрасываются. Называть этот режим «полностью холодный сервер» нельзя.

Метрики:

| Поле | Точная область |
| --- | --- |
| dispatch_ms | Создание Application и run: bootstrap, routing, SQL, render, send в output buffer. Не включает соединение PDO, начальный require autoload и открытие session |
| process_wall_ms | Весь PHP subprocess: дополнительно startup, PDO connect, самопроверка instrumentation и session setup |
| sql_executions | Вызовы PDO query/exec и execute подготовленных statement во время dispatch; prepare без execute не считается. TX methods и внутренние statements БД не считаются |
| peak_allocated_bytes | Allocated PHP peak после reset перед dispatch, включая уже загруженный baseline процесса |
| peak_delta_bytes | Разность peak и allocated memory непосредственно перед dispatch, ограниченная снизу нулём; не RSS всего сервера |
| body_bytes/body_sha256 | Размер и хеш фактического ответа; response body не сохраняется |
| page_hits/page_misses/status | Контроль cache state и ожидаемого HTTP status, выставленного Response в CLI dispatch |

PDO-счётчик находится только в test probe; reflection задаёт существующий private Database connection в одноразовом процессе. Core, конфиг и production API не меняются. Перед **каждым** образцом проверяются query, exec и два execute одного statement — ожидаются четыре исполнения; затем счётчик обнуляется. Накладные расходы instrumentation входят в время dispatch.

Лимит памяти — 128M. OPcache CLI выключен. Точные PHP/DB версии, JIT setting, SAPI, SHA проверенного checkout и head PR пишутся в JSON. CPU, память и filesystem runner сохраняются отдельными файлами. Подробные raw samples позволяют проверить percentiles, ошибки и разброс; не выводить числа без ссылки на соответствующий run и SHA.

## Изоляция и корректность

До измерений прогоняются два одинаковых `/` по двум разным доменам с общим file-cache: второй ответ каждого домена обязан быть HIT и содержать только свой marker. Прямые административные обращения из primary context к документу и ИС secondary site обязаны вернуть 404. Каждый измеренный ответ проверяет статус/наличие primary marker и отсутствие secondary marker. Публичная ИС дополнительно не должна выводить заранее известные draft/inactive записи.

Это дополнительная проверка границ на большом наборе данных. Она не заменяет весь security suite: существующие CI/MVP тесты CSRF, stale forms, роли и HTTP-изоляция остаются обязательными.

## Ограничения и следующий шаг G0

Это **CLI application dispatch**, не сетевой HTTP benchmark. Значение dispatch_ms нельзя выдавать за browser TTFB, FPM latency, время загрузки страницы или измерение ru.rewired.ru. 128M — ограничение процесса теста, не доказательство совместимости всех shared-хостингов. Новые Commerce/localization ещё не существуют; их benchmark не имитируется.

Артефакты workflow: `g0-content-baseline-0` … `-4`, по одному `report.json` с summary и raw samples плюс metadata runner. Наличие артефакта само по себе не означает success: читать `status`, job conclusion и diagnostics. Ошибка корректности/изоляции/инструментации прерывает job; неуспешный отчёт также сохраняется. Медленный результат не скрывается произвольным порогом или повтором до зелёного.

Для завершения #105 ещё нужны review результатов и утверждение реалистичных бюджетов, согласование области CLI/HTTP и отдельные human editor walkthrough. Частичный browser UI baseline описан в PR130: три сценария на 390/1024/1440, реальные screenshots и automation actions. Подменять human usability количеством автоматизированных click нельзя. G1 остаётся заблокирован до приёмки G0 и устранения его критических дефектов.
