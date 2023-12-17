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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser\AbstractResultParser;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;

/**
 * Fake test parser
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TestResultParser extends AbstractResultParser
{
    /**
     * @inheritDoc
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true): SearchResultSet
    {
        // TODO: Implement parse() method.
        return $resultSet;
    }

    /**
     * @inheritDoc
     */
    public function canParse(SearchResultSet $resultSet): bool
    {
        return true;
    }
}
