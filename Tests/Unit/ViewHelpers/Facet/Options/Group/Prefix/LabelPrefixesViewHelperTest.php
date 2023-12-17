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

namespace WapplerSystems\Meilisearch\Tests\Unit\ViewHelpers\Facet\Options\Group\Prefix;

use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use WapplerSystems\Meilisearch\ViewHelpers\Facet\Options\Group\Prefix\LabelPrefixesViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class LabelPrefixesViewHelperTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canGetPrefixesSortedByOrderInCollection()
    {
        $optionCollection = $this->getTestFacetOptionCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->onlyMethods(['remove'])->getMock();
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $renderingContextMock->expects(self::any())->method('getVariableProvider')->willReturn($variableContainer);

        $testArguments['options'] = $optionCollection;
        $testArguments['length'] = 1;
        LabelPrefixesViewHelper::renderStatic($testArguments, function () {}, $renderingContextMock);
        self::assertTrue($variableContainer->exists('prefixes'), 'Expected that prefixes has been set');
        $prefixes = $variableContainer->get('prefixes');
        self::assertSame(['r', 'p', 'l'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    /**
     * @test
     */
    public function canGetPrefixesSortedAlphabeticalByLabel()
    {
        $optionCollection = $this->getTestFacetOptionCollection();

        $variableContainer = $this->getMockBuilder(StandardVariableProvider::class)->onlyMethods(['remove'])->getMock();
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $renderingContextMock->expects(self::any())->method('getVariableProvider')->willReturn($variableContainer);

        $testArguments['options'] = $optionCollection;
        $testArguments['length'] = 1;
        $testArguments['sortBy'] = 'alpha';
        LabelPrefixesViewHelper::renderStatic($testArguments, function () {}, $renderingContextMock);
        $prefixes = $variableContainer->get('prefixes');
        self::assertSame(['l', 'p', 'r'], $prefixes, 'ViewHelper registers unexpected prefixes from passed options');
    }

    /**
     * @return OptionCollection
     */
    protected function getTestFacetOptionCollection(): OptionCollection
    {
        $facet = $this->createMock(OptionsFacet::class);

        $roseRed = new Option($facet, 'Rose Red', 'rose_red', 14);
        $blue = new Option($facet, 'Polar Blue', 'polar_blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'lemon_yellow', 3);
        $red = new Option($facet, 'Rubin Red', 'rubin_red', 2);
        $royalGreen = new Option($facet, 'Royal Green', 'royal_green', 1);

        $optionCollection = new OptionCollection();
        $optionCollection->add($roseRed);
        $optionCollection->add($blue);
        $optionCollection->add($yellow);
        $optionCollection->add($red);
        $optionCollection->add($royalGreen);
        return $optionCollection;
    }
}
