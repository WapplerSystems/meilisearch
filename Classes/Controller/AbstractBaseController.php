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

namespace WapplerSystems\Meilisearch\Controller;

use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSetService;
use WapplerSystems\Meilisearch\Domain\Search\SearchRequestBuilder;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\Search;
use WapplerSystems\Meilisearch\System\Configuration\ConfigurationManager as MeilisearchConfigurationManager;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Service\ConfigurationService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class AbstractBaseController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractBaseController extends ActionController
{
    /**
     * The HTTP message for 503 error from Apache Meilisearch server.
     */
    protected const STATUS_503_MESSAGE = 'Apache Meilisearch Server is not available.';

    private ?ContentObjectRenderer $contentObjectRenderer = null;

    protected ?TypoScriptFrontendController $typoScriptFrontendController = null;

    private ?MeilisearchConfigurationManager $meilisearchConfigurationManager = null;

    /**
     * The configuration is private if you need it please get it from the MeilisearchVariableProvider of RenderingContext.
     */
    protected ?TypoScriptConfiguration $typoScriptConfiguration = null;

    protected ?SearchResultSetService $searchService = null;

    protected ?SearchRequestBuilder $searchRequestBuilder = null;

    protected bool $resetConfigurationBeforeInitialize = true;

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
        // @extensionScannerIgnoreLine
        $this->contentObjectRenderer = $this->configurationManager->getContentObject();
        $this->arguments = GeneralUtility::makeInstance(Arguments::class);
    }

    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    public function getContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->contentObjectRenderer;
    }

    public function injectMeilisearchConfigurationManager(MeilisearchConfigurationManager $configurationManager): void
    {
        $this->meilisearchConfigurationManager = $configurationManager;
    }

    public function setResetConfigurationBeforeInitialize(bool $resetConfigurationBeforeInitialize): void
    {
        $this->resetConfigurationBeforeInitialize = $resetConfigurationBeforeInitialize;
    }

    /**
     * Initialize action
     */
    protected function initializeAction(): void
    {
        // Reset configuration (to reset flexform overrides) if resetting is enabled
        if ($this->resetConfigurationBeforeInitialize) {
            $this->meilisearchConfigurationManager->reset();
        }
        /** @var TypoScriptService $typoScriptService */
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);

        // Merge settings done by typoscript with meilisearchConfiguration plugin.tx_meilisearch (obsolete when part of ext:meilisearch)
        $frameWorkConfiguration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $pluginSettings = [];
        foreach (['search', 'settings', 'suggest', 'statistics', 'logging', 'general', 'meilisearch', 'view'] as $key) {
            if (isset($frameWorkConfiguration[$key])) {
                $pluginSettings[$key] = $frameWorkConfiguration[$key];
            }
        }

        $this->typoScriptConfiguration = $this->meilisearchConfigurationManager->getTypoScriptConfiguration();
        if ($pluginSettings !== []) {
            $this->typoScriptConfiguration->mergeMeilisearchConfiguration(
                $typoScriptService->convertPlainArrayToTypoScriptArray($pluginSettings),
                true,
                false
            );
        }

        if (!empty($this->contentObjectRenderer->data['pi_flexform'])) {
            GeneralUtility::makeInstance(ConfigurationService::class)
                ->overrideConfigurationWithFlexFormSettings(
                    $this->contentObjectRenderer->data['pi_flexform'],
                    $this->typoScriptConfiguration
                );
        }

        parent::initializeAction();
        $this->typoScriptFrontendController = $GLOBALS['TSFE'];
        $this->initializeSettings();

        if ($this->actionMethodName !== 'meilisearchNotAvailableAction') {
            $this->initializeSearch();
        }
    }

    /**
     * Inject settings of plugin.tx_meilisearch
     */
    protected function initializeSettings(): void
    {
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);

        // Make sure plugin.tx_meilisearch.settings are available in the view as {settings}
        $this->settings = $typoScriptService->convertTypoScriptArrayToPlainArray(
            $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_meilisearch.settings.')
        );
    }

    /**
     * Initialize the Meilisearch connection and
     * test the connection through a ping
     */
    protected function initializeSearch(): void
    {
        try {
            $meilisearchConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByTypo3Site(
                $this->typoScriptFrontendController->getSite(),
                $this->typoScriptFrontendController->getLanguage()->getLanguageId()
            );

            $search = GeneralUtility::makeInstance(Search::class, $meilisearchConnection);

            $this->searchService = GeneralUtility::makeInstance(
                SearchResultSetService::class,
                $this->typoScriptConfiguration,
                $search
            );
        } catch (NoMeilisearchConnectionFoundException) {
            $this->logMeilisearchUnavailable();
        }
    }

    protected function getSearchRequestBuilder(): SearchRequestBuilder
    {
        if ($this->searchRequestBuilder === null) {
            $this->searchRequestBuilder = GeneralUtility::makeInstance(SearchRequestBuilder::class, $this->typoScriptConfiguration);
        }

        return $this->searchRequestBuilder;
    }

    /**
     * Called when the meilisearch server is unavailable.
     */
    protected function logMeilisearchUnavailable(): void
    {
        if ($this->typoScriptConfiguration->getLoggingExceptions()) {
            $logger = GeneralUtility::makeInstance(MeilisearchLogManager::class, __CLASS__);
            $logger->error('Meilisearch server is not available');
        }
    }
}
