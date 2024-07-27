.PHONY: build up down restart shell composer artisan migrate fresh test setup
generate-swagger:
	docker-compose exec wb-app php artisan l5-swagger:generate

build:
	docker-compose build

up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose restart

shell:
	docker-compose exec wb-app bash

composer:
	docker-compose exec wb-app composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	docker-compose exec wb-app php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker-compose exec wb-app php artisan migrate

fresh:
	docker-compose exec wb-app php artisan migrate:fresh --seed

test:
	docker-compose exec wb-app php artisan test

setup:
	@docker-compose exec wb-app sh -c '[ -f /var/www/wb-back/.env ] && echo ".env file already exists. Skipping .env file creation." || (cp /var/www/wb-back/.env.example /var/www/wb-back/.env && echo ".env file created from .env.example.")'
	@docker-compose exec wb-app composer install -d /var/www/wb-back
	@docker-compose exec wb-app php /var/www/wb-back/artisan key:generate
	@docker-compose exec wb-app php /var/www/wb-back/artisan migrate
	@echo "Setup completed."

update-prod: stop build up setup

install-laravel:
	@rm -rf wb-back/* && docker-compose exec -u www-data wb-app laravel new /var/www/wb-back

%:
	@: