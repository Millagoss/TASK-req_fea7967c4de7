#!/bin/bash
set -e

echo "==> Running unit tests..."
php vendor/bin/phpunit --testsuite=Unit --colors=always

echo ""
echo "==> Running API tests..."
php vendor/bin/phpunit --testsuite=API --colors=always

echo ""
echo "==> Running all tests with coverage report..."
php vendor/bin/phpunit --colors=always --coverage-text

echo ""
echo "==> All tests passed!"
