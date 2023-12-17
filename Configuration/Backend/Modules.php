<?php
/**
 * Definitions for modules provided by EXT:meilisearch
 */

use WapplerSystems\Meilisearch\Controller\Backend\Search\CoreOptimizationModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexAdministrationModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexQueueModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\InfoModuleController;

return [
    'searchbackend' => [
        'labels' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extensions-meilisearch-module-main',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'extensionName' => 'Meilisearch',
    ],
    'searchbackend_info' => [
        'parent' => 'searchbackend',
        'access' => 'user,group',
        'path' => '/module/searchbackend/info',
        'iconIdentifier' => 'extensions-meilisearch-module-info',
        'labels' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_mod_info.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            InfoModuleController::class => [
                'index', 'switchSite', 'switchCore', 'documentsDetails',
            ],
        ],
    ],
    'searchbackend_coreoptimization' => [
        'parent' => 'searchbackend',
        'access' => 'user,group',
        'path' => '/module/searchbackend/core-optimization',
        'iconIdentifier' => 'extensions-meilisearch-module-meilisearch-core-optimization',
        'labels' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_mod_coreoptimize.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            CoreOptimizationModuleController::class => [
                'index',
                'addSynonyms', 'importSynonymList', 'deleteAllSynonyms', 'exportSynonyms', 'deleteSynonyms',
                'saveStopWords', 'importStopWordList', 'exportStopWords',
                'switchSite', 'switchCore',
            ],
        ],
    ],
    'searchbackend_indexqueue' => [
        'parent' => 'searchbackend',
        'access' => 'user,group',
        'path' => '/module/searchbackend/index-queue',
        'iconIdentifier' => 'extensions-meilisearch-module-index-queue',
        'labels' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_mod_indexqueue.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            IndexQueueModuleController::class => [
                'index', 'initializeIndexQueue', 'clearIndexQueue', 'requeueDocument',
                'resetLogErrors', 'showError', 'doIndexingRun', 'switchSite',
            ],
        ],
    ],
    'searchbackend_indexadministration' => [
        'parent' => 'searchbackend',
        'access' => 'user,group',
        'path' => '/module/searchbackend/index-administration',
        'iconIdentifier' => 'extensions-meilisearch-module-index-administration',
        'labels' => 'LLL:EXT:meilisearch/Resources/Private/Language/locallang_mod_indexadmin.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            IndexAdministrationModuleController::class => [
                'index', 'emptyIndex', 'clearIndexQueue', 'reloadIndexConfiguration', 'switchSite',
            ],
        ],
    ],
];
