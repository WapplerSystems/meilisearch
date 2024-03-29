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

use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Abstract testcase for the processing finished events
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class SetUpProcessingFinishedEvent extends SetUpUnitTestCase
{
    protected const EVENT_CLASS = 'stdClass';
    /**
     * @test
     */
    public function canSetAndReturnProcessedEvent(): void
    {
        $processedEvent = new RecordUpdatedEvent(123, 'tx_foo_bar');

        $eventClass = static::EVENT_CLASS;
        $event = new $eventClass($processedEvent);

        self::assertEquals($processedEvent, $event->getDataUpdateEvent());
    }
}
