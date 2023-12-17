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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\Options;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacetParser;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

class OptionsFacetParserTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canListFloatValuesAsOptionsFromMeilisearchResponse(): void
    {
        $responseAdapter = new ResponseAdapter(
            /* @lang JSON */
            '{
              "facets": {
                "floatOptions": {
                  "buckets": [
                    { "val": 0.001, "count": 1 },
                    { "val": 0.002, "count": 2 },
                    { "val": 0.003, "count": 3 }
                  ]
                }
              }
            }'
        );
        $optionsFacetParser = $this->getAccessibleMock(
            OptionsFacetParser::class,
            null,
            [],
            '',
            false
        );
        /** @link OptionsFacetParser::getOptionsFromMeilisearchResponse */
        $optionsArray = $optionsFacetParser->_call(
            'getOptionsFromMeilisearchResponse',
            'floatOptions',
            $responseAdapter,
        );
        self::assertCount(3, $optionsArray, 'EXT:meilisearch can not list floats in facets.');
    }
}
