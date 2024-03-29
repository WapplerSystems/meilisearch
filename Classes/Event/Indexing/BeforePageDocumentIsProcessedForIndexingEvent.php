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

namespace WapplerSystems\Meilisearch\Event\Indexing;

use WapplerSystems\Meilisearch\Indexer\Item;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Allows to add more documents to the Meilisearch index.
 *
 * Previously used with
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['Indexer']['indexPageAddDocuments']
 */
class BeforePageDocumentIsProcessedForIndexingEvent
{
    /**
     * @var Document[]
     */
    private array $documents = [];

    public function __construct(
        private readonly Document $document,
        private readonly Item $indexQueueItem,
        private readonly TypoScriptFrontendController $tsfe,
    ) {
        $this->documents[] = $this->document;
    }

    public function getSite(): Site
    {
        return $this->tsfe->getSite();
    }

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->tsfe->getLanguage();
    }

    public function getIndexQueueItem(): Item
    {
        return $this->indexQueueItem;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * @param Document[] $documents
     */
    public function addDocuments(array $documents): void
    {
        $this->documents = array_merge($this->documents, $documents);
    }

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getTsfe(): TypoScriptFrontendController
    {
        return clone $this->tsfe;
    }
}
