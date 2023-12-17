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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Index\Queue\GarbageRemover;

use WapplerSystems\Meilisearch\Domain\Index\Queue\GarbageRemover\AbstractStrategy;
use WapplerSystems\Meilisearch\System\Logging\SolrLogManager;
use WapplerSystems\Meilisearch\System\Solr\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Solr\Service\SolrWriteService;
use WapplerSystems\Meilisearch\System\Solr\SolrConnection;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

/**
 * Abstract strategy tests
 */
abstract class AbstractStrategyTest extends SetUpUnitTestCase
{
    protected AbstractStrategy|AccessibleObjectInterface $subject;

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     * @dataProvider canDeleteRecordInAllSolrConnectionsDataProvider
     */
    public function canDeleteRecordInAllSolrConnections(int $status, bool $commit): void
    {
        $query = 'type:tx_fakeextension_foo AND uid:123 AND siteHash:#siteHash#';
        $response = new ResponseAdapter(
            '',
            $status,
            'msg' . $status,
        );

        $writeServiceMock = $this->createMock(SolrWriteService::class);
        $writeServiceMock
            ->expects(self::once())
            ->method('deleteByQuery')
            ->with($query)
            ->willReturn($response);

        $writeServiceMock
            ->expects((($status === 200 && $commit) ? self::once() : self::never()))
            ->method('commit');

        $writeServiceMock
            ->expects(($status !== 200 ? self::once() : self::never()))
            ->method('getCorePath')
            ->willReturn('core_en');

        $connectionMock = $this->createMock(SolrConnection::class);
        $connectionMock
            ->expects(self::atLeastOnce())
            ->method('getWriteService')
            ->willReturn($writeServiceMock);

        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        GeneralUtility::addInstance(SolrLogManager::class, $solrLogManagerMock);
        if ($status !== 200) {
            $solrLogManagerMock
                ->expects(self::once())
                ->method('error')
                ->with(
                    'Couldn\'t delete index document',
                    [
                        'status' => $status,
                        'msg' => 'msg' . $status,
                        'core' => 'core_en',
                        'query' => $query,
                    ]
                );
        } else {
            $solrLogManagerMock
                ->expects(self::never())
                ->method('log');
        }

        $this->subject->_call(
            'deleteRecordInAllSolrConnections',
            'tx_fakeextension_foo',
            123,
            [$connectionMock],
            '#siteHash#',
            $commit
        );
    }

    /**
     * Data provider for canDeleteRecordInAllSolrConnectionsDataProvider
     */
    public function canDeleteRecordInAllSolrConnectionsDataProvider(): \Generator
    {
        yield 'can delete and commit' => [
            'status' => 200,
            'commit' => true,
        ];

        yield 'can delete and skip commit' => [
            'status' => 200,
            'commit' => false,
        ];

        yield 'can log failed request' => [
            'status' => 500,
            'commit' => true,
        ];
    }
}
