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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Search\ResultSet\Facets;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\DefaultFacetQueryBuilder;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Class DefaultFacetQueryBuilderTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DefaultFacetQueryBuilderTest extends SetUpUnitTestCase
{
    /**
     * Whe nothing is set, no exclude tags should be set.
     *
     * faceting {
     *    facets {
     *       type {
     *          field = type
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     *
     * @test
     */
    public function testWhenKeepAllOptionsOnSelectionIsNotConfiguredNoExcludeTagIsAdded()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_meilisearch.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_meilisearch.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $defaultQueryBuilder = new DefaultFacetQueryBuilder();
        $result = $defaultQueryBuilder->build('color', $fakeConfiguration);

        self::assertStringNotContainsString('{!ex', $result['facet.field'][0]);
    }
}
