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

namespace WapplerSystems\Meilisearch\Task;

use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Abstract scheduler task for meilisearch scheduler tasks, contains the logic to
 * retrieve the site, avoids serialization of site, when scheduler task is saved.
 */
abstract class AbstractMeilisearchTask extends AbstractTask
{
    /**
     * The site this task is supposed to initialize the index queue for.
     */
    protected ?Site $site = null;

    /**
     * The rootPageId of the site that should be reIndexed
     */
    protected string|int|null $rootPageId = null;

    public function getRootPageId(): string|int|null
    {
        return $this->rootPageId;
    }

    public function setRootPageId(int $rootPageId): void
    {
        $this->rootPageId = $rootPageId;
    }

    /**
     * Returns the site object
     *
     * @throws DBALException
     */
    public function getSite(): ?Site
    {
        if (!is_null($this->site)) {
            return $this->site;
        }

        try {
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $this->site = $siteRepository->getSiteByRootPageId((int)$this->rootPageId);
        } catch (InvalidArgumentException) {
            $logger = GeneralUtility::makeInstance(MeilisearchLogManager::class, __CLASS__);
            $logger->error('Scheduler task tried to get invalid site');
        }

        return $this->site;
    }

    /**
     * @return int[]|string[]
     */
    public function __sleep()
    {
        $properties = get_object_vars($this);
        // avoid serialization of the site and logger object
        unset($properties['site'], $properties['logger']);
        return array_keys($properties);
    }
}
