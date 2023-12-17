<?php

$EM_CONF['meilisearch'] = [
    'title' => 'Meilisearch for TYPO3',
    'description' => '',
    'version' => '12.0.0',
    'state' => 'stable',
    'category' => 'plugin',
    'author' => 'Ingo Renner, Timo Hund, Markus Friedrich',
    'author_email' => 'ingo@typo3.org',
    'author_company' => 'dkd Internet Service GmbH',
    'constraints' => [
        'depends' => [
            'scheduler' => '',
            'typo3' => '12.4.3-12.4.99',
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
