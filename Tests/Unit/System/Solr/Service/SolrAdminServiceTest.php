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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Meilisearch\Service;

use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\Service\MeilisearchAdminService;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use stdClass;

use function json_encode;

/**
 * Tests the MeilisearchAdminService class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class MeilisearchAdminServiceTest extends SetUpUnitTestCase
{
    protected MeilisearchAdminService|MockObject $service;
    protected Client|MockObject $clientMock;
    protected Endpoint|MockObject $endpointMock;

    protected function setUp(): void
    {
        $this->endpointMock = $this->createMock(Endpoint::class);
        $this->endpointMock->expects(self::any())->method('getScheme')->willReturn('http');
        $this->endpointMock->expects(self::any())->method('getHost')->willReturn('localhost');
        $this->endpointMock->expects(self::any())->method('getPort')->willReturn(8983);
        $this->endpointMock->expects(self::any())->method('getPath')->willReturn('/meilisearch');
        $this->endpointMock->expects(self::any())->method('getCore')->willReturn('core_en');
        $this->endpointMock->expects(self::any())->method('getCoreBaseUri')->willReturn('http://localhost:8983/meilisearch/core_en/');

        $this->clientMock = $this->createMock(Client::class);
        $this->clientMock->expects(self::any())->method('getEndpoint')->willReturn($this->endpointMock);
        $this->adminService = $this->getMockBuilder(MeilisearchAdminService::class)->setConstructorArgs([$this->clientMock])->onlyMethods(['_sendRawGet'])->getMock();
        parent::setUp();
    }
    /**
     * @test
     */
    public function getLukeMetaDataIsSendingRequestToExpectedUrl(): void
    {
        $fakedLukeResponse = $this->createMock(ResponseAdapter::class);
        $this->assertGetRequestIsTriggered('http://localhost:8983/meilisearch/core_en/admin/luke?numTerms=50&wt=json&fl=%2A', $fakedLukeResponse);
        $result = $this->adminService->getLukeMetaData(50);

        self::assertSame($fakedLukeResponse, $result, 'Could not get expected result from getLukeMetaData');
    }

    /**
     * @test
     */
    public function getPluginsInformation(): void
    {
        $fakePluginsResponse = $this->createMock(ResponseAdapter::class);
        $this->assertGetRequestIsTriggered('http://localhost:8983/meilisearch/core_en/admin/plugins?wt=json', $fakePluginsResponse);
        $result = $this->adminService->getPluginsInformation();
        self::assertSame($fakePluginsResponse, $result, 'Could not get expected result from getPluginsInformation');
    }

    /**
     * @test
     */
    public function getSystemInformation(): void
    {
        $fakeSystemInformationResponse = $this->createMock(ResponseAdapter::class);
        $this->assertGetRequestIsTriggered('http://localhost:8983/meilisearch/core_en/admin/system?wt=json', $fakeSystemInformationResponse);
        $result = $this->adminService->getSystemInformation();
        self::assertSame($fakeSystemInformationResponse, $result, 'Could not get expected result from getSystemInformation');
    }

    /**
     * @test
     */
    public function getMeilisearchServerVersion(): void
    {
        $fakeRawResponse = new stdClass();
        $fakeRawResponse->lucene = new stdClass();
        $fakeRawResponse->lucene->{'meilisearch-spec-version'} = '6.2.1';
        $fakeSystemInformationResponse = new ResponseAdapter(
            json_encode($fakeRawResponse),
            200,
        );
        $this->assertGetRequestIsTriggered('http://localhost:8983/meilisearch/core_en/admin/system?wt=json', $fakeSystemInformationResponse);
        $result = $this->adminService->getMeilisearchServerVersion();
        self::assertSame('6.2.1', $result, 'Can not get meilisearch version from faked response');
    }

    /**
     * @test
     */
    public function canGetMeilisearchConfigNameFromFakedXmlResponse(): void
    {
        $fakeTestSchema = $this->getFixtureContentByName('meilisearchconfig.xml');
        $fakedMeilisearchConfigResponse = $this->createMock(ResponseAdapter::class);
        $fakedMeilisearchConfigResponse->expects(self::once())->method('getRawResponse')->willReturn($fakeTestSchema);

        $this->assertGetRequestIsTriggered('http://localhost:8983/meilisearch/core_en/admin/file?file=meilisearchconfig.xml', $fakedMeilisearchConfigResponse);
        $expectedSchemaVersion = 'tx_meilisearch-9-9-9--20221020';
        self::assertSame($expectedSchemaVersion, $this->adminService->getMeilisearchconfigName(), 'MeilisearchAdminService could not parse the meilisearchconfig version as expected');
    }

    protected function assertGetRequestIsTriggered(string $url, mixed $fakeResponse): void
    {
        $this->adminService->expects(self::once())->method('_sendRawGet')->with($url)->willReturn($fakeResponse);
    }
}
