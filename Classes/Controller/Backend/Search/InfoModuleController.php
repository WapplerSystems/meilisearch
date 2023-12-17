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

namespace WapplerSystems\Meilisearch\Controller\Backend\Search;

use WapplerSystems\Meilisearch\Api;
use WapplerSystems\Meilisearch\Domain\Search\ApacheMeilisearchDocument\Repository as ApacheMeilisearchDocumentRepository;
use WapplerSystems\Meilisearch\Domain\Search\Statistics\StatisticsRepository;
use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Validator\Path;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Info Module
 */
class InfoModuleController extends AbstractModuleController
{
    protected ApacheMeilisearchDocumentRepository $apacheMeilisearchDocumentRepository;

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->apacheMeilisearchDocumentRepository = GeneralUtility::makeInstance(ApacheMeilisearchDocumentRepository::class);
    }

    /**
     * Index action, shows an overview of the state of the Meilisearch index
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws DBALException
     *
     * @noinspection PhpUnused
     */
    public function indexAction(): ResponseInterface
    {
        $this->initializeAction();
        if ($this->selectedSite === null) {
            $this->view->assign('can_not_proceed', true);
            return $this->getModuleTemplateResponse();
        }

        $this->collectConnectionInfos();
        $this->collectStatistics();
        $this->collectIndexFieldsInfo();
        $this->collectIndexInspectorInfo();

        return $this->getModuleTemplateResponse();
    }

    /**
     * Renders the details of Apache Meilisearch documents
     *
     * @noinspection PhpUnused
     * @throws DBALException
     */
    public function documentsDetailsAction(string $type, int $uid, int $pageId, int $languageUid): ResponseInterface
    {
        $documents = $this->apacheMeilisearchDocumentRepository->findByTypeAndPidAndUidAndLanguageId($type, $uid, $pageId, $languageUid);
        $this->view->assign('documents', $documents);
        return $this->getModuleTemplateResponse();
    }

    /**
     * Checks whether the configured Meilisearch server can be reached and provides a
     * flash message according to the result of the check.
     */
    protected function collectConnectionInfos(): void
    {
        $connectedHosts = [];
        $missingHosts = [];
        $invalidPaths = [];

        /** @var Path $path */
        $path = GeneralUtility::makeInstance(Path::class);
        $connections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);

        if (empty($connections)) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        $alreadyListedConnections = [];
        foreach ($connections as $connection) {
            $coreAdmin = $connection->getAdminService();
            $coreUrl = (string)$coreAdmin;
            if (in_array($coreUrl, $alreadyListedConnections)) {
                continue;
            }
            $alreadyListedConnections[] = $coreUrl;

            if ($coreAdmin->ping()) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }

            if (!$path->isValidMeilisearchPath($coreAdmin->getCorePath())) {
                $invalidPaths[] = $coreAdmin->getCorePath();
            }
        }

        $this->view->assignMultiple([
            'site' => $this->selectedSite,
            'apiKey' => Api::getApiKey(),
            'connectedHosts' => $connectedHosts,
            'missingHosts' => $missingHosts,
            'invalidPaths' => $invalidPaths,
        ]);
    }

    /**
     * Returns the statistics
     *
     * @throws DBALException
     */
    protected function collectStatistics(): void
    {
        $frameWorkConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
            'meilisearch'
        );
        $statisticsConfig = $frameWorkConfiguration['plugin.']['tx_meilisearch.']['statistics.'] ?? [];

        $topHitsLimit = (int)($statisticsConfig['topHits.']['limit'] ?? 5);
        $noHitsLimit = (int)($statisticsConfig['noHits.']['limit'] ?? 5);

        $queriesDays = (int)($statisticsConfig['queries.']['days'] ?? 30);

        $siteRootPageId = $this->selectedSite->getRootPageId();
        /** @var StatisticsRepository $statisticsRepository */
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $this->view->assign(
            'top_search_phrases',
            $statisticsRepository->getTopKeyWordsWithHits(
                $siteRootPageId,
                (int)($statisticsConfig['topHits.']['days'] ?? 30),
                $topHitsLimit
            )
        );
        $this->view->assign(
            'top_search_phrases_without_hits',
            $statisticsRepository->getTopKeyWordsWithoutHits(
                $siteRootPageId,
                (int)($statisticsConfig['noHits.']['days'] ?? 30),
                $noHitsLimit
            )
        );
        $this->view->assign(
            'search_phrases_statistics',
            $statisticsRepository->getSearchStatistics(
                $siteRootPageId,
                $queriesDays,
                (int)($statisticsConfig['queries.']['limit'] ?? 100)
            )
        );

        $labels = [];
        $data = [];
        $chartData = $statisticsRepository->getQueriesOverTime(
            $siteRootPageId,
            $queriesDays,
            86400
        );
        foreach ($chartData as $bucket) {
            // @todo Replace deprecated strftime in php 8.1. Suppress warning for now
            $labels[] = @strftime('%x', $bucket['timestamp']);
            $data[] = (int)$bucket['numQueries'];
        }

        $this->view->assign('queriesChartLabels', json_encode($labels));
        $this->view->assign('queriesChartData', json_encode($data));
        $this->view->assign('topHitsLimit', $topHitsLimit);
        $this->view->assign('noHitsLimit', $noHitsLimit);
    }

    /**
     * Gets Luke metadata for the currently selected core and provides a list
     * of that data.
     */
    protected function collectIndexFieldsInfo(): void
    {
        $indexFieldsInfoByCorePaths = [];

        $meilisearchCoreConnections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($meilisearchCoreConnections as $meilisearchCoreConnection) {
            $coreAdmin = $meilisearchCoreConnection->getAdminService();

            $indexFieldsInfo = [
                'corePath' => $coreAdmin->getCorePath(),
            ];
            if ($coreAdmin->ping()) {
                $lukeData = $coreAdmin->getLukeMetaData();
                $limitNote = '';

                if (isset($lukeData->index->numDocs) && $lukeData->index->numDocs > 20000) {
                    $limitNote = '<em>Too many terms</em>';
                } elseif (isset($lukeData->index->numDocs)) {
                    $limitNote = 'Nothing indexed';
                    // below limit, so we can get more data
                    // Note: we use 2 since 1 fails on Ubuntu Hardy.
                    $lukeData = $coreAdmin->getLukeMetaData(2);
                }

                $fields = $this->getFields($lukeData, $limitNote);
                $coreMetrics = $this->getCoreMetrics($lukeData, $fields);

                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Apache Meilisearch server: ' . $this->selectedSite->getLabel() . ' ' . $coreAdmin->getCorePath(),
                    ContextualFeedbackSeverity::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$coreAdmin->getCorePath()] = $indexFieldsInfo;
        }
        $this->view->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }

    /**
     * Retrieves the information for the index inspector.
     *
     * @throws DBALException
     */
    protected function collectIndexInspectorInfo(): void
    {
        $meilisearchCoreConnections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);
        $documentsByCoreAndType = [];
        $alreadyListedCores = [];
        foreach ($meilisearchCoreConnections as $languageId => $meilisearchCoreConnection) {
            $coreAdmin = $meilisearchCoreConnection->getAdminService();

            // Do not list cores twice when multiple languages use the same core
            $url = (string)$coreAdmin;
            if (in_array($url, $alreadyListedCores)) {
                continue;
            }
            $alreadyListedCores[] = $url;

            $documents = $this->apacheMeilisearchDocumentRepository->findByPageIdAndByLanguageId($this->selectedPageUID, $languageId);

            $documentsByType = [];
            foreach ($documents as $document) {
                $documentsByType[$document['type']][] = $document;
            }

            $documentsByCoreAndType[$languageId]['core'] = $coreAdmin;
            $documentsByCoreAndType[$languageId]['documents'] = $documentsByType;
        }

        $this->view->assignMultiple([
            'pageId' => $this->selectedPageUID,
            'indexInspectorDocumentsByLanguageAndType' => $documentsByCoreAndType,
        ]);
    }

    /**
     * Gets field metrics.
     *
     * @param ResponseAdapter $lukeData Luke index data
     * @param string $limitNote Note to display if there are too many documents in the index to show number of terms for a field
     *
     * @return array An array of field metrics
     */
    protected function getFields(ResponseAdapter $lukeData, string $limitNote): array
    {
        $rows = [];

        $fields = (array)$lukeData->fields;
        foreach ($fields as $name => $field) {
            $rows[$name] = [
                'name' => $name,
                'type' => $field->type,
                'docs' => $field->docs ?? 0,
                'terms' => $field->distinct ?? $limitNote,
            ];
        }
        ksort($rows);

        return $rows;
    }

    /**
     * Gets general core metrics.
     *
     * @param ResponseAdapter $lukeData Luke index data
     * @param array $fields Fields metrics
     *
     * @return array An array of core metrics
     */
    protected function getCoreMetrics(ResponseAdapter $lukeData, array $fields): array
    {
        return [
            'numberOfDocuments' => $lukeData->index->numDocs ?? 0,
            'numberOfDeletedDocuments' => $lukeData->index->deletedDocs ?? 0,
            'numberOfTerms' => $lukeData->index->numTerms ?? 0,
            'numberOfFields' => count($fields),
        ];
    }
}
