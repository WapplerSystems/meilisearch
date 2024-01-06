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

use TYPO3\CMS\Core\Utility\DebugUtility;
use WapplerSystems\Meilisearch\Api;
use WapplerSystems\Meilisearch\Domain\Search\MeilisearchDocument\Repository as MeilisearchDocumentRepository;
use WapplerSystems\Meilisearch\Domain\Search\Statistics\StatisticsRepository;
use WapplerSystems\Meilisearch\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
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
    protected MeilisearchDocumentRepository $meilisearchDocumentRepository;

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->meilisearchDocumentRepository = GeneralUtility::makeInstance(MeilisearchDocumentRepository::class);
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

        return $this->getModuleTemplateResponse();
    }

    /**
     * Renders the details of Meilisearch documents
     *
     * @noinspection PhpUnused
     * @throws DBALException
     */
    public function documentsDetailsAction(string $type, int $uid, int $pageId, int $languageUid): ResponseInterface
    {
        $documents = $this->meilisearchDocumentRepository->findByTypeAndPidAndUidAndLanguageId($type, $uid, $pageId, $languageUid);
        $this->view->assign('documents', $documents);
        return $this->getModuleTemplateResponse();
    }

    /**
     * Checks whether the configured Meilisearch server can be reached and provides a
     * flash message according to the result of the check.
     */
    protected function collectConnectionInfos(): void
    {
        $connections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);

        $data = [];

        if (empty($connections)) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        foreach ($connections as $connection) {

            $service = $connection->getService();

            $key = $connection->getUrl();

            if (isset($data[$key])) {
                continue;
            }


            $data[$key] = [
                'url' => $connection->getUrl(),
                'healthy' => $service->getClient()->isHealthy(),
                'client' => $service->getClient(),
                'service' => $service,
            ];

            $data[$key]['stats'] = $service->getClient()->stats();

        }

        $this->view->assignMultiple([
            'site' => $this->selectedSite,
            'connections' => $connections,
            'data' => $data,
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
            $service = $meilisearchCoreConnection->getService();
            $client = $service->getClient();


            $indexFieldsInfo = [
            ];
            if ($client->isHealthy()) {


                continue;




                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Meilisearch server: ' . $this->selectedSite->getLabel(),
                    ContextualFeedbackSeverity::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$service->getCorePath()] = $indexFieldsInfo;
        }
        $this->view->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }


    protected function collectIndexes(): void
    {
        $indexFieldsInfoByCorePaths = [];

        $meilisearchCoreConnections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($meilisearchCoreConnections as $meilisearchCoreConnection) {
            $service = $meilisearchCoreConnection->getService();
            $client = $service->getClient();


            $indexFieldsInfo = [
            ];
            if ($client->isHealthy()) {

                $indexes = $client->getIndexes();



                continue;




                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Meilisearch server: ' . $this->selectedSite->getLabel(),
                    ContextualFeedbackSeverity::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$service->getCorePath()] = $indexFieldsInfo;
        }
        $this->view->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }


    protected function collectSettings(): void
    {
        $indexFieldsInfoByCorePaths = [];

        $meilisearchCoreConnections = $this->meilisearchConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($meilisearchCoreConnections as $meilisearchCoreConnection) {
            $service = $meilisearchCoreConnection->getService();
            $client = $service->getClient();


            $indexFieldsInfo = [
            ];
            if ($client->isHealthy()) {


                continue;




                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Meilisearch server: ' . $this->selectedSite->getLabel(),
                    ContextualFeedbackSeverity::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$service->getCorePath()] = $indexFieldsInfo;
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
            $service = $meilisearchCoreConnection->getService();

            continue;

            // Do not list cores twice when multiple languages use the same core
            $url = (string)$service;
            if (in_array($url, $alreadyListedCores)) {
                continue;
            }
            $alreadyListedCores[] = $url;

            $documents = $this->meilisearchDocumentRepository->findByPageIdAndByLanguageId($this->selectedPageUID, $languageId);

            $documentsByType = [];
            foreach ($documents as $document) {
                $documentsByType[$document['type']][] = $document;
            }

            $documentsByCoreAndType[$languageId]['core'] = $service;
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
