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

use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Represents all information for EXT:meilisearch of a given TYPO3 Site to retrieve
 * configuration and setup from a page.
 *
 * (c) 2011-2015 Ingo Renner <ingo@typo3.org>
 */
class Site
{
    protected TypoScriptConfiguration $configuration;

    protected array $rootPageRecord = [];

    protected string $domain = '';

    protected string $siteHash = '';

    protected PagesRepository $pagesRepository;

    protected int $defaultLanguageId = 0;

    /**
     * @var int[] Available language ids
     */
    protected array $availableLanguageIds = [];

    protected ?Typo3Site $typo3SiteObject = null;

    protected array $meilisearchConnectionConfigurations = [];

    protected array $freeContentModeLanguages = [];

    /**
     * Constructor of Site
     *
     * @todo Use dependency injection instead.
     */
    public function __construct(
        TypoScriptConfiguration $configuration,
        array $page,
        string $domain,
        string $siteHash,
        PagesRepository $pagesRepository = null,
        int $defaultLanguageId = 0,
        array $availableLanguageIds = [],
        array $meilisearchConnectionConfigurations = [],
        Typo3Site $typo3SiteObject = null,
    ) {
        $this->configuration = $configuration;
        $this->rootPageRecord = $page;
        $this->domain = $domain;
        $this->siteHash = $siteHash;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->defaultLanguageId = $defaultLanguageId;
        $this->availableLanguageIds = $availableLanguageIds;
        $this->meilisearchConnectionConfigurations = $meilisearchConnectionConfigurations;
        $this->typo3SiteObject = $typo3SiteObject;
    }

    /**
     * Returns Meilisearch connection configurations indexed by language id.
     *
     * @throws NoMeilisearchConnectionFoundException
     */
    public function getMeilisearchConnectionConfiguration(int $language = 0): array
    {
        if (!is_array($this->meilisearchConnectionConfigurations[$language] ?? null)) {
            /** @var NoMeilisearchConnectionFoundException $noMeilisearchConnectionException */
            $noMeilisearchConnectionException = GeneralUtility::makeInstance(
                NoMeilisearchConnectionFoundException::class,
                'Could not find a Meilisearch connection for root page [' . $this->getRootPageId() . '] and language [' . $language . '].',
                1552491117
            );
            $noMeilisearchConnectionException->setRootPageId($this->getRootPageId());
            $noMeilisearchConnectionException->setLanguageId($language);

            throw $noMeilisearchConnectionException;
        }

        return $this->meilisearchConnectionConfigurations[$language];
    }

    public function getTypo3SiteObject(): Typo3Site
    {
        return $this->typo3SiteObject;
    }

    /**
     * Checks if current TYPO3 site has free content mode languages
     */
    public function hasFreeContentModeLanguages(): bool
    {
        return !empty($this->getFreeContentModeLanguages());
    }

    /**
     * Return all free content mode languages.
     *
     * Note: There is no "fallback type" nor "fallbacks" for default language 0
     *       See "displayCond" on https://github.com/TYPO3/typo3/blob/1394a4cff5369df3f835dae254b3d4ada2f83c7b/typo3/sysext/backend/Configuration/SiteConfiguration/site_language.php#L403-L416
     *         or https://review.typo3.org/c/Packages/TYPO3.CMS/+/56505/ for more information.
     */
    public function getFreeContentModeLanguages(): array
    {
        if (!empty($this->freeContentModeLanguages)) {
            return $this->freeContentModeLanguages;
        }

        if (!$this->typo3SiteObject instanceof Typo3Site) {
            return [];
        }

        foreach ($this->availableLanguageIds as $languageId) {
            if ($languageId > 0 && $this->typo3SiteObject->getLanguageById($languageId)->getFallbackType() === 'free') {
                $this->freeContentModeLanguages[$languageId] = $languageId;
            }
        }
        return $this->freeContentModeLanguages;
    }

