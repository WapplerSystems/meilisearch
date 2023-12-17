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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Index\Queue\UpdateHandler;

use WapplerSystems\Meilisearch\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\IndexQueue\Queue;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use WapplerSystems\Meilisearch\System\TCA\TCAService;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract testcase for the update handlers
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class SetUpUpdateHandler extends SetUpUnitTestCase
{
    /**
     * @var ConfigurationAwareRecordService|MockObject
     */
    protected $recordServiceMock;

    /**
     * @var FrontendEnvironment|MockObject
     */
    protected $frontendEnvironmentMock;

    /**
     * @var TypoScriptConfiguration|MockObject
     */
    protected $typoScriptConfigurationMock;

    /**
     * @var TCAService|MockObject
     */
    protected $tcaServiceMock;

    /**
     * @var Queue|MockObject
     */
    protected $indexQueueMock;

    /**
     * @var PagesRepository|MockObject
     */
    protected PagesRepository|MockObject $pagesRepositoryMock;

    protected function setUp(): void
    {
        $this->recordServiceMock = $this->createMock(ConfigurationAwareRecordService::class);
        $this->frontendEnvironmentMock = $this->createMock(FrontendEnvironment::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->indexQueueMock = $this->createMock(Queue::class);
        $this->pagesRepositoryMock = $this->createMock(PagesRepository::class);
        GeneralUtility::addInstance(PagesRepository::class, $this->pagesRepositoryMock);

        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->frontendEnvironmentMock
            ->expects(self::any())
            ->method('getSolrConfigurationFromPageId')
            ->willReturn($this->typoScriptConfigurationMock);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TCA']);
        parent::tearDown();
    }
}
