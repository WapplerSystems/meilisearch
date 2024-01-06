<?php

use WapplerSystems\Meilisearch\Controller\SearchController;
use WapplerSystems\Meilisearch\Controller\SuggestController;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser\GroupedResultParser;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\SearchResult;
use WapplerSystems\Meilisearch\Domain\Search\ResultSet\SearchResultSet;
use WapplerSystems\Meilisearch\Eid\ApiEid;
use WapplerSystems\Meilisearch\GarbageCollector;
use WapplerSystems\Meilisearch\Indexer\FrontendHelper\AuthorizationService;
use WapplerSystems\Meilisearch\Indexer\FrontendHelper\Manager;
use WapplerSystems\Meilisearch\Indexer\FrontendHelper\PageIndexer;
use WapplerSystems\Meilisearch\Indexer\FrontendHelper\UserGroupDetector;
use WapplerSystems\Meilisearch\Indexer\RecordMonitor;
use WapplerSystems\Meilisearch\Routing\Enhancer\MeilisearchFacetMaskAndCombineEnhancer;
use WapplerSystems\Meilisearch\System\Configuration\ExtensionConfiguration;
use WapplerSystems\Meilisearch\Task\EventQueueWorkerTask;
use WapplerSystems\Meilisearch\Task\EventQueueWorkerTaskAdditionalFieldProvider;
use WapplerSystems\Meilisearch\Task\IndexQueueWorkerTask;
use WapplerSystems\Meilisearch\Task\IndexQueueWorkerTaskAdditionalFieldProvider;
use WapplerSystems\Meilisearch\Task\OptimizeIndexTask;
use WapplerSystems\Meilisearch\Task\OptimizeIndexTaskAdditionalFieldProvider;
use WapplerSystems\Meilisearch\Task\ReIndexTask;
use WapplerSystems\Meilisearch\Task\ReIndexTaskAdditionalFieldProvider;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask;

defined('TYPO3') or die('Access denied.');

// ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

(static function () {
    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    // Registering RecordMonitor and GarbageCollector hooks.

    // hooking into TCE Main to monitor record updates that may require deleting documents from the index
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['meilisearch/garbagecollector'] = GarbageCollector::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['meilisearch/garbagecollector'] = GarbageCollector::class;

    // hooking into TCE Main to monitor record updates that may require reindexing by the index queue
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['meilisearch/recordmonitor'] = RecordMonitor::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['meilisearch/recordmonitor'] = RecordMonitor::class;

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    // registering Index Queue page indexer helpers
    Manager::registerFrontendHelper('findUserGroups', UserGroupDetector::class);

    Manager::registerFrontendHelper('indexPage', PageIndexer::class);

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // adding scheduler tasks

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][OptimizeIndexTask::class] = [
        'extension' => 'meilisearch',
        'title' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:optimizeindex_title',
        'description' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:optimizeindex_description',
        'additionalFields' => OptimizeIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][ReIndexTask::class] = [
        'extension' => 'meilisearch',
        'title' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:reindex_title',
        'description' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:reindex_description',
        'additionalFields' => ReIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][IndexQueueWorkerTask::class] = [
        'extension' => 'meilisearch',
        'title' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
        'description' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
        'additionalFields' => IndexQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][EventQueueWorkerTask::class] = [
        'extension' => 'meilisearch',
        'title' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.title',
        'description' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.description',
        'additionalFields' => EventQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TableGarbageCollectionTask::class]['options']['tables']['tx_meilisearch_statistics'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TableGarbageCollectionTask::class]['options']['tables']['tx_meilisearch_statistics'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => 180,
        ];
    }


    // Register cache for frequent searches

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch'] = [];
    }
    // Caching framework meilisearch
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration'] = [];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['backend'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['backend'] = Typo3DatabaseBackend::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['options'])) {
        // default life-time is one day
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['options'] = ['defaultLifetime' => 60 * 60 * 24];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['groups'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_meilisearch_configuration']['groups'] = ['all'];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    /** @var ExtensionConfiguration $extensionConfiguration */
    $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

    // cacheHash handling
    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'FE' => [
                'cacheHash' => [
                    'excludedParameters' => $extensionConfiguration->getCacheHashExcludedParameters(),
                ],
            ],
        ]
    );

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultClassName '] = SearchResult::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultSetClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['searchResultSetClassName '] = SearchResultSet::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['WapplerSystems']['Meilisearch']['writerConfiguration'])) {
        $context = Environment::getContext();
        if ($context->isProduction()) {
            $logLevel = LogLevel::ERROR;
        } elseif ($context->isDevelopment()) {
            $logLevel = LogLevel::DEBUG;
        } else {
            $logLevel = LogLevel::INFO;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['WapplerSystems']['Meilisearch']['writerConfiguration'] = [
            $logLevel => [
                FileWriter::class => [
                    'logFileInfix' => 'meilisearch',
                ],
            ],
        ];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    ExtensionUtility::configurePlugin(
        'Meilisearch',
        'pi_results',
        [
            SearchController::class => 'results,form,detail',
        ],
        [
            SearchController::class => 'results',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Meilisearch',
        'pi_search',
        [
            SearchController::class => 'form',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Meilisearch',
        'pi_frequentlySearched',
        [
            SearchController::class => 'frequentlySearched',
        ],
        [
            SearchController::class => 'frequentlySearched',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Meilisearch',
        'pi_suggest',
        [
            SuggestController::class => 'suggest',
        ],
        [
            SuggestController::class => 'suggest',
        ]
    );

    // register the Fluid namespace 'meilisearch' globally
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['meilisearch'] = ['WapplerSystems\\Meilisearch\\ViewHelpers'];

    /*
     * Meilisearch route enhancer configuration
     */
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['MeilisearchFacetMaskAndCombineEnhancer'] = MeilisearchFacetMaskAndCombineEnhancer::class;

    // add meilisearch field to rootline fields
    if ($GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] === '') {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = 'no_search_sub_entries';
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',no_search_sub_entries';
    }

    /**
     * Registers an authentication service to authorize / grant the indexer to
     * access protected pages.
     */
    ExtensionManagementUtility::addService(
        'meilisearch',
        'auth',
        AuthorizationService::class,
        [// service meta data
            'title' => 'Meilisearch Indexer Authorization',
            'description' => 'Authorizes the Meilisearch Index Queue indexer to access protected pages.',
            'subtype' => 'getUserFE,authUserFE',
            'available' => true,
            'priority' => 100,
            'quality' => 100,

            'os' => '',
            'exec' => '',
            'className' => AuthorizationService::class,
        ]
    );


    // Register Meilisearch Grouping feature
    $parserRegistry = GeneralUtility::makeInstance(ResultParserRegistry::class);
    if (!$parserRegistry->hasParser(GroupedResultParser::class, 200)) {
        $parserRegistry->registerParser(GroupedResultParser::class, 200);
    }
})();

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = ExtensionManagementUtility::extPath('meilisearch');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
