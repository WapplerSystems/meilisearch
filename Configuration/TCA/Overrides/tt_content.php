<?php

defined('TYPO3') or die('Access denied.');

// Register the plugins
$pluginSignature = 'meilisearch_pi_search';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'meilisearch',
    'pi_search',
    'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_search'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
    = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    $pluginSignature,
    'FILE:EXT:meilisearch/Configuration/FlexForms/Form.xml'
);

$pluginSignature = 'meilisearch_pi_frequentlysearched';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'meilisearch',
    'pi_frequentlySearched',
    'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_frequentsearches'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';

$pluginSignature = 'meilisearch_pi_results';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'meilisearch',
    'pi_results',
    'LLL:EXT:meilisearch/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_results'
);

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
    = 'pi_flexform';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    $pluginSignature,
    'FILE:EXT:meilisearch/Configuration/FlexForms/Results.xml'
);
