<?php

declare(strict_types=1);

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

namespace WapplerSystems\Meilisearch\Event\Indexing;

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

namespace WapplerSystems\Meilisearch\Tests\Unit\Domain\Index\Queue;

use WapplerSystems\Meilisearch\Domain\Index\Queue\QueueInitializationService;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\IndexQueue\Queue;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueueInitializerServiceTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function allIndexConfigurationsAreUsedWhenWildcardIsPassed(): void
    {
        $queueMock = $this->createMock(Queue::class);
        GeneralUtility::addInstance(Queue::class, $queueMock);
        GeneralUtility::addInstance(Queue::class, $queueMock);
        $service = $this->getMockBuilder(QueueInitializationService::class)->onlyMethods(['executeInitializer'])->setConstructorArgs([new NoopEventDispatcher()])->getMock();

        $fakeTs = [
            'plugin.' => [
                'tx_solr.' => [
                    'index.' => [
                        'queue.' => [
                            'my_pages' => 1,
                            'my_pages.' => [
                                'initialization' => 'MyPagesInitializer',
                                'type' => 'pages',
                                'fields.' => [
                                    'title' => 'title',
                                ],
                            ],
                            'my_news' => 1,
                            'my_news.' => [
                                'initialization' => 'MyNewsInitializer',
                                'type' => 'tx_news_domain_model_news',
                                'fields.' => [
                                    'title' => 'title',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeTs);

        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $service
            ->expects(self::exactly(2))
            ->method('executeInitializer')
            ->withConsecutive(
                [$siteMock, 'my_pages', 'MyPagesInitializer', 'pages', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_pages.']],
                [$siteMock, 'my_news', 'MyNewsInitializer', 'tx_news_domain_model_news', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_news.']]
            );
        $service->initializeBySiteAndIndexConfiguration($siteMock, '*');
    }
}
