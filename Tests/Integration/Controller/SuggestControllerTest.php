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

namespace WapplerSystems\Meilisearch\Tests\Integration\Controller;

use WapplerSystems\Meilisearch\Controller\SuggestController;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Integration testcase to test for {@link SuggestController}
 *
 * @author Timo Hund
 * @group frontend
 */
class SuggestControllerTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultMeilisearchTestSiteConfiguration();
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            page = PAGE
            page.typeNum = 0
            # include suggest feature
            @import \'EXT:meilisearch/Configuration/TypoScript/Examples/Suggest/setup.typoscript\'
            '
        );
    }

    /**
     * Executed after each test. Empties meilisearch and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpMeilisearchServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canDoABasicSuggest()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString('Sweat', 'rand')->getBody());

        // we assume to get suggestions like Sweatshirt
        self::assertStringContainsString('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
    }

    /**
     * @test
     */
    public function canDoABasicSuggestWithoutCallback()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexing_data.csv');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString('Sweat')->getBody());

        // we assume to get suggestions like Sweatshirt
        self::assertStringContainsString('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
    }

    /**
     * @test
     */
    public function canSuggestWithUriSpecialChars()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/suggest_with_uri_special_chars.csv');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
			plugin.tx_meilisearch.suggest.suggestField = title
            '
        );

        $this->indexPages([1, 2, 3, 4, 5]);

        // @todo: add more variants
        // @TODO: Check why does meilisearch return some/larg instead of some/large
        $testCases = [
            [
                'prefix' => 'Some/',
                'expected' => 'suggestions":{"some/":1,"some/larg":1,"some/large/path":1}',
            ],
            [
                'prefix' => 'Some/Large',
                'expected' => 'suggestions":{"some/large/path":1}',
            ],
        ];
        foreach ($testCases as $testCase) {
            $this->expectSuggested($testCase['prefix'], $testCase['expected']);
        }
    }

    protected function expectSuggested(string $prefix, string $expected)
    {
        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString($prefix, 'rand')->getBody());

        //we assume to get suggestions like some/large/path
        self::assertStringContainsString($expected, $result, 'Response did not contain expected suggestions: ' . $expected);
    }

    protected function executeFrontendSubRequestForSuggestQueryString(string $queryString, string $callback = null): Response
    {
        $request = new InternalRequest('http://testone.site/en/');
        $request = $request
            ->withPageId(1)
            ->withQueryParameter('type', '7384')
            ->withQueryParameter('tx_meilisearch[queryString]', $queryString);

        if ($callback !== null) {
            $request = $request->withQueryParameter('tx_meilisearch[callback]', $callback);
        }
        return $this->executeFrontendSubRequest($request);
    }
}
