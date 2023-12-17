<?php

declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Tests\Integration\Access;

use WapplerSystems\Meilisearch\Access\Rootline;
use WapplerSystems\Meilisearch\Tests\Integration\IntegrationTest;

/**
 * Class RootlineTest
 */
class RootlineTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canGetAccessRootlineByPageId()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/user_protected_page.csv');
        $accessRootline = Rootline::getAccessRootlineByPageId(10);
        self::assertSame('10:4711', (string)$accessRootline, 'Did not determine expected access rootline for fe_group protected page');

        $accessRootline = Rootline::getAccessRootlineByPageId(1);
        self::assertSame('', (string)$accessRootline, 'Access rootline for non protected page should be empty');
    }
}
