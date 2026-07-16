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
        }
    }
}
