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

namespace WapplerSystems\Meilisearch\Tests\Integration\Domain\Index\PageIndexer;

use WapplerSystems\Meilisearch\Domain\Index\PageIndexer\PageUriBuilder;
use WapplerSystems\Meilisearch\IndexQueue\Item;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests for checking if the uri is built for a page
 */
class PageUriBuilderTest extends IntegrationTest
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/wapplersystems/meilisearch/Tests/Integration/Fixtures/Extensions/fake_extension3',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function pageIndexingUriCanBeModifiedViaEventListener(): void
    {
        $subject = GeneralUtility::makeInstance(PageUriBuilder::class);
        $item = new Item(['item_uid' => 1, 'root' => 1, 'item_type' => 'pages']);
        $url = $subject->getPageIndexingUriFromPageItemAndLanguageId($item);
        self::assertEquals('http://testone.site/en/', (string)$url);
        $item->setIndexingProperty('size', 'enorme');
        $url = $subject->getPageIndexingUriFromPageItemAndLanguageId($item);
        self::assertEquals('http://testone.site/en/?&larger=large', (string)$url);
    }
}
