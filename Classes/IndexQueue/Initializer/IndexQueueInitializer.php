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

namespace WapplerSystems\Meilisearch\IndexQueue\Initializer;

use WapplerSystems\Meilisearch\Domain\Site\Site;

/**
 * Interface to initialize items in the Index Queue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface IndexQueueInitializer
{
    /**
     * Sets the site for the initializer.
     *
     * @param Site $site The site to initialize Index Queue items for.
     */
    public function setSite(Site $site): void;

    /**
     * Set the type (usually a Db table name) of items to initialize.
     *
     * @param string $type Type to initialize.
     */
    public function setType(string $type): void;

    /**
     * Sets the name of the indexing configuration to initialize.
     *
     * @param string $indexingConfigurationName Indexing configuration name
     */
    public function setIndexingConfigurationName(string $indexingConfigurationName): void;

    /**
     * Sets the configuration for how to index a type of items.
     *
     * @param array $indexingConfiguration Indexing configuration from TypoScript
     */
    public function setIndexingConfiguration(array $indexingConfiguration): void;

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return bool TRUE if initialization was successful, FALSE on error.
     */
    public function initialize(): bool;
}
