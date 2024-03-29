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

namespace WapplerSystems\Meilisearch\Domain\Index\Queue\GarbageRemover;

use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class PageStrategy
 */
class PageStrategy extends AbstractStrategy
{
    /**
     * Removes the garbage of a page record.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function removeGarbageOfByStrategy(string $table, int $uid): void
    {
        if ($table === 'tt_content') {
            $this->collectPageGarbageByContentChange($uid);
            return;
        }

        if ($table === 'pages') {
            $this->collectPageGarbageByPageChange($uid);
        }
    }

    /**
     * Determines the relevant page id for a content element update. Deletes the page from meilisearch and requeues the
     * page for a reindex.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function collectPageGarbageByContentChange(int $ttContentUid): void
    {
        $contentElement = BackendUtility::getRecord('tt_content', $ttContentUid, 'uid, pid', '', false);
        $this->deleteInMeilisearchAndUpdateIndexQueue('pages', $contentElement['pid']);
    }

    /**
     * When a page was changed it is removed from the index and index queue.
     *
     * @throws DBALException
     */
    protected function collectPageGarbageByPageChange(int $uid): void
    {
        $pageOverlay = BackendUtility::getRecord('pages', $uid, 'l10n_parent, sys_language_uid', '', false);
        if (!empty($pageOverlay['l10n_parent']) && (int)($pageOverlay['l10n_parent']) !== 0) {
            $this->deleteIndexDocuments('pages', (int)$pageOverlay['l10n_parent'], (int)$pageOverlay['sys_language_uid']);
        } else {
            $this->deleteInMeilisearchAndRemoveFromIndexQueue('pages', $uid);
        }
    }
}
