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
$ PHPRC=$PWD/etc/php.ini bougie run -- php ../../../vendor/bin/phpunit \
    -c phpunit.xml.dist testsuite/Magento/Directory
```

Two small adapters make the stock framework talk to bougie's
socket-only MariaDB:

- [patches/testframework-db-mysql-unix-socket.patch](patches/testframework-db-mysql-unix-socket.patch)
  teaches the framework's `Db\Mysql` the same convention Magento's PDO
  adapter already has: a `db-host` containing `/` is a Unix socket, so
  its `mariadb` / `mariadb-dump` shell-outs get `--socket=` instead of
  `--host/--port` (which MariaDB clients treat as TCP-only). Applied
  automatically by bougie's native composer-patches support — any
  `patches/*.patch` is picked up on `bougie sync`. Candidate for
  upstreaming to Mage-OS.
- `PHPRC` points at [etc/php.ini](dev/tests/integration/etc/php.ini):
  `memory_limit = -1` for the child PHP processes the framework spawns
  (its own `setup:install`), which `-d` flags can't reach.

One temporary wart: bougie 0.44.0 deploys the magento2-base skeleton
before applying patches, so after a fresh `bougie sync` copy the
patched file over the deployed one (CI does the same; the ordering fix
is upstream in bougie):

```console
$ cp vendor/mage-os/magento2-base/dev/tests/integration/framework/Magento/TestFramework/Db/Mysql.php \
     dev/tests/integration/framework/Magento/TestFramework/Db/Mysql.php
```

Tests for your own modules under `app/code/*/*/Test/Integration` are
picked up by the same suite automatically.

Heads-up when running other upstream suites: a few assert
stock-Magento specifics — e.g. `Magento/Cms`'s `testGetConfigCssUrls`
expects the stock admin-theme path and fails against Mage-OS 3.1's own
`MageOS/m137-admin-theme`. That's an upstream testsuite gap, not an
environment issue.
