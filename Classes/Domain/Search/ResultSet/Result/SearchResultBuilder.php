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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result;

use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The SearchResultBuilder is responsible to build a SearchResult object from an \WapplerSystems\Meilisearch\System\Meilisearch\Document\Document
 * and should use a different class as SearchResult if configured.
 */
class SearchResultBuilder
{
    /**
     * This method is used to wrap the original meilisearch document instance in an instance of the configured SearchResult
     * class.
     */
    public function fromApacheMeilisearchDocument(Document $originalDocument): SearchResult
    {
        $searchResultClassName = $this->getResultClassName();
        $result = GeneralUtility::makeInstance($searchResultClassName, $originalDocument->getFields());

        if (!$result instanceof SearchResult) {
            throw new InvalidArgumentException('Could not create result object with class: ' . $searchResultClassName, 1470037679);
        }

        return $result;
    }

    protected function getResultClassName(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultClassName '] ?? SearchResult::class;
    }
}
