# Stock Allocation API

A REST API for managing orders and allocating stock across multiple warehouses. Built with Symfony 8 and PHP 8.4.

The system uses a greedy algorithm to minimize the number of warehouses used per order while handling concurrent requests with pessimistic locking.

## Requirements

- Docker & Docker Compose

## Setup

```bash
# Build containers
docker compose build --no-cache

# Start services
docker compose up --wait

# Run database migrations
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Load sample data
docker compose exec php bin/console doctrine:fixtures:load --no-interaction
```

The API is now available at `https://localhost`

## Running Tests

```bash
docker compose exec php bin/phpunit
```

## API Usage

All endpoints require the `X-API-KEY` header. Sample keys from fixtures:

- `vinted-api-key-2025`
- `senukai-api-key-2025`
- `test-api-key`

> **Note:** Use `-k` flag with curl to accept the self-signed certificate.

### Create Order

```bash
curl -k -X POST https://localhost/api/orders -H "Content-Type: application/json" -H "X-API-KEY: test-api-key" -d '{"items": {"BOX-S": 10, "BOX-M": 5}}'
```

### Get Order

```bash
curl -k https://localhost/api/orders/1 -H "X-API-KEY: test-api-key"
```

### Ship Order

```bash
curl -k -X POST https://localhost/api/orders/1/ship -H "X-API-KEY: test-api-key"
```

### Cancel Order

```bash
curl -k -X POST https://localhost/api/orders/1/cancel -H "X-API-KEY: test-api-key"
```

### List Products

```bash
curl -k https://localhost/api/products -H "X-API-KEY: test-api-key"
```

### List Warehouses

```bash
curl -k https://localhost/api/warehouses -H "X-API-KEY: test-api-key"
```

### Get Warehouse Stock

```bash
curl -k https://localhost/api/warehouses/1 -H "X-API-KEY: test-api-key"
```