.PHONY: up down build restart logs shell test test-pg migrate fresh seed pint stan rector bot laya classify-eval

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

stan:
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G

rector:
	docker compose exec app vendor/bin/rector process

bot:
	docker compose exec app php artisan channel:telegram:poll

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

# Classifier lab: run Laya locally (needs `uv tool install "laya[serve]" --python 3.13` once;
# first start downloads ~2.3 GB of checkpoints). Loopback only; Docker reaches it via host.docker.internal.
laya:
	LAYA_HOST=127.0.0.1 LAYA_PORT=8765 LAYA_DEVICE=mps LAYA_PRELOAD=1 LAYA_MODELS=laya-typed-decisions laya-serve

classify-eval:
	php artisan classify:try --file=tests/Evals/classify.txt --driver=rules --driver=laya
