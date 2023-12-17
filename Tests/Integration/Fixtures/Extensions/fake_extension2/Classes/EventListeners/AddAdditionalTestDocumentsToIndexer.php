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

namespace WapplerSystems\MeilisearchFakeExtension2\EventListeners;

use WapplerSystems\Meilisearch\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use WapplerSystems\Meilisearch\System\Solr\Document\Document;

final class AddAdditionalTestDocumentsToIndexer
{
    public function __invoke(BeforeDocumentIsProcessedForIndexingEvent $event): void
    {
        if (($event->getIndexQueueItem()->getRecord()['activate-event-listener'] ?? '') === true) {
            $event->addDocuments([new Document(['can-be-an-alternative-record' => 'additional-test-document'])]);
        }
    }
}
