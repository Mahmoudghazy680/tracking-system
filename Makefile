SHELL := /bin/bash

.PHONY: init-env env-check dns-check install-docker prepare-host fix-db-perms pull up ps logs down restart restart-app wait bootstrap validate deploy diagnose

init-env:
	@if [[ -f .env ]]; then echo ".env already exists"; else cp .env.example .env && echo "Created .env from .env.example"; fi

env-check:
	./scripts/validate-env.sh

dns-check:
	./scripts/check-dns.sh

install-docker:
	./scripts/install-docker-ubuntu.sh

prepare-host:
	./scripts/prepare-host.sh

fix-db-perms:
	./scripts/set-percona-permissions.sh

pull:
	docker-compose pull

up:
	docker-compose up -d

ps:
	docker-compose ps

logs:
	docker-compose logs -f

down:
	docker-compose down

restart:
	docker-compose restart

restart-app:
	docker-compose restart app

wait:
	./scripts/wait-for-stack.sh

bootstrap:
	./scripts/bootstrap-cattr.sh

validate:
	./scripts/validate-cattr.sh

deploy:
	./scripts/deploy-cattr.sh

diagnose:
	./scripts/diagnose-access.sh
