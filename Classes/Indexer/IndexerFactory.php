<?php

declare(strict_types=1);

namespace WapplerSystems\Meilisearch\Indexer;


use Symfony\Component\DependencyInjection\ServiceLocator;

class IndexerFactory
{

    public function __construct(
        private readonly ServiceLocator $indexers
    ) {
    }


    public function hasIndexer(string $identifier): bool
    {
        return $this->indexers->has($identifier);
    }

    /**
     * Get a registered operation as instance
     * @param $identifier
     * @return AbstractIndexer
     * @throws \UnexpectedValueException
     */
    public function getIndexer($identifier) : AbstractIndexer
    {
        if (!$this->hasIndexer($identifier)) {
            throw new \UnexpectedValueException('Indexer with identifier ' . $identifier . ' is not registered.', 1685139937);
        }

        return $this->indexers->get($identifier);
    }


    public function createIndexerForItem(Item $item) : AbstractIndexer
    {
        return $this->getIndexer($item->getType());
    }


}
