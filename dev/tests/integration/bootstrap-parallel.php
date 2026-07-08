<?php
/**
 * Bootstrap for parallel integration-test workers (paratest).
 *
 * paratest gives every worker process a TEST_TOKEN (1..N). Each worker
 * gets its own framework thread id — which namespaces the sandbox dir —
 * and its own database, a `_t<token>` suffix on the bougie tenant db
 * (`bin/parallel-prime` creates the databases and sandboxes up front).
 *
 * The stock framework bootstrap re-deploys test modules into the shared
 * app/code/Magento on every include, which races when N workers include
 * it at once, so workers take an exclusive lock for the bootstrap phase;
 * TESTS_PARALLEL_RUN=1 keeps a finished worker's shutdown handler from
 * deleting those modules out from under the workers still running.
 */
$token = (int) (getenv('TEST_TOKEN') ?: 0);
define('TESTS_PARALLEL_THREAD', $token);
define('TESTS_PARALLEL_RUN', 1);
if ($token > 0) {
    putenv('BOUGIE_SERVICE_MARIADB_DATABASE=' . getenv('BOUGIE_SERVICE_MARIADB_DATABASE') . '_t' . $token);
}

// The lock only guards the first-ever test-module deploy (the framework
// copies them into the shared app/code/Magento once; see the
// deployTestModules patch). Once deployed, bootstraps are sandbox-local
// and safe to run concurrently — skip the lock so module starts don't
// convoy behind each other.
$deployed = glob(dirname(__DIR__, 3) . '/app/code/Magento/TestModule*/registration.php', GLOB_NOSORT);
if ($deployed) {
    require __DIR__ . '/framework/bootstrap.php';
    return;
}

$lockDir = __DIR__ . '/tmp';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}
$lock = fopen($lockDir . '/.bootstrap.lock', 'c');
flock($lock, LOCK_EX);
try {
    require __DIR__ . '/framework/bootstrap.php';
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
