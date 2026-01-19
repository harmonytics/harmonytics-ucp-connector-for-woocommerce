#!/bin/bash

echo "=========================================="
echo "  Harmonytics UCP Connector for WooCommerce - Test Runner"
echo "=========================================="

# Install dependencies
echo ""
echo "[1/5] Installing system dependencies..."
apt-get update -qq
apt-get install -y -qq subversion default-mysql-client unzip git curl libzip-dev > /dev/null
docker-php-ext-install mysqli zip > /dev/null 2>&1
echo "      Done."

# Wait for database
echo ""
echo "[2/5] Waiting for database..."
echo "      Host: $WP_TESTS_DB_HOST, User: $WP_TESTS_DB_USER"
MAX_TRIES=30
TRIES=0
until mysql --skip-ssl -h "$WP_TESTS_DB_HOST" -u"$WP_TESTS_DB_USER" -p"$WP_TESTS_DB_PASS" -e "SELECT 1" 2>&1; do
    TRIES=$((TRIES+1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "      ERROR: Could not connect to database after $MAX_TRIES attempts"
        echo "      Debug: trying without password..."
        mysql -h "$WP_TESTS_DB_HOST" -u"$WP_TESTS_DB_USER" -e "SELECT 1" 2>&1 || true
        exit 1
    fi
    echo "      Waiting... ($TRIES/$MAX_TRIES)"
    sleep 2
done
echo "      Database is ready."

# Install Composer
echo ""
echo "[3/5] Installing Composer dependencies..."
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1
fi
composer install --no-interaction 2>&1 | tail -5
echo "      Done."

# Setup WordPress test environment
echo ""
echo "[4/5] Setting up WordPress test environment..."
bash /plugin/bin/install-wp-tests.sh wordpress_test "$WP_TESTS_DB_USER" "$WP_TESTS_DB_PASS" "$WP_TESTS_DB_HOST" latest true
echo "      Done."

# Run tests
echo ""
echo "[5/5] Running PHPUnit tests..."
echo "=========================================="
echo ""

# Check if specific test file was passed as argument
if [ -n "$1" ]; then
    ./vendor/bin/phpunit --colors=always "$@"
else
    ./vendor/bin/phpunit --colors=always
fi

TEST_EXIT=$?

echo ""
echo "=========================================="
echo "  Tests completed!"
echo "=========================================="

exit $TEST_EXIT
