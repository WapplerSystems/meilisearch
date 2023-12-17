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

namespace WapplerSystems\Meilisearch\Tests\Integration\System\Meilisearch;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MeilisearchConnectionTest
 */
class MeilisearchConnectionTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
    }

    /**
     * There is following scenario:
     *
     *  [0]
     *   |
     *   ——[ 1] First site
     *   |   |
     *   |   ——[11] Subpage of first site
     *   |
     *   ——[111] Second site
     *   |   |
     *   |   ——[21] Subpage of second site
     *   |
     *   ——[ 3] Detached and non Root Page-Tree
     *       |
     *       —— [31] Subpage 1 of Detached
     *       |
     *       —— [32] Subpage 2 of Detached
     *
     * @param ?int $pageUid defaults to 1
     */
    protected function canFindMeilisearchConnectionByPageAndReturn(?int $pageUid = 1): MeilisearchConnection
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/slim_basic_sites.csv');

        /** @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $messageOnNoMeilisearchConnectionFoundException = vsprintf(
            'The MeilisearchConnection for page with uid "%s" could not be found. Can\'t proceed with dependent tests.',
            [$pageUid]
        );
        try {
            $meilisearchConnection = $connectionManager->getConnectionByPageId($pageUid, 0);
            self::assertInstanceOf(MeilisearchConnection::class, $meilisearchConnection, $messageOnNoMeilisearchConnectionFoundException);
            return $meilisearchConnection;
        } catch (NoMeilisearchConnectionFoundException $exception) {
            self::fail($messageOnNoMeilisearchConnectionFoundException);
        }
    }

    /**
     * @test
     */
    public function typo3sHttpSettingsAreRecognizedByClient()
    {
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['connect_timeout'] = 0.0001;
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['timeout'] = 0.0001;
        $meilisearchConnection = $this->canFindMeilisearchConnectionByPageAndReturn();

        $httpClientAdapter = $meilisearchConnection->getService()->getClient()->getAdapter();
        $httpClientObject = $this->getInaccessiblePropertyFromObject(
            $httpClientAdapter,
            'httpClient'
        );

        $guzzleConfig = $this->getInaccessiblePropertyFromObject($httpClientObject, 'config');

        $httpSettingsIgnoredMessage = 'The client for solarium does not get TYPO3 system configuration for HTTP. ' . PHP_EOL .
            'Please check why "%s" does not taken into account or are overridden.';

        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['connect_timeout'],
            $guzzleConfig['connect_timeout'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'connect_timeout\']',
                ]
            )
        );
        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['timeout'],
            $guzzleConfig['timeout'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'timeout\']',
                ]
            )
        );
        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['headers']['User-Agent'],
            $guzzleConfig['headers']['User-Agent'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'headers\'][\'User-Agent\']',
                ]
            )
        );
    }
}
