<?php

$EM_CONF['t3_meilisearch'] = [
    'title' => 'Meilisearch for TYPO3',
    'description' => '',
    'version' => '13.0.0',
    'state' => 'stable',
    'category' => 'plugin',
    'author' => 'Sven Wappler, Ingo Renner, Timo Hund, Markus Friedrich',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'scheduler' => '',
            'typo3' => '13.4.3-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'classmap' => [
            'Resources/Private/Php/',
        ],
        'psr-4' => [
            'WapplerSystems\\Meilisearch\\' => 'Classes/',
        ],
    ],
];
