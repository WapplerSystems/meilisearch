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

namespace WapplerSystems\Meilisearch\System\UserFunctions;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\System\Configuration\ExtensionConfiguration;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use function str_starts_with;

/**
 * This class contains all user functions for flexforms.
 *
 * @author Daniel Siepmann <coding@daniel-siepmann.de>
 */
class FlexFormUserFunctions
{
    /**
     * Provides all facet fields for a flexform select, enabling the editor to select one of them.
     *
     * @throws NoMeilisearchConnectionFoundException
     * @throws DBALException
     */
    public function getFacetFieldsFromSchema(array &$parentInformation): void
    {
        $pageRecord = $parentInformation['flexParentDatabaseRow'];
        // @todo: Fix type hinting issue properly on whole call chain.
        $configuredFacets = $this->getConfiguredFacetsForPage($pageRecord['pid'] ?? null);

        if (!is_array($pageRecord)) {
            $parentInformation['items'] = [];
            return;
        }

        $newItems = $this->getParsedMeilisearchFieldsFromSchema($configuredFacets, $pageRecord);
        $parentInformation['items'] = $newItems;
    }

    /**
     * This method parses the meilisearch schema fields into the required format for the backend flexform.
     *
     * @throws NoMeilisearchConnectionFoundException
     * @throws DBALException
     */
    protected function getParsedMeilisearchFieldsFromSchema(array $configuredFacets, array $pageRecord): array
    {
        $newItems = [];

        array_map(function ($fieldName) use (&$newItems, $configuredFacets) {
            $value = $fieldName;
            $label = $fieldName;

            $facetNameFilter = static function ($facet) use ($fieldName) {
                return $facet['field'] === $fieldName;
            };
            $configuredFacets = array_filter($configuredFacets, $facetNameFilter);
            if (!empty($configuredFacets)) {
                $configuredFacet = array_values($configuredFacets);
                $label = $configuredFacet[0]['label'];
                // try to translate LLL: label or leave it unchanged
                if (str_starts_with($label, 'LLL:') && $this->getTranslation($label) != '') {
                    $label = $this->getTranslation($label);
                } elseif (!str_starts_with($label, 'LLL:') && ($configuredFacet[0]['label.'] ?? null)) {
                    $label = sprintf('cObject[...faceting.facets.%slabel]', array_keys($configuredFacets)[0]);
                }
                $label = sprintf('%s (Facet Label: "%s")', $value, $label);
            }

            $newItems[$value] = [$label, $value];
        }, $this->getFieldNamesFromMeilisearchMetaDataForPage($pageRecord));

        ksort($newItems, SORT_NATURAL);
        return $newItems;
    }

    /**
     * Retrieves the configured facets for a page.
     */
    protected function getConfiguredFacetsForPage(int $pid = null): ?array
    {
        if ($pid === null) {
            return null;
        }
        $typoScriptConfiguration = $this->getConfigurationFromPageId($pid);
        return $typoScriptConfiguration->getSearchFacetingFacets();
    }

    /**
     * Retrieves the translation with the LocalizationUtility.
     */
    protected function getTranslation(string $label): ?string
    {
        return LocalizationUtility::translate($label);
    }

    /**
     * Get meilisearch connection.
     *
     * @throws NoMeilisearchConnectionFoundException
     * @throws DBALException
     */
    protected function getConnection(array $pageRecord): MeilisearchConnection
    {
        return GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageRecord['pid'], $pageRecord['sys_language_uid']);
    }

    /**
     * Retrieves all fieldnames that occurs in the meilisearch schema for one page.
     *
     * @throws NoMeilisearchConnectionFoundException
     * @throws DBALException
     */
    protected function getFieldNamesFromMeilisearchMetaDataForPage(array $pageRecord): array
    {
        return array_keys((array)$this->getConnection($pageRecord)->getService()->getFieldsMetaData());
    }

    /**
     * Enriches the parents information with information from template
     */
    public function getAvailableTemplates(array &$parentInformation): void
    {
        $pageRecord = $parentInformation['flexParentDatabaseRow'];
        if (!is_array($pageRecord) || !isset($pageRecord['pid'])) {
            $parentInformation['items'] = [];
            return;
        }

        $pageId = $pageRecord['pid'];

        $templateKey = $this->getTypoScriptTemplateKeyFromFieldName($parentInformation);
        $availableTemplate = $this->getAvailableTemplateFromTypoScriptConfiguration($pageId, $templateKey);
        $newItems = $this->buildSelectItemsFromAvailableTemplate($availableTemplate);

        $parentInformation['items'] = $newItems;
    }

    /**
     * Enriches the parent information with available/configured plugin namespaces.
     *
     * @noinspection PhpUnused Used in {@link Configuration/FlexForms/Form.xml}
     */
    public function getAvailablePluginNamespaces(array &$parentInformation): void
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $namespaces = [];
        foreach ($extensionConfiguration->getAvailablePluginNamespaces() as $namespace) {
            $label = $namespace === 'tx_meilisearch' ? 'Default' : $namespace;
            $namespaces[$namespace] = [$label, $namespace];
        }
        $parentInformation['items'] = $namespaces;
    }

    /**
     * Returns the template key from field name
     */
    protected function getTypoScriptTemplateKeyFromFieldName(array $parentInformation): array|string
    {
        $field = $parentInformation['field'];
        return str_replace('view.templateFiles.', '', $field);
    }

    /**
     * Returns TypoScriptConfiguration if is available or can be resolved for given pid
     */
    protected function getConfigurationFromPageId(int $pid = null): ?TypoScriptConfiguration
    {
        if ($pid === null) {
            return null;
        }

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        $typoScript = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

        return GeneralUtility::makeInstance(TypoScriptConfiguration::class, $typoScript);
    }

    /**
     * Retrieves the configured templates from TypoScript.
     */
    protected function getAvailableTemplateFromTypoScriptConfiguration(int $pageId, string $templateKey): array
    {
        $configuration = $this->getConfigurationFromPageId($pageId);
        return $configuration->getAvailableTemplatesByFileKey($templateKey);
    }

    /**
     * Returns the available templates as needed for the flexform.
     */
    protected function buildSelectItemsFromAvailableTemplate(array $availableTemplates): array
    {
        $newItems = [];
        $newItems['Use Default'] = ['Use Default', null];
        foreach ($availableTemplates as $availableTemplate) {
            $label = $availableTemplate['label'] ?? '';
            $value = $availableTemplate['file'] ?? '';
            $newItems[$label] = [$label, $value];
        }

        return $newItems;
    }
}
