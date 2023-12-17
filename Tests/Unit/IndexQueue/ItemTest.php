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

namespace WapplerSystems\Meilisearch\Tests\Unit\IndexQueue;

use WapplerSystems\Meilisearch\Domain\Index\Queue\QueueItemRepository;
use WapplerSystems\Meilisearch\IndexQueue\Item;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ItemTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canGetErrors()
    {
        $metaData = ['errors' => 'error during index'];
        $record = [];
        $item = new Item($metaData, $record, null, $this->createMock(QueueItemRepository::class));

        $errors = $item->getErrors();
        self::assertSame('error during index', $errors, 'Can not get errors from queue item');
    }

    /**
     * @test
     */
    public function canGetType()
    {
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = new Item($metaData, $record, null, $this->createMock(QueueItemRepository::class));

        $type = $item->getType();
        self::assertSame('pages', $type, 'Can not get type from queue item');
    }

    /**
     * @return array
     */
    public function getStateDataProvider(): array
    {
        return [
            'pending item' => [['item_type' => 'pages', 'indexed' => 3, 'changed' => 4], Item::STATE_PENDING],
            'indexed item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => 4], Item::STATE_INDEXED],
            'blocked item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => 4, 'errors' => 'Something bad happened'], Item::STATE_BLOCKED],
        ];
    }

    /**
     * @dataProvider getStateDataProvider
     * @test
     */
    public function canGetState($metaData, $expectedState)
    {
        $item = new Item($metaData, [], null, $this->createMock(QueueItemRepository::class));
        self::assertSame($expectedState, $item->getState(), 'Can not get state from item as expected');
    }

    /**
     * @test
     */
    public function testHasErrors()
    {
        $item = new Item([], [], null, $this->createMock(QueueItemRepository::class));
        self::assertFalse($item->getHasErrors(), 'Expected that item without any data has no errors');

        $item = new Item(['errors' => 'something is broken'], [], null, $this->createMock(QueueItemRepository::class));
        self::assertTrue($item->getHasErrors(), 'Item with errors was not indicated to have errors');
    }

    /**
     * @test
     */
    public function testHasIndexingProperties()
    {
        $item = new Item([], [], null, $this->createMock(QueueItemRepository::class));
        self::assertFalse($item->hasIndexingProperties(), 'Expected that empty item should not have any indexing properties');

        $item = new Item(['has_indexing_properties' => true], [], null, $this->createMock(QueueItemRepository::class));
        self::assertTrue($item->hasIndexingProperties(), 'Item with proper meta data should have indexing properties');
    }
}
