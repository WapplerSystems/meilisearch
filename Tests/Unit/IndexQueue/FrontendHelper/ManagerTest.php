<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\Meilisearch\Tests\Unit\IndexQueue\FrontendHelper;

use WapplerSystems\Meilisearch\Indexer\FrontendHelper\Manager;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ManagerTest extends SetUpUnitTestCase
{
    /**
     * @var Manager
     */
    protected $manager;

    protected function setUp(): void
    {
        $this->manager = new Manager();
        parent::setUp();
    }

    /**
     * @test
     */
    public function resolveActionReturnsNullWhenNoHandlerIsRegistered()
    {
        $handler = $this->manager->resolveAction('foo');
        self::assertNull($handler, 'Unregistered action should return null when it will be resolved');
    }

    /**
     * @test
     */
    public function exceptionIsThrownWhenInvalidActionHandlerIsRetrieved()
    {
        Manager::registerFrontendHelper('test', InvalidFakeHelper::class);
        $this->expectException(\RuntimeException::class);
        $message = InvalidFakeHelper::class . ' is not an implementation of WapplerSystems\Meilisearch\Indexer\FrontendHelper\FrontendHelper';
        $this->expectExceptionMessage($message);
        $handler = $this->manager->resolveAction('test');
    }
}
