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

namespace WapplerSystems\Meilisearch\Tests\Integration\System\Configuration;

use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TypoScriptConfigurationTest extends IntegrationTest
{
    protected function setUp(): void
    {
        /** @var TypoScriptFrontendController|MockObject $tsfe */
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->onlyMethods([])
            ->disableOriginalConstructor()
            ->getMock();
        $GLOBALS['TSFE'] = $tsfe;
        parent::setUp();
    }

    /**
    * @test
    */
    public function testCanUsePlainValuesFromConfiguration()
    {
        $configuration = [
            'plugin.' => [
                'tx_meilisearch.' => [
                    'search.' => [
                        'sorting' => 1,
                    ],
                ],
            ],
        ];

        /** @var TypoScriptConfiguration $typoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);
        $sorting = $typoScriptConfiguration->getSearchSorting();
        self::assertTrue($sorting, 'Can not get sorting configuration from typoscript');
    }
}
