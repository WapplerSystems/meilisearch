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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Validator;

use WapplerSystems\Meilisearch\System\Validator\Path;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the Path helper class.
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class PathTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canIsValidMeilisearchPathisValidPath()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidMeilisearchPath('/sorl/core_da');

        self::assertTrue($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidMeilisearchPathEmptyString()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidMeilisearchPath('');

        self::assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidMeilisearchPathisInvalidPathButAppears()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidMeilisearchPath('/sorl/#/core_da');

        self::assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidMeilisearchPathisInvalidPath()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidMeilisearchPath('/sorl/core_da?bogus');

        self::assertFalse($isValidPath);
    }
}
