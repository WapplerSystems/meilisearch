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

use WapplerSystems\Meilisearch\System\Meilisearch\Parser\SchemaParser;
use WapplerSystems\Meilisearch\System\Meilisearch\Schema\Schema;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;

/**
 * Testcase for the SchemaParser class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SchemaParserTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function canParseLanguage()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson($this->getFixtureContentByName('schema.json'));
        self::assertSame('core_de', $schema->getManagedResourceId(), 'Could not parse id of managed resources from schema response.');
    }

    /**
     * @test
     */
    public function canParseName()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson($this->getFixtureContentByName('schema.json'));
        self::assertSame('tx_meilisearch-6-0-0--20161122', $schema->getName(), 'Could not parser name from schema response');
    }

    /**
     * @test
     */
    public function canReturnEmptySchemaWhenNoSchemaPropertyInResponse()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson('{}');
        self::assertInstanceOf(Schema::class, $schema, 'Can not get schema object from empty response');
    }
}
