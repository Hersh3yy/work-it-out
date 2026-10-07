.PHONY: up down build restart logs shell test test-pg migrate fresh seed pint laya lab-api classify-eval

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

# Classifier lab: run Laya locally (needs `uv tool install "laya[serve]" --python 3.13` once;
# first start downloads ~2.3 GB of checkpoints). Loopback only; Docker reaches it via host.docker.internal.
laya:
	LAYA_HOST=127.0.0.1 LAYA_PORT=8765 LAYA_DEVICE=mps LAYA_PRELOAD=1 LAYA_MODELS=laya-typed-decisions laya-serve

# Classifier lab API on http://127.0.0.1:8099 without Docker or a database (file sessions and cache).
# The testbed page http://127.0.0.1:4321/classifier talks to this.
lab-api:
	cd public && SESSION_DRIVER=file CACHE_STORE=file php -S 127.0.0.1:8099 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

classify-eval:
	php artisan classify:try --file=tests/Evals/classify.txt --driver=rules --driver=laya
