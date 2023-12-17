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

namespace WapplerSystems\Meilisearch\Tests\Integration\System\Records\SystemCategory;

use WapplerSystems\Meilisearch\System\Records\SystemCategory\SystemCategoryRepository;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the SystemCategoryRepository
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SystemCategoryRepositoryTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canFindOneByParentCategory()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_category.csv');

        /** @var SystemCategoryRepository $repository */
        $repository = GeneralUtility::makeInstance(SystemCategoryRepository::class);
        $category = $repository->findOneByUid(2);
        self::assertSame('child', $category['title'], 'Can not retrieve system category by uid');
    }
}
