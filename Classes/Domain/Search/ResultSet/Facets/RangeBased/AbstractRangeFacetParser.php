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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRange;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRange;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeFacet;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Event\Parser\AfterFacetIsParsedEvent;
use WapplerSystems\Meilisearch\System\Solr\ParsingUtil;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractRangeFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractRangeFacetParser extends AbstractFacetParser
{
    protected ?EventDispatcherInterface $eventDispatcher;

    public function injectEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function getParsedFacet(
        SearchResultSet $resultSet,
        string $facetName,
        array $facetConfiguration,
        string $facetClass,
        string $facetItemClass,
        string $facetRangeCountClass,
    ): ?AbstractFacet {
        $fieldName = $facetConfiguration['field'];
        $label = $this->getPlainLabelOrApplyCObject($facetConfiguration);
        $activeValue = $this->getActiveFacetValuesFromRequest($resultSet, $facetName);
        $response = $resultSet->getResponse();

        $valuesFromResponse = isset($response->facet_counts->facet_ranges->{$fieldName}) ? get_object_vars($response->facet_counts->facet_ranges->{$fieldName}) : [];

        /** @var NumericRangeFacet|DateRangeFacet $facet */
        $facet = GeneralUtility::makeInstance(
            $facetClass,
            $resultSet,
            $facetName,
            $fieldName,
            $label,
            $facetConfiguration
        );

        $facet->setIsAvailable(count($valuesFromResponse) > 0);
        $facet->setIsUsed(count($activeValue) > 0);

        if (!empty($valuesFromResponse)) {
            $rangeCounts = [];
            $allCount = 0;

            $countsFromResponse = isset($valuesFromResponse['counts']) ? ParsingUtil::getMapArrayFromFlatArray($valuesFromResponse['counts']) : [];

            foreach ($countsFromResponse as $rangeCountValue => $count) {
                $rangeCountValue = $this->parseResponseValue($rangeCountValue);
                $rangeCount = GeneralUtility::makeInstance($facetRangeCountClass, $rangeCountValue, $count);
                $rangeCounts[] = $rangeCount;
                $allCount += $count;
            }

            $fromInResponse = $this->parseResponseValue($valuesFromResponse['start']);
            $toInResponse = $this->parseResponseValue($valuesFromResponse['end']);

            if (isset($activeValue[0]) && preg_match('/(-?\d*?)-(-?\d*)/', $activeValue[0], $rawValues) == 1) {
                $rawFrom = $rawValues[1];
                $rawTo = $rawValues[2];
            } else {
                $rawFrom = 0;
                $rawTo = 0;
            }

            $from = $this->parseRequestValue($rawFrom);
            $to = $this->parseRequestValue($rawTo);

            $type = $facetConfiguration['type'] ?? 'numericRange';
            $gap = $facetConfiguration[$type . '.']['gap'] ?? 1;

            /** @var DateRange|NumericRange $range */
            $range = GeneralUtility::makeInstance(
                $facetItemClass,
                $facet,
                $from,
                $to,
                $fromInResponse,
                $toInResponse,
                $gap,
                $allCount,
                $rangeCounts,
                true
            );
            $facet->setRange($range);
        }

        if (isset($this->eventDispatcher)) {
            /** @var AfterFacetIsParsedEvent $afterFacetIsParsedEvent */
            $afterFacetIsParsedEvent = $this->eventDispatcher
                ->dispatch(new AfterFacetIsParsedEvent($facet, $facetConfiguration));
            $facet = $afterFacetIsParsedEvent->getFacet();
        }

        return $facet;
    }

    abstract protected function parseRequestValue(float|int|string|null $rawRequestValue): mixed;

    abstract protected function parseResponseValue(float|int|string|null $rawResponseValue): mixed;
}
