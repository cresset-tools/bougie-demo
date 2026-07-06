<?php
/**
 * Wire Magento's integration test framework to the bougie-provisioned
 * services. Connection details come from the BOUGIE_SERVICE_* tenant
 * environment that `bougie run` injects (same vocabulary bougie's own
 * magento recipe uses for `setup:install`), so this file works on any
 * machine — dev laptop or CI — with `bougie up` and no local edits.
 */

$config = [
    // bougie's MariaDB is Unix-socket only. "localhost" routes both
    // consumers of this value through pdo_mysql.default_socket (set in
    // etc/php.ini from the env `bougie run` injects): mysqlnd natively,
    // and the framework's mariadb/mariadb-dump shell-outs via the
    // committed patch in patches/ (upstream Db\Mysql only speaks
    // --host/--port, which MariaDB clients treat as TCP-only). A raw
    // socket path here would trip the PDO adapter's config mutation on
    // reconnect ("No host configured to connect").
    'db-host' => 'localhost',
    'db-user' => getenv('BOUGIE_SERVICE_MARIADB_USER'),
    'db-password' => getenv('BOUGIE_SERVICE_MARIADB_PASSWORD'),
    // The project's tenant database. The test framework installs the
    // test application into it and wipes it between runs — fine for CI
    // and for a dedicated dev tenant.
    'db-name' => getenv('BOUGIE_SERVICE_MARIADB_DATABASE'),
    'db-prefix' => '',
    'backend-frontname' => 'backend',
    'search-engine' => 'opensearch',
    'opensearch-host' => getenv('BOUGIE_SERVICE_OPENSEARCH_HOST'),
    'opensearch-port' => getenv('BOUGIE_SERVICE_OPENSEARCH_PORT'),
    'admin-user' => \Magento\TestFramework\Bootstrap::ADMIN_NAME,
    'admin-password' => \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD,
    'admin-email' => \Magento\TestFramework\Bootstrap::ADMIN_EMAIL,
    'admin-firstname' => \Magento\TestFramework\Bootstrap::ADMIN_FIRSTNAME,
    'admin-lastname' => \Magento\TestFramework\Bootstrap::ADMIN_LASTNAME,
    'consumers-wait-for-messages' => '0',
];

// RabbitMQ is present when the Amqp module is installed and the
// service is up; only then does setup:install accept --amqp-*.
if (getenv('BOUGIE_SERVICE_RABBITMQ_HOST')) {
    $config += [
        'amqp-host' => getenv('BOUGIE_SERVICE_RABBITMQ_HOST'),
        'amqp-port' => getenv('BOUGIE_SERVICE_RABBITMQ_PORT'),
        'amqp-user' => getenv('BOUGIE_SERVICE_RABBITMQ_USER'),
        'amqp-password' => getenv('BOUGIE_SERVICE_RABBITMQ_PASSWORD'),
        'amqp-virtualhost' => getenv('BOUGIE_SERVICE_RABBITMQ_VHOST'),
    ];
}

return $config;
