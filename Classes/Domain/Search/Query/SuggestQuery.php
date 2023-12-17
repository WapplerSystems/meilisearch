<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\Meilisearch\Domain\Search\Query;

use WapplerSystems\Meilisearch\Domain\Search\Query\ParameterBuilder\ReturnFields;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\Util;

/**
 * A query specialized to get search suggestions
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @copyright (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 */
class SuggestQuery extends Query
{
    protected array $configuration;

    protected string $prefix;

    public function __construct(string $keywords, TypoScriptConfiguration $meilisearchConfiguration = null)
    {
        parent::__construct();
        $meilisearchConfiguration = $meilisearchConfiguration ?? Util::getMeilisearchConfiguration();

        $this->setQuery($keywords);
        $this->configuration = $meilisearchConfiguration->getObjectByPathOrDefault('plugin.tx_meilisearch.suggest.');

        if (!empty($this->configuration['treatMultipleTermsAsSingleTerm'])) {
            $this->prefix = $keywords;
        } else {
            $matches = [];
            preg_match('/^(:?(.* |))([^ ]+)$/', $keywords, $matches);
            $fullKeywords = trim($matches[2] ?? '');
            $partialKeyword = trim($matches[3] ?? '');

            $this->setQuery($fullKeywords);
            $this->prefix = $partialKeyword;
        }

        $this->getEDisMax()->setQueryAlternative('*:*');
        $this->setFields(ReturnFields::fromString(($this->configuration['suggestField'] ?? ''))->getValues());
        $this->addParam('facet', 'on');
        $this->addParam('facet.prefix', $this->prefix);
        $this->addParam('facet.field', ($this->configuration['suggestField'] ?? null));
        $this->addParam('facet.limit', ($this->configuration['numberOfSuggestions'] ?? null));
        $this->addParam('facet.mincount', 1);
        $this->addParam('facet.method', 'enum');
    }
}
