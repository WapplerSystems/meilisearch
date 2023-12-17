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

use WapplerSystems\Meilisearch\IndexQueue\AbstractIndexer;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractIndexerTest extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['detectSerializedValue'] = [];
        parent::setUp();
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomContentElements(): void
    {
        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];

        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Response of SOLR_CLASSIFICATION is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');

        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM'), 'Non configured fields should allways be unserialized');
        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM'), 'Non configured fields should allways be unserialized');
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomInvalidSerializedValueDetector(): void
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['detectSerializedValue'][] = InvalidSerializedValueDetector::class;
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/.*InvalidSerializedValueDetector must implement interface.*/');

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
        ];

        // when an invalid detector is registered we expect that an exception is thrown
        AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM');
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomValidSerializedValueDetector(): void
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['detectSerializedValue'][] = ValidSerializedValueDetector::class;

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM'), 'Every value should be treated as serialized by custom detector');
    }

    /**
     * Test that field values can be resolved
     * @test
     * @dataProvider indexingDataProvider
     */
    public function resolveFieldValue(array $indexingConfiguration, string $meilisearchFieldName, array $data, $expectedValue): void
    {
        $subject = new class () extends AbstractIndexer {};
        $tsfe = $this->createMock(TypoScriptFrontendController::class);
        self::assertEquals(
            $this->callInaccessibleMethod(
                $subject,
                'resolveFieldValue',
                $indexingConfiguration,
                $meilisearchFieldName,
                $data,
                $tsfe
            ),
            $expectedValue
        );
    }

    public function indexingDataProvider(): \Generator
    {
        yield 'meilisearch field defined as string' => [
            ['meilisearchFieldName_stringS' => 'meilisearchFieldName'],
            'meilisearchFieldName_stringS',
            ['meilisearchFieldName' => 'test'],
            'test',
        ];
        yield 'meilisearch field defined as int' => [
            ['meilisearchFieldName_intS' => 'meilisearchFieldName'],
            'meilisearchFieldName_intS',
            ['meilisearchFieldName' => 123],
            123,
        ];
        yield 'meilisearch field not defined' => [
            ['meilisearchFieldName_stringS' => 'meilisearchFieldName'],
            'meilisearchFieldName_stringS',
            [],
            null,
        ];
    }
}
