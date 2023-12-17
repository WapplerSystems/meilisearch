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

namespace WapplerSystems\Meilisearch;

use MeiliSearch\Client;
use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;
use WapplerSystems\Meilisearch\Exception\InvalidConnectionException;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository as PagesRepositoryAtExtMeilisearch;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use WapplerSystems\Meilisearch\System\Util\SiteUtility;
use Doctrine\DBAL\Exception as DBALException;
use Solarium\Core\Client\Endpoint;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;

use TYPO3\CMS\Core\Utility\GeneralUtility;

use function json_encode;

/**
 * ConnectionManager is responsible to create MeilisearchConnection objects.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class ConnectionManager implements SingletonInterface
{
    /**
     * @var MeilisearchConnection[]
     */
    protected static array $connections = [];

    protected PagesRepositoryAtExtMeilisearch $pagesRepositoryAtExtMeilisearch;

    protected SiteRepository $siteRepository;

    public function __construct(
        PagesRepositoryAtExtMeilisearch $pagesRepositoryAtExtMeilisearch = null,
        SiteRepository $siteRepository = null
    ) {
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
        $this->pagesRepositoryAtExtMeilisearch = $pagesRepositoryAtExtMeilisearch ?? GeneralUtility::makeInstance(PagesRepositoryAtExtMeilisearch::class);
    }

    /**
     * Creates a Meilisearch connection for read and write endpoints
     *
     * @throw InvalidConnectionException
     */
    public function getMeilisearchConnectionForEndpoints(array $readEndpointConfiguration, array $writeEndpointConfiguration): MeilisearchConnection
    {
        $connectionHash = md5(json_encode($readEndpointConfiguration) . json_encode($writeEndpointConfiguration));
        if (!isset(self::$connections[$connectionHash])) {
            $readEndpoint = new Endpoint($readEndpointConfiguration);
            if (!$this->isValidEndpoint($readEndpoint)) {
                throw new InvalidConnectionException('Invalid read endpoint');
            }

            $writeEndpoint = new Endpoint($writeEndpointConfiguration);
            if (!$this->isValidEndpoint($writeEndpoint)) {
                throw new InvalidConnectionException('Invalid write endpoint');
            }

            self::$connections[$connectionHash] = GeneralUtility::makeInstance(MeilisearchConnection::class, $readEndpoint, $writeEndpoint);
        }

        return self::$connections[$connectionHash];
    }

    /**
     * Checks if endpoint is valid
     */
    protected function isValidEndpoint(Endpoint $endpoint): bool
    {
        return
            !empty($endpoint->getHost())
            && !empty($endpoint->getPort())
            && !empty($endpoint->getCore())
        ;
    }

    /**
     * Creates a solr configuration from the configuration array and returns it.
     */
    public function getConnectionFromConfiguration(array $solrConfiguration): MeilisearchConnection
    {
        return $this->getMeilisearchConnectionForEndpoints($solrConfiguration['read'], $solrConfiguration['write']);
    }

    /**
     * Gets a Meilisearch connection for a page ID.
     *
     * @throws DBALException
     * @throws NoMeilisearchConnectionFoundException
     */
    public function getConnectionByPageId(int $pageId, int $language = 0, string $mountPointParametersList = ''): MeilisearchConnection
    {
        try {
            $site = $this->siteRepository->getSiteByPageId($pageId, $mountPointParametersList);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getMeilisearchConnectionConfiguration($language);
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets a Meilisearch connection for a TYPO3 site and language
     *
     * @throws NoMeilisearchConnectionFoundException
     */
    public function getConnectionByTypo3Site(Typo3Site $typo3Site, int $languageUid = 0): MeilisearchConnection
    {
        $config = SiteUtility::getMeilisearchConnectionConfiguration($typo3Site, $languageUid);
        if ($config === null) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage(
                $typo3Site->getRootPageId(),
                $languageUid
            );
        }

        try {
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage(
                $typo3Site->getRootPageId(),
                $languageUid
            );
        }
    }

    /**
     * Gets a Meilisearch connection for a root page ID.
     *
     * @throws DBALException
     * @throws NoMeilisearchConnectionFoundException
     */
    public function getConnectionByRootPageId(int $pageId, ?int $language = 0): MeilisearchConnection
    {
        try {
            $site = $this->siteRepository->getSiteByRootPageId($pageId);
            $this->throwExceptionOnInvalidSite($site, 'No site for pageId ' . $pageId);
            $config = $site->getMeilisearchConnectionConfiguration($language ?? 0);
            return $this->getConnectionFromConfiguration($config);
        } catch (InvalidArgumentException) {
            throw $this->buildNoConnectionExceptionForPageAndLanguage($pageId, $language);
        }
    }

    /**
     * Gets all connections found.
     *
     * @return MeilisearchConnection[] An array of initialized {@link MeilisearchConnection} connections
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getAllConnections(): array
    {
        $solrConnections = [];
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            foreach ($site->getAllMeilisearchConnectionConfigurations() as $solrConfiguration) {
                $solrConnections[] = $this->getConnectionFromConfiguration($solrConfiguration);
            }
        }

        return $solrConnections;
    }

    /**
     * Gets all connections configured for a given site.
     *
     * @return MeilisearchConnection[] An array of Meilisearch connection objects {@link MeilisearchConnection}
     */
    public function getConnectionsBySite(Site $site): array
    {
        $connections = [];

        foreach ($site->getAllMeilisearchConnectionConfigurations() as $languageId => $solrConnectionConfiguration) {
            $connections[$languageId] = $this->getConnectionFromConfiguration($solrConnectionConfiguration);
        }

        return $connections;
    }

    /**
     * Builds and returns the exception instance of {@link NoMeilisearchConnectionFoundException}
     */
    protected function buildNoConnectionExceptionForPageAndLanguage(int $pageId, int $language): NoMeilisearchConnectionFoundException
    {
        $message = 'Could not find a Meilisearch connection for page [' . $pageId . '] and language [' . $language . '].';
        $noMeilisearchConnectionException = $this->buildNoConnectionException($message);

        $noMeilisearchConnectionException->setLanguageId($language);
        return $noMeilisearchConnectionException;
    }

    /**
     * Throws a no connection exception when no site was passed.
     *
     * @throws NoMeilisearchConnectionFoundException
     */
    protected function throwExceptionOnInvalidSite(?Site $site, string $message): void
    {
        if (!is_null($site)) {
            return;
        }

        throw $this->buildNoConnectionException($message);
    }

    /**
     * Build a NoMeilisearchConnectionFoundException with the passed message.
     */
    protected function buildNoConnectionException(string $message): NoMeilisearchConnectionFoundException
    {
        return GeneralUtility::makeInstance(
            NoMeilisearchConnectionFoundException::class,
            $message,
            1575396474
        );
    }


    private function createClientFromArray(array $configuration)
    {
        return new Client(($configuration['scheme'] ?? 'http') . '://' . $configuration['host'] . ':' . $configuration['port'], $configuration['apiKey'] ?? null, new \TYPO3\CMS\Core\Http\Client(\TYPO3\CMS\Core\Http\Client\GuzzleClientFactory::getClient()));
    }

}
