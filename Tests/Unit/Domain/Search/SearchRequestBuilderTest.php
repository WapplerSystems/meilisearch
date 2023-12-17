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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search;

use WapplerSystems\Meilisearch\Domain\Search\SearchRequest;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequestBuilder;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Session\FrontendUserSession;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchRequestBuilderTest extends SetUpUnitTestCase
{
    protected FrontendUserSession|MockObject $sessionMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected SearchRequestBuilder|MockObject $searchRequestBuilder;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->sessionMock = $this->createMock(FrontendUserSession::class);
        $this->searchRequestBuilder = new SearchRequestBuilder($this->configurationMock, $this->sessionMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function testPageIsResettedWhenValidResultsPerPageValueWasPassed(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->willReturn([10, 25]);
        $this->configurationMock->expects(self::any())->method('getSearchPluginNamespace')
            ->willReturn(SearchRequest::DEFAULT_PLUGIN_NAMESPACE);
        $this->assertPerPageInSessionWillBeChanged();

        $requestArguments = [
            'q' => 'test',
            'resultsPerPage' => 25,
            'page' => 5, // pagination page
        ];
        $request = $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
        self::assertSame($request->getPage(), null, 'Page was not resetted.');
    }

    /**
     * @test
     */
    public function testPerPageValueIsNotSetInSession(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->willReturn([10, 25]);
        $this->assertPerPageInSessionWillNotBeChanged();

        $requestArguments = ['q' => 'test', 'page' => 3];
        $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
    }

    private function assertPerPageInSessionWillBeChanged(): void
    {
        $this->sessionMock->expects(self::once())->method('setPerPage');
    }

    private function assertPerPageInSessionWillNotBeChanged(): void
    {
        $this->sessionMock->expects(self::never())->method('setPerPage');
    }
}
