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
$ PHPRC=$PWD/etc/php.ini bougie run -- sh -c \
    'PATH="$PWD/bin:$PATH" exec php ../../../vendor/bin/phpunit \
     -c phpunit.xml.dist testsuite/Magento/Directory'
```

Two small adapters make the stock framework talk to bougie's
socket-only MariaDB:

- `PHPRC` points at [etc/php.ini](dev/tests/integration/etc/php.ini):
  `memory_limit = -1` for the child PHP processes the framework spawns
  (its own `setup:install`), and `pdo_mysql.default_socket` expanded
  from the `BOUGIE_SERVICE_MARIADB_SOCKET` env `bougie run` injects.
- [bin/](dev/tests/integration/bin/db-client) wrappers rewrite the
  framework's `mariadb` / `mariadb-dump` CLI calls from `--host/--port`
  (which force TCP) to `--socket=`.

Tests for your own modules under `app/code/*/*/Test/Integration` are
picked up by the same suite automatically.

Heads-up when running other upstream suites: a few assert
stock-Magento specifics — e.g. `Magento/Cms`'s `testGetConfigCssUrls`
expects the stock admin-theme path and fails against Mage-OS 3.1's own
`MageOS/m137-admin-theme`. That's an upstream testsuite gap, not an
environment issue.
