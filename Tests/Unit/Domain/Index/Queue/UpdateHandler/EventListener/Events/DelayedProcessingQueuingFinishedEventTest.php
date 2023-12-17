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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Index\Queue\UpdateHandler\EventListener\Events;

use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingQueuingFinishedEvent;

/**
 * Testcase for the DelayedProcessingQueuingFinishedEvent
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class DelayedProcessingQueuingFinishedEventTest extends SetUpProcessingFinishedEvent
{
    protected const EVENT_CLASS = DelayedProcessingQueuingFinishedEvent::class;
}
