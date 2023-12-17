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

namespace WapplerSystems\Meilisearch\Tests\Integration\Domain\Search\MeilisearchDocument;

use WapplerSystems\Meilisearch\Domain\Search\MeilisearchDocument\Repository;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MeilisearchDocumentRepositoryTest extends IntegrationTest
{
    /**
     * @var Repository|null
     */
    protected ?Repository $meilisearchDocumentRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
        // trigger an index
        $this->importCSVDataSet(__DIR__ . '/../../../Controller/Fixtures/indexing_data.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages([1, 2, 3, 4, 5]);

        $this->meilisearchDocumentRepository = GeneralUtility::makeInstance(Repository::class);
    }

    /**
     * Executed after each test. Empties meilisearch and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        unset($this->meilisearchDocumentRepository);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canFindByPageIdAndByLanguageId()
    {
        $meilisearchDocumentsCollection = $this->meilisearchDocumentRepository->findByPageIdAndByLanguageId(3, 0);

        self::assertIsArray($meilisearchDocumentsCollection, 'Repository did not get Document collection from pageId 3.');
        self::assertNotEmpty($meilisearchDocumentsCollection, 'Repository did not get apache meilisearch documents from pageId 3.');
        self::assertInstanceOf(Document::class, $meilisearchDocumentsCollection[0], 'MeilisearchDocumentRepository returned not an array of type Document.');
    }

    /**
     * @test
     */
    public function canReturnEmptyCollectionIfNoConnectionToMeilisearchServerIsEstablished()
    {
        $meilisearchDocumentsCollection = $this->meilisearchDocumentRepository->findByPageIdAndByLanguageId(3, 777);
        self::assertEmpty($meilisearchDocumentsCollection, 'MeilisearchDocumentRepository does not return empty collection if no connection to core can be established.');
    }
}
