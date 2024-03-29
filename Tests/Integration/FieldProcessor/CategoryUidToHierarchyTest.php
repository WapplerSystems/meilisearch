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

namespace WapplerSystems\Meilisearch\Tests\Integration\FieldProcessor;

use WapplerSystems\Meilisearch\FieldProcessor\CategoryUidToHierarchy;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the CategoryUidToHierarchy
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class CategoryUidToHierarchyTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canConvertToCategoryIdToHierarchy()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_category.csv');
        $processor = GeneralUtility::makeInstance(CategoryUidToHierarchy::class);
        $result = $processor->process([2]);
        $expectedResult = ['0-1/', '1-1/2/'];
        self::assertSame($result, $expectedResult, 'Hierarchy processor did not build expected hierarchy');
    }
}
