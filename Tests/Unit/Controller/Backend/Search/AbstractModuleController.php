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

namespace WapplerSystems\Meilisearch\Tests\Unit\Controller\Backend\Search;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Controller\Backend\Search\AbstractModuleController as ModuleController;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\Indexer\Queue;
use WapplerSystems\Meilisearch\System\Mvc\Backend\Service\ModuleDataStorageService;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

abstract class AbstractModuleController extends SetUpUnitTestCase
{
    /**
     * @var ModuleController|MockObject
     */
    protected $controller;

    protected Site|MockObject $selectedSiteMock;
    protected ConnectionManager|MockObject $connectionManagerMock;

    /**
     * Initializes the concrete backend module controller
     */
    protected function setUpConcreteModuleController(
        string $concreteModuleControllerClass,
        array $mockMethods = ['addFlashMessage']
    ): void {
        $this->selectedSiteMock = $this->createMock(Site::class);
        /** @var ModuleController|MockObject $subject */
        $subject = $this->getMockBuilder($concreteModuleControllerClass)
            ->setConstructorArgs(
                [
                    'moduleTemplateFactory' => $this->createMock(ModuleTemplateFactory::class),
                    'iconFactory' => $this->createMock(IconFactory::class),
                    'moduleDataStorageService' => $this->createMock(ModuleDataStorageService::class),
                    'siteRepository' => $this->createMock(SiteRepository::class),
                    'siteFinder' => $this->createMock(SiteFinder::class),
                    'meilisearchConnectionManager' => $this->connectionManagerMock = $this->createMock(ConnectionManager::class),
                    'indexQueue' => $this->createMock(Queue::class),
                ]
            )
            ->onlyMethods($mockMethods)
            ->getMock();
        $uriBuilderMock = $this->getMockBuilder(UriBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['uriFor'])->getMock();
        $uriBuilderMock->expects(self::any())
            ->method('uriFor')
            ->willReturn('index');
        $subject->injectUriBuilder($uriBuilderMock);
        $subject->setSelectedSite($this->selectedSiteMock);
        $this->controller = $subject;
    }
}
