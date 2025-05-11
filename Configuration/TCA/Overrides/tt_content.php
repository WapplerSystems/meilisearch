<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die('Access denied.');


// Add tt_content search group
$GLOBALS['TCA']['tt_content']['columns']['CType']['config']['itemGroups']['search'] = 'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang.xlf:plugin_results';

// Register the plugins
$pluginSearchSignature = ExtensionUtility::registerPlugin(
    't3_meilisearch',
    'search',
    'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_search',
    'extensions-t3meilisearch-plugin-contentelement',
    'search'
);
$GLOBALS['TCA']['tt_content']['types'][$pluginSearchSignature]['showitem'] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:t3_meilisearch/Configuration/FlexForms/Form.xml',
    $pluginSearchSignature
);

$pluginFrequentlySearchedSignature = ExtensionUtility::registerPlugin(
    't3_meilisearch',
    'frequentlySearched',
    'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_frequentsearches',
    'extensions-t3meilisearch-plugin-contentelement',
    'search'
);
$GLOBALS['TCA']['tt_content']['types'][$pluginFrequentlySearchedSignature]['showitem'] = '';

$pluginResultsSignature = ExtensionUtility::registerPlugin(
    't3_meilisearch',
    'results',
    'LLL:EXT:t3_meilisearch/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_results',
    'extensions-t3meilisearch-plugin-contentelement',
    'search'
);
$GLOBALS['TCA']['tt_content']['types'][$pluginResultsSignature]['showitem'] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:t3_meilisearch/Configuration/FlexForms/Results.xml',
    $pluginResultsSignature
);


