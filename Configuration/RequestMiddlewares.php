<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
$requestMiddlewares = [
    'apache-meilisearch-for-typo3/page-indexer-initialization' => [
        'target' => \WapplerSystems\Meilisearch\Middleware\PageIndexerInitialization::class,
        'before' => ['typo3/cms-frontend/tsfe', 'typo3/cms-frontend/authentication'],
        'after' => ['typo3/cms-core/normalized-params-attribute'],
    ],
];

$extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \WapplerSystems\Meilisearch\System\Configuration\ExtensionConfiguration::class
);
if ($extensionConfiguration->getIsRouteEnhancerEnabled()) {
    $requestMiddlewares['wapplersystems/meilisearch-route-enhancer'] = [
        'target' => \WapplerSystems\Meilisearch\Middleware\MeilisearchRoutingMiddleware::class,
        'before' => [
            'typo3/cms-frontend/site',
        ],
    ];
}

return [
    'frontend' => $requestMiddlewares,
];
