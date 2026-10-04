.PHONY: up down build restart logs shell test test-pg migrate fresh seed pint

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache app

restart:
	docker compose restart app

logs:
	docker compose logs -f app

shell:
	docker compose exec app sh

test:
	docker compose exec app php artisan test

test-pg:
	docker compose exec app vendor/bin/pest --configuration=phpunit.pgsql.xml

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

seed:
	docker compose exec app php artisan db:seed

pint:
	docker compose exec app ./vendor/bin/pint

register-test:
	curl -s -X POST http://localhost:8088/api/auth/register \
		-H "Content-Type: application/json" \
		-H "Accept: application/json" \
		-d '{"name":"Local Test","email":"local@test.com","password":"password","password_confirmation":"password"}' | python3 -m json.tool
