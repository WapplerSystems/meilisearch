<?php
/**
 * Definitions for modules provided by EXT:t3_meilisearch
 */

use WapplerSystems\Meilisearch\Controller\Backend\Search\CoreOptimizationModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexAdministrationModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\IndexQueueModuleController;
use WapplerSystems\Meilisearch\Controller\Backend\Search\InfoModuleController;

return [
    'searchbackend' => [
        'labels' => 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extensions-t3meilisearch-module-main',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'extensionName' => 'Meilisearch',
    ],
    'searchbackend_info' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/info',
        'iconIdentifier' => 'extensions-t3meilisearch-module-info',
        'labels' => 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang_mod_info.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            InfoModuleController::class => [
                'index', 'switchSite', 'switchCore', 'documentsDetails',
            ],
        ],
    ],
    'searchbackend_coreoptimization' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/core-optimization',
        'iconIdentifier' => 'extensions-t3meilisearch-module-meilisearch-core-optimization',
        'labels' => 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang_mod_coreoptimize.xlf',
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
        'access' => 'user',
        'path' => '/module/searchbackend/index-queue',
        'iconIdentifier' => 'extensions-t3meilisearch-module-index-queue',
        'labels' => 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang_mod_indexqueue.xlf',
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
        'iconIdentifier' => 'extensions-t3meilisearch-module-index-administration',
        'labels' => 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang_mod_indexadmin.xlf',
        'extensionName' => 'Meilisearch',
        'controllerActions' => [
            IndexAdministrationModuleController::class => [
                'index', 'emptyIndex', 'clearIndexQueue', 'reloadIndexConfiguration', 'switchSite',
            ],
        ],
    ],
];
