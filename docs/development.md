# Разработка

Frontend: Angular 22, standalone-компоненты, signals и FormsModule.
Backend: PHP 8.4, PDO SQLite, JSON API и автозагрузка PSR-4 через Composer 2.
Схема создаётся `backend/src/Database.php` из `backend/schema.sql` при первом
обращении; данные хранятся в `var/project-desk.sqlite`.

## Локально без Docker

Нужны PHP 8.4 с `pdo_sqlite`, Composer 2, Node 22.23.3+ и npm. В двух терминалах:

```bash
# Из корня репозитория
composer --working-dir=backend install
php -S 127.0.0.1:8098 backend/router.php
```

```bash
cd frontend
npm ci
npm start
```

Frontend доступен на http://localhost:4200, `/api` проксируется на порт 8098.

```bash
php backend/tests/smoke.php
cd frontend
npm run build
```

Тест API поднимает отдельный сервер и временную SQLite, затем удаляет её.
`DB_PATH` позволяет указать отдельную БД; `SEED_NOW=2026-11-11T08:00:00Z`
задаёт исходную дату только при создании новой БД. Текущее серверное время
возвращается в `/api/context`; это не остановленные часы приложения.

При создании новой демо-базы комментарии к заблокированным задачам получают
отметку внимания. Отметки в уже существующих базах сохраняются при перезапуске.

## Docker

`docker compose up --build -d --wait` собирает frontend и отдаёт его тем же PHP
сервером, что обслуживает API. Это сервер для локального тестового задания;
production web-сервер и настоящая аутентификация в комплект не входят.

`make up`, `make test`, `make down` — сокращения команд Compose.
`docker compose build` пересобирает код; существующая БД остаётся в volume.

## Проверки PHP в Docker

```bash
make test-php      # PHPUnit
make analyze-php   # PHPStan, уровень 9
make check-php     # обе проверки
```

Сервис `php-check` собирается с dev-зависимостями из `backend/composer.lock`.
PHPUnit проверяет реальную SQLite на отдельном временном файле без доступа к
рабочему volume. PHPStan анализирует исходники, маршрутизатор и тесты.
В baseline остались 6 замечаний к исходному HTTP smoke-тесту. При устранении
замечания удалите соответствующую запись, сохраняя уровень 9.

`make test` запускает HTTP smoke-тест в сервисе приложения с временной БД.

## Структура

- `frontend/src/app.*` — текущие экраны проектов и карточка задачи.
- `frontend/src/project-desk/` — клиент API и сгенерированные типы.
- `frontend/src/task/`, `attention/` — карточка и обзор с предметными типами.
- `backend/router.php` — API и выдача собранного frontend.
- `backend/src/Http/` — таблица маршрутов в `Router`, диспетчеризация в `FrontController` и контроллеры сценариев. `ControllerFactory` создаёт контроллер после выбора маршрута и переиспользует соединение SQLite в пределах запроса.
- `backend/src/Application/` — сценарии, доступ, координация доменных операций и DTO полей задачи.
- `backend/src/Domain/` — правила проекта, задачи и комментария.
- `backend/src/Infrastructure/Sqlite/` — репозитории агрегатов и запросы чтения.
- `backend/src/Database.php`, `schema.sql`, `migrations/` — создание БД, демо-данные и обновление существующих файлов SQLite.
- `backend/tests/smoke.php` — проверка существующих сценариев API.

Ошибки изменений API возвращаются с HTTP-статусом и полем `error`.
Конфликт версии при редактировании — 409: откройте задачу заново.
Собранный frontend, node_modules, БД и секреты игнорируются Git.
