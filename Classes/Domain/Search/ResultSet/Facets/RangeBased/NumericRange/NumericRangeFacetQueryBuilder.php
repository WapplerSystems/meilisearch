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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;

class NumericRangeFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * Builds query parts for numeric range facet
     */
    public function build(string $facetName, TypoScriptConfiguration $configuration): array
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        $tag = '';
        if ((bool)($facetConfiguration['keepAllOptionsOnSelection'] ?? null) === true) {
            $tag = '{!ex=' . $facetConfiguration['field'] . '}';
        }
        $facetParameters['facet.range'][] = $tag . $facetConfiguration['field'];

        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.start'] = $facetConfiguration['numericRange.']['start'];
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.end'] = $facetConfiguration['numericRange.']['end'];
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.gap'] = $facetConfiguration['numericRange.']['gap'];

        return $facetParameters;
    }
}
