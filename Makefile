.PHONY: up test test-php analyze-php check-php check-frontend check e2e down
up:
	docker compose up --build -d --wait
test:
	docker compose exec -T app php backend/tests/smoke.php
test-php:
	docker compose run --rm --build -T php-check composer test
analyze-php:
	docker compose run --rm --build -T php-check composer analyse
check-php:
	docker compose run --rm --build -T php-check composer test
	docker compose run --rm -T php-check composer analyse
check-frontend:
	docker compose run --rm --build -T frontend-check
check: check-php check-frontend
	docker compose run --rm --build --no-deps -T app php backend/tests/smoke.php
e2e:
	@set -eu; project=project-desk-e2e-$$$$; \
	trap 'docker compose -p "$$project" -f compose.e2e.yaml down --volumes' EXIT; \
	docker compose -p "$$project" -f compose.e2e.yaml up --build -d --wait app; \
	docker compose -p "$$project" -f compose.e2e.yaml run --build --rm -T e2e
down:
	docker compose down
