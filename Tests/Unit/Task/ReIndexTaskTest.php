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

namespace WapplerSystems\Meilisearch\Tests\Unit\Task;

use WapplerSystems\Meilisearch\Task\ReIndexTask;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Testcase for ReIndexTask
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ReIndexTaskTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canGetErrorMessageInAdditionalInformationWhenSiteNotAvailable()
    {
        $indexQueuerWorker = $this->getMockBuilder(ReIndexTask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSite'])
            ->getMock();

        $mesage = $indexQueuerWorker->getAdditionalInformation();
        $expectedMessage = 'Invalid site configuration for scheduler please re-create the task!';
        self::assertSame($expectedMessage, $mesage, 'Expect to get error message of non existing site');
    }
}
