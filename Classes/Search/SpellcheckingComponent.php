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

namespace WapplerSystems\Meilisearch\Search;

use WapplerSystems\Meilisearch\Domain\Search\Query\QueryBuilder;
use WapplerSystems\Meilisearch\Event\Search\AfterSearchQueryHasBeenPreparedEvent;

/**
 * Spellchecking search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SpellcheckingComponent
{
    public function __construct(protected readonly QueryBuilder $queryBuilder) {}

    /**
     * Initializes the search component.
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        if ($event->getTypoScriptConfiguration()->getSearchConfiguration()['spellchecking'] ?? false) {
            $query = $this->queryBuilder->startFrom($event->getQuery())->useSpellcheckingFromTypoScript()->getQuery();
            $event->setQuery($query);
        }
    }
}
