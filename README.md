# CardNest

CardNest is a modular-monolith foundation for a trading-card collection platform. This first increment contains
identity, authentication, the Vue shell, infrastructure and quality checks only. Catalog, collection and marketplace
features are intentionally out of scope.

## Requirements

- Docker with Docker Compose
- Git

## Start locally

```bash
cp .env.example .env
cp backend/.env.example backend/.env.local
cp frontend/.env.example frontend/.env.local
docker compose run --rm backend composer install
docker compose run --rm --no-deps frontend npm install
docker compose up -d postgres redis
docker compose run --rm backend php bin/console doctrine:database:create --if-not-exists
docker compose run --rm backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose up backend frontend
```

Open the frontend at <http://localhost:5174>. The API is available at <http://localhost:8080/api/v1> and its health
endpoint is `/api/v1/health`.

## Quality checks

```bash
docker compose run --rm -e APP_ENV=test backend php bin/console doctrine:database:create --if-not-exists
docker compose run --rm -e APP_ENV=test backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm -e APP_ENV=test backend php bin/phpunit
docker compose run --rm backend vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker compose run --rm backend vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
docker compose run --rm --no-deps frontend npm test
docker compose run --rm --no-deps frontend npm run build
docker compose run --rm --no-deps frontend npm run test:e2e
```

The frontend image includes Chromium and its operating-system dependencies for Playwright. Rebuild the image after
changing the Playwright version in `frontend/package.json`.

See [docs/api.md](docs/api.md) for the current HTTP contract.

## Architecture

The backend is one Symfony application divided into modules with `Domain`, `Application`, `Infrastructure` and
`Presentation` layers. Doctrine entities never leave the backend: controllers return explicit response DTOs.
Authentication uses random bearer tokens; only SHA-256 hashes are stored, tokens expire after 30 days and logout
deletes the active token. E-mail delivery is represented by an interface and asynchronous message, with a safe local
logging adapter until a real provider is selected.
