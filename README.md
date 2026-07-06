# bougie-demo

A demo [Mage-OS](https://mage-os.org) store managed by
[bougie](https://github.com/cresset-tools/bougie), scaffolded with:

```console
$ bougie new bougie-demo --starter mageos
```

The repo commits only the project definition — `composer.json`,
`composer.lock`, and the integration-test config. Everything else
(vendor tree, Magento skeleton, PHP itself) is materialized by
`bougie sync`.

## Local development

```console
$ bougie start
```

That one command installs PHP + dependencies, brings up MariaDB,
Redis, OpenSearch, and RabbitMQ, runs Magento's installer, and serves
the store on its `*.bougie.run` URL.

## Integration tests

CI uses [setup-bougie](https://github.com/cresset-tools/setup-bougie)
to install bougie (with caching), then runs a slice of the Mage-OS
integration testsuite against bougie-provisioned services — see
[.github/workflows/integration.yml](.github/workflows/integration.yml).

The glue is
[dev/tests/integration/etc/install-config-mysql.php](dev/tests/integration/etc/install-config-mysql.php):
it feeds the `BOUGIE_SERVICE_*` tenant environment (injected by
`bougie run`) into Magento's test framework, so the same config works
on any machine:

```console
$ bougie service up
$ cd dev/tests/integration
$ bougie run -- php ../../../vendor/bin/phpunit \
    -c phpunit.xml.dist testsuite/Magento/Directory
```

The socket wiring lives entirely in the test-framework setup:
[install-config-mysql.php](dev/tests/integration/etc/install-config-mysql.php)
passes the tenant's socket path as `db-host` (from the
`BOUGIE_SERVICE_MARIADB_SOCKET` env `bougie run` injects). Two patches,
declared under `extra.patches` in [composer.json](composer.json)
(cweagans/composer-patches format, applied natively by bougie), make
the stock stack honor it — both are candidates for upstreaming to
Mage-OS:

- [patches/testframework-db-mysql-unix-socket.patch](patches/testframework-db-mysql-unix-socket.patch)
  teaches the test framework's `Db\Mysql` the convention Magento's PDO
  adapter already has: a `db-host` containing `/` is a Unix socket, so
  its `mariadb` / `mariadb-dump` shell-outs get `--socket=` instead of
  `--host/--port` (which MariaDB clients treat as TCP-only).
- [patches/framework-pdo-mysql-socket-reconnect.patch](patches/framework-pdo-mysql-socket-reconnect.patch)
  fixes the PDO adapter's half of the same story: the first `_connect`
  consumed `host` into `unix_socket` and unset it, so any reconnect on
  the same adapter died with "No host configured to connect". The
  one-line fix keeps `host = 'localhost'` alongside `unix_socket` —
  mysqlnd routes that combination through the socket.

No other adapters are needed (bougie ≥ 0.45): CLI PHP under
`bougie run` defaults to `memory_limit = -1` — including the child
processes the framework spawns for its own `setup:install` — and
patched files reach the deployed Magento skeleton.

Tests for your own modules under `app/code/*/*/Test/Integration` are
picked up by the same suite automatically.

Heads-up when running other upstream suites: a few assert
stock-Magento specifics — e.g. `Magento/Cms`'s `testGetConfigCssUrls`
expects the stock admin-theme path and fails against Mage-OS 3.1's own
`MageOS/m137-admin-theme`. That's an upstream testsuite gap, not an
environment issue.
