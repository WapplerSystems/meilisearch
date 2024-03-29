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

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\AbstractFacetPackage;

/**
 * Class NumericRangePackage
 */
class NumericRangePackage extends AbstractFacetPackage
{
    public function getParserClassName(): string
    {
        return NumericRangeFacetParser::class;
    }

    public function getQueryBuilderClassName(): string
    {
        return NumericRangeFacetQueryBuilder::class;
    }

    public function getUrlDecoderClassName(): string
    {
        return NumericRangeUrlDecoder::class;
    }
}
