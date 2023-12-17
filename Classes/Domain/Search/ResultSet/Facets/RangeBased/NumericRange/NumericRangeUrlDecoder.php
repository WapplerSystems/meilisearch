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

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\FacetUrlDecoderInterface;
use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;

/**
 * Parser to build Meilisearch range queries from tx_meilisearch[filter]
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class NumericRangeUrlDecoder implements FacetUrlDecoderInterface
{
    /**
     * Delimiter for ranges in the URL.
     */
    public const DELIMITER = '-';

    /**
     * Parses the given range from a GET parameter and returns a Meilisearch range
     * filter.
     *
     * @param string $value The range filter from the URL.
     * @param array $configuration Facet configuration
     *
     * @return string Lucene query language filter to be used for querying Meilisearch
     *
     * @throws InvalidArgumentException
     */
    public function decode(string $value, array $configuration = []): string
    {
        preg_match('/(-?\d*?)' . self::DELIMITER . '(-?\d*)/', $value, $filterParts);
        if ($filterParts[1] == '' || $filterParts[2] == '') {
            throw new InvalidArgumentException(
                'Invalid numeric range given',
                1466062730
            );
        }

        return '[' . (int)$filterParts[1] . ' TO ' . (int)$filterParts[2] . ']';
    }
}
