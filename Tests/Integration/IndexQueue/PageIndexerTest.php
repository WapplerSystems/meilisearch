<?php

declare(strict_types=1);

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

namespace WapplerSystems\Meilisearch\Tests\Integration\IndexQueue;

use WapplerSystems\Meilisearch\Indexer\PageIndexer;
use WapplerSystems\Meilisearch\Indexer\PageIndexerRequest;
use WapplerSystems\Meilisearch\Indexer\PageIndexerResponse;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 */
class PageIndexerTest extends IntegrationTest
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/meilisearch',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();

        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            page.10.renderObj.5 = TEXT
            page.10.renderObj.5.field = header
            page.10.stdWrap.dataWrap = <!--TYPO3SEARCH_begin-->|<!--TYPO3SEARCH_end-->
            '
        );
    }

    /**
     * Executed after each test. Emptys meilisearch and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @param string $fixture
     * @param int $expectedNumFound
     * @param array $expectedAccessFieldValues
     * @param array $expectedContents
     *
     * @test
     * @dataProvider canIndexPageWithAccessProtectedContentIntoMeilisearchDataProvider
     */
    public function canIndexPageWithAccessProtectedContentIntoMeilisearch(
        string $fixture,
        int $expectedNumFound,
        array $expectedAccessFieldValues,
        array $expectedContents,
        string $core = 'core_en'
    ): void {
        $this->cleanUpMeilisearchServerAndAssertEmpty($core);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture . '.csv');

        $createPageIndexerMock = function (): PageIndexerRequest {
            $requestMock = $this->getMockBuilder(PageIndexerRequest::class)
                ->onlyMethods(['send'])
                ->getMock();
            $sendCallback = function ($indexRequestUrl) use ($requestMock): PageIndexerResponse {
                return $this->sendPageIndexerRequest($indexRequestUrl, $requestMock);
            };
            $requestMock->method('send')->willReturnCallback($sendCallback);

            return $requestMock;
        };

        $pageIndexer = $this->getMockBuilder(PageIndexer::class)
            ->onlyMethods(['getPageIndexerRequest'])
            ->getMock();
        $pageIndexer->method('getPageIndexerRequest')->willReturnCallback($createPageIndexerMock);

        $item = $this->getIndexQueueItem(4711);
        $pageIndexer->index($item);

        // we wait to make sure the document will be available in meilisearch
        $this->waitToBeVisibleInMeilisearch($core);

        $meilisearchContent = json_decode(
            file_get_contents($this->getMeilisearchConnectionUriAuthority() . '/meilisearch/' . $core . '/select?q=*:*&sort=access%20asc'),
            true
        );

        self::assertEquals($expectedNumFound, $meilisearchContent['response']['numFound'] ?? 0, 'Could not index documents into Meilisearch');
        foreach ($expectedAccessFieldValues as $index => $expectedAccessFieldValue) {
            self::assertEquals(
                $expectedAccessFieldValue,
                $meilisearchContent['response']['docs'][$index]['access'][0] ?? '',
                'Wrong access settings document ' . ($index + 1)
            );
        }
        foreach ($expectedContents as $index => $expectedContent) {
            self::assertEquals(
                $expectedContent,
                $meilisearchContent['response']['docs'][$index]['content'] ?? '',
                'Wrong content in document ' . ($index + 1)
            );
        }
    }

    /**
     * Data provider for canIndexPageWithAccessProtectedContentIntoMeilisearch
     */
    public function canIndexPageWithAccessProtectedContentIntoMeilisearchDataProvider(): Traversable
    {
        yield 'protected page' => [
            'can_index_access_protected_page',
            1,
            [
                '2:1/c:0',
            ],
            [
                'public content of protected page',
            ],
        ];

        yield 'page for any login(-2)' => [
            'can_index_access_protected_page_show_at_any_login',
            1,
            [
                '2:-2/c:0',
            ],
            [
                'access restricted content for any login',
            ],
        ];

        yield 'protected page with protected content' => [
            'can_index_access_protected_page_with_protected_contents',
            2,
            [
                '2:1/c:0',
                '2:1/c:2',
            ],
            [
                'public content of protected page',
                'public content of protected pageprotected content of protected page',
            ],
        ];

        yield 'translation of protected page with protected content' => [
            'can_index_access_protected_page_with_protected_contents',
            2,
            [
                '2:1/c:0',
                '2:1/c:2',
            ],
            [
                'public content of protected page de',
                'public content of protected page deprotected content of protected page de',
            ],
            'core_de',
        ];

        yield 'public page with protected content and global content' => [
            'can_index_page_with_protected_content',
            2,
            [
                'c:0',
                'c:1',
            ],
            [
                'public ce',
                'protected cepublic ce',
            ],
        ];

        yield 'public page with protected and hide at login content' => [
            'can_index_page_with_protected_and_hideatlogin_content',
            2,
            [
                'c:0',
                'c:1',
            ],
            [
                'hide at login content',
                'protected ce',
            ],
        ];
    }

    /**
     * Sends a page indexer request
     *
     * In test environment we have to use an InternalRequest, this method
     * is intended to replace PageIndexerRequest->send()
     */
    protected function sendPageIndexerRequest(string $url, PageIndexerRequest $request): PageIndexerResponse
    {
        $internalRequest = new InternalRequest($url);

        foreach ($request->getHeaders() as $header) {
            [$headerName, $headerValue] = GeneralUtility::trimExplode(':', $header, true, 2);
            $internalRequest = $internalRequest->withAddedHeader($headerName, $headerValue);
        }

        $rawResponse = $this->executeFrontendSubRequest($internalRequest);
        $rawResponse->getBody()->rewind();

        $indexerResponse = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $decodedResponse = $indexerResponse->getResultsFromJson($rawResponse->getBody()->getContents());
        $rawResponse->getBody()->rewind();

        self::assertNotNull($decodedResponse, 'Failed to execute Page Indexer Request during integration test');

        $requestId = $decodedResponse['requestId'] ?? null;
        self::assertNotNull($requestId, 'Request id not set as expected');
        $indexerResponse->setRequestId($requestId);
        foreach (($decodedResponse['actionResults'] ?? []) as $action => $actionResult) {
            $indexerResponse->addActionResult($action, $actionResult);
        }

        return $indexerResponse;
    }
}
