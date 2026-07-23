<?php

namespace Bugban\Symfony;

use Bugban\Sdk\Bugban;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bugban Symfony bundle. Register in config/bundles.php:
 *
 *     Bugban\Symfony\BugbanBundle::class => ['all' => true],
 */
class BugbanBundle extends Bundle
{
    /**
     * boot() runs on every kernel boot (every request / CLI run), unlike the
     * extension's load() which only runs when the container is (re)compiled and
     * cached. We initialize the SDK here so the static client exists at runtime.
     */
    public function boot(): void
    {
        parent::boot();

        if ($this->container !== null && $this->container->hasParameter('bugban._config')) {
            Bugban::init($this->container->getParameter('bugban._config'));
            $this->registerQueryRunner();
        }
    }

    /**
     * Let the Bugban panel re-run one of this app's own captured SELECTs and
     * report the timing. Uses Doctrine's connection inside a transaction that is
     * always rolled back; only the row COUNT is returned, never row data.
     */
    private function registerQueryRunner(): void
    {
        try {
            if ($this->container === null || !$this->container->has('doctrine.dbal.default_connection')) {
                return;
            }
            $container = $this->container;
            // The core SDK may be older than this adapter (stale lock file or a
            // manual libs/ copy loaded first). Never call into it blindly.
            if (!method_exists('\\Bugban\\Sdk\\Bugban', 'setQueryRunner')) {
                return;
            }

            Bugban::setQueryRunner(function ($sql, array $bindings) use ($container) {
                $conn = $container->get('doctrine.dbal.default_connection');
                $conn->beginTransaction();
                try {
                    // DBAL 3/4 renamed fetchAllNumeric(); DBAL 2 has fetchAll().
                    $rows = method_exists($conn, 'fetchAllNumeric')
                        ? $conn->fetchAllNumeric($sql, $bindings)
                        : $conn->fetchAll($sql, $bindings);

                    return is_array($rows) ? count($rows) : 0;
                } finally {
                    try {
                        $conn->rollBack();
                    } catch (\Exception $e) {
                        // Nothing was written; a failed rollback is not fatal.
                    }
                }
            });
        } catch (\Exception $e) {
            // Monitoring must never break the app.
        } catch (\Throwable $e) {
            // Same for engine errors.
        }
    }
}
