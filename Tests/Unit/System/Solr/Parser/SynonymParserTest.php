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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Meilisearch\Parser;

use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SynonymParser;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Testcase for StopWordParser
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SynonymParserTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canParseSynonyms()
    {
        $parser = new SynonymParser();
        $synonyms = $parser->parseJson('foo', $this->getFixtureContentByName('synonym.json'));
        self::assertSame(['bar'], $synonyms, 'Could not parser synonyms from synonyms response');
    }
}
