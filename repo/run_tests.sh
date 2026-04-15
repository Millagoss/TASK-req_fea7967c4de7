#!/bin/bash
set -e

echo "==> Running unit tests..."
docker compose run --rm --no-deps test vendor/bin/phpunit --testsuite=Unit --colors=always

echo ""
echo "==> Running API tests..."
docker compose run --rm --no-deps test vendor/bin/phpunit --testsuite=API --colors=always

echo ""
echo "==> All tests passed!"
