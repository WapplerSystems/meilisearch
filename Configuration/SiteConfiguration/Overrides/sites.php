<?php

/**
 * Global Meilisearch Connection Settings
 */
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_enabled_read'] = [
    'label' => 'Enable Meilisearch for this site',
    'onChange' => 'reload',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 1,
        'items' => [
            [
                'label' => '',
                'labelChecked' => '',
                'labelUnchecked' => '',
            ],
        ],
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_scheme_read'] = [
    'label' => 'Scheme',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'valuePicker' => [
            'items' => [
                [ 'http', 'http'],
                [ 'https', 'https'],
            ],
        ],
        'placeholder' => 'http',
    ],
    'displayCond' => 'FIELD:meilisearch_enabled_read:=:1',
];

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_host_read'] = [
    'label' => 'Host',
    'config' => [
        'type' => 'input',
        'default' => 'localhost',
        'placeholder' => 'localhost',
        'size' => 50,
    ],
    'displayCond' => 'FIELD:meilisearch_enabled_read:=:1',
];

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_port_read'] = [
    'label' => 'Port',
    'config' => [
        'type' => 'input',
        'required' => true,
        'size' => 5,
        'default' => 8983,
    ],
    'displayCond' => 'FIELD:meilisearch_enabled_read:=:1',
];

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_path_read'] = [
    'label' => 'URL path to Apache Meilisearch server',
    'description' => 'Must not contain "/meilisearch/"! Unless you have an additional "meilisearch" segment in your path like "http://localhost:8983/meilisearch/meilisearch/core_en".',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'default' => '/',
    ],
    'displayCond' => 'FIELD:meilisearch_enabled_read:=:1',
];

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_use_write_connection'] = [
    'label' => 'Use different write connection',
    'onChange' => 'reload',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
        'items' => [
            [
                'label' => '',
                'labelChecked' => '',
                'labelUnchecked' => '',
            ],
        ],
    ],
    'displayCond' => 'FIELD:meilisearch_enabled_read:=:1',
];

// write TCA
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_scheme_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_scheme_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_scheme_write']['displayCond'] = 'FIELD:meilisearch_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_port_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_port_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_port_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_port_write']['displayCond'] = 'FIELD:meilisearch_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_host_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_host_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_host_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_host_write']['displayCond'] = 'FIELD:meilisearch_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_path_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_path_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_path_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['meilisearch_path_write']['displayCond'] = 'FIELD:meilisearch_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['palettes']['meilisearch_read']['showitem'] = 'meilisearch_scheme_read, meilisearch_port_read, --linebreak--, meilisearch_host_read, meilisearch_path_read';
$GLOBALS['SiteConfiguration']['site']['palettes']['meilisearch_write']['showitem'] = 'meilisearch_scheme_write, meilisearch_port_write, --linebreak--, meilisearch_host_write, meilisearch_path_write';

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] .= ',--div--;Meilisearch,meilisearch_enabled_read,--palette--;;meilisearch_read, meilisearch_use_write_connection,--palette--;;meilisearch_write';

/**
 * Language specific core configuration
 */
$GLOBALS['SiteConfiguration']['site_language']['columns']['meilisearch_core_read'] = [
    'label' => 'Corename',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'valuePicker' => [
            'items' => [
                [ 'Arabic', 'core_ar'],
                [ 'Armenian', 'core_hy'],
                [ 'Basque', 'core_eu'],
                [ 'Brazilian Portuguese', 'core_ptbr'],
                [ 'Bulgarian', 'core_bg'],
                [ 'Burmese', 'core_my'],
                [ 'Catalan', 'core_ca'],
                [ 'Chinese', 'core_zh'],
                [ 'Czech', 'core_cs'],
                [ 'Danish', 'core_da'],
                [ 'Dutch', 'core_nl'],
                [ 'English', 'core_en'],
                [ 'Finnish', 'core_fi'],
                [ 'French', 'core_fr'],
                [ 'Galician', 'core_gl'],
                [ 'German', 'core_de'],
                [ 'Greek', 'core_el'],
                [ 'Hinde', 'core_hi'],
                [ 'Hungarian', 'core_hu'],
                [ 'Indonesian', 'core_id'],
                [ 'Irish', 'core_ie'],
                [ 'Italian', 'core_it'],
                [ 'Japanese', 'core_ja'],
                [ 'Korean', 'core_km'],
                [ 'Lao', 'core_lo'],
                [ 'Latvia', 'core_lv'],
                [ 'Norwegian', 'core_no'],
                [ 'Persian', 'core_fa'],
                [ 'Polish', 'core_pl'],
                [ 'Portuguese', 'core_pt'],
                [ 'Romanian', 'core_ro'],
                [ 'Russian', 'core_ru'],
                [ 'Serbian', 'core_rs'],
                [ 'Spanish', 'core_es'],
                [ 'Swedish', 'core_sv'],
                [ 'Thai', 'core_th'],
                [ 'Turkish', 'core_tr'],
                [ 'Ukrainian', 'core_uk'],
            ],
        ],
        'placeholder' => 'core_*',
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    'flag',
    'flag, meilisearch_core_read, ',
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem']
);
