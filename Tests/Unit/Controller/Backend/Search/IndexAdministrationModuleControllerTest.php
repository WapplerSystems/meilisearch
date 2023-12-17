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

use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexAdministrationModuleController;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchAdminService;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Core\Client\Endpoint;

/**
 * Testcase for IndexQueueModuleController
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexAdministrationModuleControllerTest extends AbstractModuleController
{
    /**
     * @var IndexAdministrationModuleController|MockObject
     */
    protected $controller;

    protected function setUp(): void
    {
        parent::setUpConcreteModuleController(IndexAdministrationModuleController::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function testReloadIndexConfigurationAction(): void
    {
        $responseMock = $this->createMock(ResponseAdapter::class);
        $responseMock->expects(self::once())->method('getHttpStatus')->willReturn(200);

        $writeEndpointMock = $this->createMock(Endpoint::class);
        $adminServiceMock = $this->createMock(MeilisearchAdminService::class);
        $adminServiceMock->expects(self::once())->method('reloadCore')->willReturn($responseMock);
        $adminServiceMock->expects(self::once())->method('getPrimaryEndpoint')->willReturn($writeEndpointMock);

        $meilisearchConnection = $this->createMock(MeilisearchConnection::class);
        $meilisearchConnection->expects(self::once())->method('getAdminService')->willReturn($adminServiceMock);

        $fakeConnections = [$meilisearchConnection];
        $this->connectionManagerMock->expects(self::once())
            ->method('getConnectionsBySite')
            ->with($this->selectedSiteMock)
            ->willReturn($fakeConnections);
        $this->controller->reloadIndexConfigurationAction();
    }
}
