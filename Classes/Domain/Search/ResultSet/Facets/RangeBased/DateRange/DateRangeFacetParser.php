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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetParser;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\System\Data\DateTime;
use DateTime as PhpDateTime;
use Exception;

/**
 * Class DateRangeFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeFacetParser extends AbstractRangeFacetParser
{
    protected string $facetClass = DateRangeFacet::class;

    protected string $facetItemClass = DateRange::class;

    protected string $facetRangeCountClass = DateRangeCount::class;

    /**
     * Parses/hydrates result set's response to date range facet object structure
     */
    public function parse(SearchResultSet $resultSet, string $facetName, array $facetConfiguration): ?AbstractFacet
    {
        return $this->getParsedFacet(
            $resultSet,
            $facetName,
            $facetConfiguration,
            $this->facetClass,
            $this->facetItemClass,
            $this->facetRangeCountClass
        );
    }

    /**
     * Parses request value to date time object
     *
     * @throws Exception
     */
    protected function parseRequestValue(float|int|string|null $rawRequestValue): ?DateTime
    {
        $rawRequestValue = PhpDateTime::createFromFormat('Ymd', substr((string)$rawRequestValue, 0, 8));
        if ($rawRequestValue === false) {
            return null;
        }
        return new DateTime($rawRequestValue->format(DateTime::ISO8601));
    }

    /**
     *  Parses response value to date time
     *
     * @throws Exception
     */
    protected function parseResponseValue(float|int|string|null $rawResponseValue): DateTime
    {
        $rawDate = PhpDateTime::createFromFormat(PhpDateTime::ISO8601, $rawResponseValue);
        return new DateTime($rawDate->format(DateTime::ISO8601));
    }
}
