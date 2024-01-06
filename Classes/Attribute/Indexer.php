<?php

declare(strict_types=1);


namespace WapplerSystems\Meilisearch\Attribute;

use Attribute;

/**
 * Service tag to autoconfigure operations
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Indexer
{
    public const TAG_NAME = 'meilisearch.indexer';

    public function __construct(
        public string $type,
        public int $priority = 0
    ) {
    }
}