    /**
     * Gets the site's root page ID (uid).
     */
    public function getRootPageId(): int
    {
        return (int)$this->rootPageRecord['uid'];
    }

    /**
     * Gets available language id's for this site
     */
    public function getAvailableLanguageIds(): array
    {
        return $this->availableLanguageIds;
    }

    /**
     * Gets the site's label. The label is build from the site title and root page ID (uid).
     */
    public function getLabel(): string
    {
        $rootlineTitles = [];
        $rootLine = BackendUtility::BEgetRootLine($this->rootPageRecord['uid']);
        // Remove last
        array_pop($rootLine);
        $rootLine = array_reverse($rootLine);
        foreach ($rootLine as $rootLineItem) {
            $rootlineTitles[] = $rootLineItem['title'];
        }
        return implode(' - ', $rootlineTitles) . ', Root Page ID: ' . $this->rootPageRecord['uid'];
    }

    /**
     * Gets the site's Meilisearch TypoScript configuration (plugin.tx_meilisearch.*)
     *
     * Purpose: Interface and Unit test mocking helper method.
     */
    public function getMeilisearchConfiguration(): TypoScriptConfiguration
    {
        return $this->configuration;
    }

    /**
     * Gets the site's default language as configured in
     * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
     * be the default.
     */
    public function getDefaultLanguageId(): int
    {
        return $this->defaultLanguageId;
    }

    /**
     * Generates a list of page IDs in this site and returns it.
     *
     * Attentions:
     * * This includes all page types!
     * * Deleted pages are not included.
     * * Uses the root page, if $pageId is not given
     * * Includes the given $pageId
     *
     * @param int|null $pageId Page ID from where to start collection sub-pages. Uses and includes the root page if none given.
     * @param string|null $indexQueueConfigurationName The name of index queue.
     *
     * @return array Array of pages (IDs) in this site
     */
    public function getPages(
        ?int $pageId = null,
        ?string $indexQueueConfigurationName = null
    ): array {
        $pageId = $pageId ?? (int)$this->rootPageRecord['uid'];

        $initialPagesAdditionalWhereClause = '';
        // Fetch configuration in order to be able to read initialPagesAdditionalWhereClause
        if ($indexQueueConfigurationName !== null) {
            $meilisearchConfiguration = $this->getMeilisearchConfiguration();
            $initialPagesAdditionalWhereClause = $meilisearchConfiguration->getInitialPagesAdditionalWhereClause($indexQueueConfigurationName);
        }
        return $this->pagesRepository->findAllSubPageIdsByRootPage($pageId, $initialPagesAdditionalWhereClause);
    }

    /**
     * Generates the site's unique Site Hash.
     *
     * The Site Hash is build from the site's main domain, the system encryption
     * key, and the extension "tx_meilisearch". These components are concatenated and
     * sha1-hashed.
     */
    public function getSiteHash(): string
    {
        return $this->siteHash;
    }

    /**
     * Gets the site's main domain.
     *
     * @return string The site's main domain.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Gets the site's root page record.
     *
     * @return array The site's root page.
     */
    public function getRootPageRecord(): array
    {
        return $this->rootPageRecord;
    }

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle(): string
    {
        return $this->rootPageRecord['title'] ?? '';
    }

    /**
     * Returns all Meilisearch connection configurations.
     */
    public function getAllMeilisearchConnectionConfigurations(): array
    {
        $configs = [];
        foreach ($this->getAvailableLanguageIds() as $languageId) {
            try {
                $configs[$languageId] = $this->getMeilisearchConnectionConfiguration($languageId);
            } catch (NoMeilisearchConnectionFoundException) {
            }
        }
        return $configs;
    }

    /**
     * Returns the EXT:meilisearch state configured in site.
     */
    public function isEnabled(): bool
    {
        return !empty($this->getAllMeilisearchConnectionConfigurations());
    }
}
