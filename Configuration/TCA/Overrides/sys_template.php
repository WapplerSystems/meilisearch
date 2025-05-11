<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die('Access denied.');

// TypoScript
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Meilisearch/',
    'Search - Base Configuration'
);

// StyleSheets
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/BootstrapCss/',
    'Search - Bootstrap CSS Framework'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/StyleSheets/',
    'Search - Default Stylesheets'
);

// OpenSearch
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/OpenSearch/',
    'Search - OpenSearch'
);

// Extension Pre-Configuration
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/IndexQueueNews/',
    'Search - Index Queue Configuration for news'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/IndexQueueNewsContentElements/',
    'Search - Index Queue Configuration for news with content elements'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/IndexQueueTtNews/',
    'Search - Index Queue Configuration for tt_news'
);

// Examples
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/BoostQueries/',
    'Search - (Example) Boost more recent results'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/EverythingOn/',
    'Search - (Example) Everything On'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/FilterPages/',
    'Search - (Example) Filter to only show page results'
);

ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Suggest/',
    'Search - (Example) Suggest/autocomplete with jquery'
);

ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/Options/',
    'Search - (Example) Options facet on author field'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/OptionsToggle/',
    'Search - (Example) Options with on/off toggle'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/OptionsPrefixGrouped/',
    'Search - (Example) Options grouped by prefix'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/OptionsSinglemode/',
    'Search - (Example) Options with singlemode (only one option at a time)'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/OptionsFiltered/',
    'Search - (Example) Options filterable by option value'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/QueryGroup/',
    'Search - (Example) QueryGroup facet on the created field'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/Hierarchy/',
    'Search - (Example) Hierarchy facet on the rootline field'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/DateRange/',
    'Search - (Example) DateRange facet with jquery ui datepicker on created field'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Facets/NumericRange/',
    'Search - (Example) NumericRange facet with jquery ui slider on pid field'
);
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/Ajaxify/',
    'Search - Ajaxify the search results with jQuery'
);

// Meilisearch Fluid Grouping Examples
ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/TypeFieldGroup/',
    'Search - (Example) Fieldgroup on type field'
);

ExtensionManagementUtility::addStaticFile(
    't3_meilisearch',
    'Configuration/TypoScript/Examples/PidQueryGroup/',
    'Search - (Example) Querygroup on pid field'
);
