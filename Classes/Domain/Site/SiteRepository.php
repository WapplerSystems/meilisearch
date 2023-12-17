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

namespace WapplerSystems\Meilisearch\Domain\Site;

use WapplerSystems\Meilisearch\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use WapplerSystems\Meilisearch\Exception\InvalidArgumentException;
use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\FrontendEnvironment\Tsfe;
use WapplerSystems\Meilisearch\System\Cache\TwoLevelCache;
use WapplerSystems\Meilisearch\System\Configuration\ExtensionConfiguration;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use WapplerSystems\Meilisearch\System\Util\SiteUtility;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class SiteRepository is responsible to retrieve instances of Site objects
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteRepository
{
    protected RootPageResolver $rootPageResolver;

    protected TwoLevelCache $runtimeCache;

    protected SiteFinder $siteFinder;

    protected ExtensionConfiguration $extensionConfiguration;

    protected FrontendEnvironment $frontendEnvironment;

    public function __construct(
        RootPageResolver $rootPageResolver = null,
        TwoLevelCache $twoLevelCache = null,
        SiteFinder $siteFinder = null,
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    ) {
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, 'runtime');
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Gets the Site for a specific page ID.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function getSiteByPageId(int $pageId, string $mountPointIdentifier = ''): ?Site
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($pageId, false, $mountPointIdentifier);
        return $this->getSiteByRootPageId($rootPageId);
    }

    /**
     * Gets the Site for a specific root page-id.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function getSiteByRootPageId(int $rootPageId): ?Site
    {
        $cacheId = 'SiteRepository' . '_' . 'getSiteByPageId' . '_' . $rootPageId;

        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->buildSite($rootPageId);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * Returns the first available Site.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getFirstAvailableSite(bool $stopOnInvalidSite = false): ?Site
    {
        $sites = $this->getAvailableSites($stopOnInvalidSite);
        return array_shift($sites);
    }

    /**
     * Gets all available TYPO3 sites with Meilisearch configured.
     *
     * @return Site[] An array of available sites
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getAvailableSites(bool $stopOnInvalidSite = false): array
    {
        $cacheId = 'SiteRepository' . '_' . 'getAvailableSites';

        $sites = $this->runtimeCache->get($cacheId);
        if (!empty($sites)) {
            return $sites;
        }

        $sites = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $this->runtimeCache->set($cacheId, $sites);

        return $sites;
    }

    /**
     * Returns available TYPO3 sites
     *
     * @return Site[]
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function getAvailableTYPO3ManagedSites(bool $stopOnInvalidSite): array
    {
        $typo3ManagedMeilisearchSites = [];
        $typo3Sites = $this->siteFinder->getAllSites();
        foreach ($typo3Sites as $typo3Site) {
            try {
                $rootPageId = $typo3Site->getRootPageId();
                if (isset($typo3ManagedMeilisearchSites[$rootPageId])) {
                    //get each site only once
                    continue;
                }
                $typo3ManagedMeilisearchSite = $this->buildSite($rootPageId);
                if ($typo3ManagedMeilisearchSite->isEnabled()) {
                    $typo3ManagedMeilisearchSites[$rootPageId] = $typo3ManagedMeilisearchSite;
                }
            } catch (Throwable $e) {
                if ($stopOnInvalidSite) {
                    throw new UnexpectedTYPO3SiteInitializationException(
                        'Something went wrong on TYPO3 site initialization. See stack trace for more information.',
                        1680859613,
                        $e
                    );
                }
            }
        }
        return $typo3ManagedMeilisearchSites;
    }

    /**
     * Creates an instance of the Site object.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    protected function buildSite(int $rootPageId): ?Site
    {
        $rootPageRecord = BackendUtility::getRecord('pages', $rootPageId);
        if (empty($rootPageRecord)) {
            throw new InvalidArgumentException(
                "The rootPageRecord for the given rootPageRecord ID '$rootPageId' could not be found in the database and can therefore not be used as site root rootPageRecord.",
                1487326416
            );
        }

        $this->validateRootPageRecord($rootPageRecord);

        return $this->buildTypo3ManagedSite($rootPageRecord);
    }

    /**
     * Returns the site hash for given domain.
     */
    protected function getSiteHashForDomain(string $domain): string
    {
        /** @var SiteHashService $siteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getSiteHashForDomain($domain);
    }

    /**
     * Validates given root page record, if it fits requirements.
     *
     * @throws InvalidArgumentException
     */
    protected function validateRootPageRecord(array $rootPageRecord): void
    {
        if (!SiteUtility::isRootPage($rootPageRecord)) {
            throw new InvalidArgumentException(
                'The rootPageRecord for the given rootPageRecord ID \'' . $rootPageRecord['uid'] . '\' is not marked as root rootPageRecord and can therefore not be used as site root rootPageRecord.',
                1309272922
            );
        }
    }

    /**
     * Builds a TYPO3 managed site with TypoScript configuration.
     *
     * @throws DBALException
     */
    protected function buildTypo3ManagedSite(array $rootPageRecord): ?Site
    {
        $typo3Site = $this->getTypo3Site($rootPageRecord['uid']);
        if (!$typo3Site instanceof CoreSite) {
            return null;
        }

        $domain = $typo3Site->getBase()->getHost();

        $siteHash = $this->getSiteHashForDomain($domain);
        $defaultLanguage = $typo3Site->getDefaultLanguage()->getLanguageId();
        $pageRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $availableLanguageIds = array_map(static function ($language) {
            return $language->getLanguageId();
        }, $typo3Site->getLanguages());

        // Try to get first instantiable TSFE for one of site languages, to get TypoScript with `plugin.tx_meilisearch.index.*`,
        // to be able to collect indexing configuration,
        // which are required for BE-Modules/CLI-Commands or RecordMonitor within BE/TCE-commands.
        // If TSFE for none of languages can be initialized, then the \WapplerSystems\Meilisearch\Domain\Site\Site object unusable at all,
        // so the rest of the steps in this method are not necessary, and therefore the null will be returned.
        $tsfeFactory = GeneralUtility::makeInstance(Tsfe::class);
        $tsfeToUseForTypoScriptConfiguration = $tsfeFactory->getTsfeByPageIdAndLanguageFallbackChain($typo3Site->getRootPageId(), ...$availableLanguageIds);
        if (!$tsfeToUseForTypoScriptConfiguration instanceof TypoScriptFrontendController) {
            return null;
        }

        $meilisearchConnectionConfigurations = [];

        foreach ($availableLanguageIds as $languageUid) {
            $meilisearchConnection = SiteUtility::getMeilisearchConnectionConfiguration($typo3Site, $languageUid);
            if ($meilisearchConnection !== null) {
                $meilisearchConnectionConfigurations[$languageUid] = $meilisearchConnection;
            }
        }

        $meilisearchConfiguration = $this->frontendEnvironment->getMeilisearchConfigurationFromPageId(
            $rootPageRecord['uid'],
            $tsfeToUseForTypoScriptConfiguration->getLanguage()->getLanguageId()
        );

        return GeneralUtility::makeInstance(
            Site::class,
            $meilisearchConfiguration,
            $rootPageRecord,
            $domain,
            $siteHash,
            $pageRepository,
            $defaultLanguage,
            $availableLanguageIds,
            $meilisearchConnectionConfigurations,
            $typo3Site
        );
    }

    /**
     * Returns {@link CoreSite}.
     */
    protected function getTypo3Site(int $pageUid): ?CoreSite
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageUid);
        } catch (Throwable) {
        }
        return null;
    }
}
