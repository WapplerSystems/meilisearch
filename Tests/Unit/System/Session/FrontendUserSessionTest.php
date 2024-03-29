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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Session;

use WapplerSystems\Meilisearch\System\Session\FrontendUserSession;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Testcase for the SchemaParser class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FrontendUserSessionTest extends SetUpUnitTestCase
{
    protected FrontendUserAuthentication|MockObject $feUserMock;

    /**
     * @var FrontendUserSession
     */
    protected FrontendUserSession $session;

    protected function setUp(): void
    {
        $this->feUserMock = $this->createMock(FrontendUserAuthentication::class);
        $this->session = new FrontendUserSession($this->feUserMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function getEmptyArrayWhenNoLastSearchesInSession(): void
    {
        $lastSearches = $this->session->getLastSearches();
        self::assertSame([], $lastSearches, 'Expected to get an empty lastSearches array');
    }

    /**
     * @test
     */
    public function sessionDataWillBeRetrievedFromSessionForLastSearches(): void
    {
        $fakeSessionData = ['foo', 'bar'];
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_meilisearch_lastSearches')->willReturn($fakeSessionData);
        self::assertSame($fakeSessionData, $this->session->getLastSearches(), 'Session data from fe_user was not returned from session');
    }

    /**
     * @test
     */
    public function canSetLastSearchesInSession(): void
    {
        $lastSearches = ['TYPO3', 'meilisearch'];
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_meilisearch_lastSearches', $lastSearches);
        $this->session->setLastSearches($lastSearches);
    }

    /**
     * @test
     */
    public function getHasPerPageReturnsFalseWhenNothingIsSet(): void
    {
        self::assertFalse($this->session->getHasPerPage(), 'Has per page should be false');
    }

    /**
     * @test
     */
    public function getPerPageReturnsZeroWhenNothingIsSet(): void
    {
        self::assertSame(0, $this->session->getPerPage(), 'Expected to get 0 when nothing was set');
    }

    /**
     * @test
     */
    public function getPerPageFromSessionData(): void
    {
        $fakeSessionData = 12;
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_meilisearch_resultsPerPage')->willReturn($fakeSessionData);
        self::assertSame(12, $this->session->getPerPage(), 'Could not get per page from session data');
    }

    /**
     * @test
     */
    public function canSetPerPageInSessionData(): void
    {
        $lastSearches = 45;
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_meilisearch_resultsPerPage', $lastSearches);
        $this->session->setPerPage($lastSearches);
    }
}
