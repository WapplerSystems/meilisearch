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

namespace WapplerSystems\Meilisearch\System\Util;

use WapplerSystems\Meilisearch\Domain\Site\Site as ExtMeilisearchSite;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains related functions for the new site management that was introduced with TYPO3 9.
 */
class SiteUtility
{
    /**
     * In memory cache indexed by [<root-page-id>][<language-id>]
     */
    private static array $languages = [];

    /**
     * @internal for unit tests tear down methods.
     */
    public static function reset(): void
    {
        self::$languages = [];
    }

    /**
     * Determines if the site where the page belongs to is managed with the TYPO3 site management.
     */
    public static function getIsSiteManagedSite(int $pageId): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            /** @var SiteFinder $siteFinder */
            return $siteFinder->getSiteByPageId($pageId) instanceof CoreSite;
        } catch (SiteNotFoundException) {
        }
        return false;
    }

    /**
     * This method is used to retrieve the connection configuration from the TYPO3 site configuration.
     *
     * Note: Language context properties have precedence over global settings.
     *
     * The configuration is done in the globals configuration of a site, and be extended in the language specific configuration
     * of a site.
     *
     * Typically, everything except the core name is configured on the global level and the core name differs for each language.
     *
     * In addition, every property can be defined for the ```read``` and ```write``` scope.
     *
     * The convention for property keys is "meilisearch_{propertyName}_{scope}". With the configuration "meilisearch_host" you define the host
     * for the meilisearch read connection.
     */
    public static function getConnectionProperty(
        CoreSite $typo3Site,
        string $property,
        int $languageId,
        mixed $defaultValue = null,
    ): string|int|bool|null {
        $value = self::getConnectionPropertyOrFallback($typo3Site, $property, $languageId);
        if ($value === null) {
            return $defaultValue;
        }
        return $value;
    }

    /**
     * Builds the Meilisearch connection configuration
     */
    public static function getMeilisearchConnectionConfiguration(
        CoreSite $typo3Site,
        int $languageUid,
    ): ?array {
        $meilisearchEnabled = self::getConnectionProperty($typo3Site, 'enabled', $languageUid, false);

        $rootPageUid = $typo3Site->getRootPageId();
        return [
            'connectionKey' => $rootPageUid . '|' . $languageUid,
            'rootPageUid' => $rootPageUid,
            'scheme' => self::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'http'),
            'host' => self::getConnectionProperty($typo3Site, 'host', $languageUid, 'localhost'),
            'port' => (int)self::getConnectionProperty($typo3Site, 'port', $languageUid,  7700),
            'path' => self::getConnectionProperty($typo3Site, 'path', $languageUid,  ''),
            'masterKey' => self::getConnectionProperty($typo3Site, 'masterKey', $languageUid,  ''),

            'language' => $languageUid,
        ];
    }

    /**
     * Builds the Meilisearch connection configuration for all languages of given TYPO3 site
     */
    public static function getAllMeilisearchConnectionConfigurations(
        CoreSite $typo3Site,
    ): array {
        $connections = [];
        foreach ($typo3Site->getLanguages() as $language) {
            $connection = self::getMeilisearchConnectionConfiguration($typo3Site, $language->getLanguageId());
            if ($connection !== null) {
                $connections[$language->getLanguageId()] = $connection;
            }
        }

        return $connections;
    }

    /**
     * Resolves site configuration properties.
     * Language context properties have precedence over global settings.
     */
    protected static function getConnectionPropertyOrFallback(
        CoreSite $typo3Site,
        string $property,
        int $languageId
    ): string|int|bool|null {

        // convention key meilisearch_$property_$scope
        $keyToCheck = 'meilisearch_' . $property;

        // try to find language specific setting if found return it
        $rootPageUid = $typo3Site->getRootPageId();
        if (!isset(self::$languages[$rootPageUid][$languageId])) {
            self::$languages[$rootPageUid][$languageId] = $typo3Site->getLanguageById($languageId)->toArray();
        }
        $value = self::getValueOrFallback(self::$languages[$rootPageUid][$languageId], $keyToCheck);
        if ($value !== null) {
            return $value;
        }

        // if not found check global configuration
        $siteBaseConfiguration = $typo3Site->getConfiguration();
        return self::getValueOrFallback($siteBaseConfiguration, $keyToCheck);
    }

    /**
     * Checks whether write connection is enabled.
     * Language context properties have precedence over global settings.
     */
    protected static function isWriteConnectionEnabled(
        CoreSite $typo3Site,
        int $languageId,
    ): bool {
        $rootPageUid = $typo3Site->getRootPageId();
        if (!isset(self::$languages[$rootPageUid][$languageId])) {
            self::$languages[$rootPageUid][$languageId] = $typo3Site->getLanguageById($languageId)->toArray();
        }
        $value = self::getValueOrFallback(self::$languages[$rootPageUid][$languageId], 'meilisearch_use_write_connection', 'meilisearch_use_write_connection');
        if ($value !== null) {
            return $value;
        }

        $siteBaseConfiguration = $typo3Site->getConfiguration();
        $value = self::getValueOrFallback($siteBaseConfiguration, 'meilisearch_use_write_connection', 'meilisearch_use_write_connection');
        if ($value !== null) {
            return $value;
        }
        return false;
    }

    /**
     * Returns value of data by key or by fallback key if exists or null if not.
     */
    protected static function getValueOrFallback(
        array $data,
        string $keyToCheck
    ): string|int|bool|null {
        $value = $data[$keyToCheck] ?? null;
        if ($value === '0' || $value === 0 || !empty($value)) {
            return self::evaluateConfigurationData($value);
        }
        return null;
    }

    /**
     * Evaluate configuration data
     *
     * Setting boolean values via environment variables
     * results in strings like 'false' that may be misinterpreted
     * thus we check for boolean values in strings.
     */
    protected static function evaluateConfigurationData(string|bool|null $value): string|int|bool|null
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    /**
     * Takes a page record and checks whether the page is marked as root page.
     */
    public static function isRootPage(array $pageRecord): bool
    {
        return ($pageRecord['is_siteroot'] ?? null) == 1;
    }

    /**
     * Retrieves the rootPageIds as an array from a set of sites.
     *
     * @param CoreSite[]|ExtMeilisearchSite[] $sites
     * @return int[]
     */
    public static function getRootPageIdsFromSites(array $sites): array
    {
        $rootPageIds = [];
        foreach ($sites as $site) {
            $rootPageIds[] = $site->getRootPageId();
        }

        return $rootPageIds;
    }
}
