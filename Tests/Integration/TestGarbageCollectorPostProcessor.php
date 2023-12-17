<?php

namespace WapplerSystems\Meilisearch\Tests\Integration;

use WapplerSystems\Meilisearch\GarbageCollectorPostProcessor;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class TestGarbageCollectorPostProcessor
 */
class TestGarbageCollectorPostProcessor implements SingletonInterface, GarbageCollectorPostProcessor
{
    protected bool $hookWasCalled = false;

    /**
     * Post-processing of garbage collector
     *
     * @see \WapplerSystems\Meilisearch\GarbageCollector::collectGarbage()
     */
    public function postProcessGarbageCollector(string $table, int $uid): void
    {
        $this->hookWasCalled = true;
    }

    public function isHookWasCalled(): bool
    {
        return $this->hookWasCalled;
    }
}
