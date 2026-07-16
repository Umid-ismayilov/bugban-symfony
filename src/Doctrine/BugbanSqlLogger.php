<?php

namespace Bugban\Symfony\Doctrine;

use Bugban\Sdk\Bugban;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * OPTIONAL slow-query bridge for Doctrine DBAL 2.x / 3.x.
 *
 * NOTE: the SQLLogger interface was REMOVED in DBAL 4 — never reference this
 * class unless `interface_exists('Doctrine\\DBAL\\Logging\\SQLLogger')` is
 * true (autoloading it on DBAL 4 would fail). The bundle itself never loads
 * it automatically; register it yourself, e.g.:
 *
 *     $connection->getConfiguration()->setSQLLogger(new BugbanSqlLogger());
 *
 * On DBAL 4 (or if you prefer), call \Bugban\Sdk\Bugban::recordQuery()
 * manually or use \Bugban\Sdk\Support\TracedPdo. Durations are handed to the
 * SDK, which drops anything faster than the configured slow_query_ms.
 */
class BugbanSqlLogger implements SQLLogger
{
    /** @var string|null */
    private $sql;
    /** @var array */
    private $params = array();
    /** @var float|null */
    private $start;

    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        $this->sql = is_string($sql) ? $sql : null;
        $this->params = is_array($params) ? $params : array();
        $this->start = microtime(true);
    }

    public function stopQuery()
    {
        try {
            if ($this->sql === null || $this->start === null) {
                return;
            }
            $durationMs = (microtime(true) - $this->start) * 1000;
            $meta = array();
            if (!empty($this->params)) {
                $meta['bindings'] = array_values($this->params);
            }
            Bugban::recordQuery($this->sql, $durationMs, $meta);
        } catch (\Exception $e) {
            // telemetry must be non-fatal
        } catch (\Throwable $e) {
            // non-fatal
        } finally {
            $this->sql = null;
            $this->start = null;
            $this->params = array();
        }
    }
}
