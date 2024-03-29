<?php

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

namespace WapplerSystems\Meilisearch\Domain\Search\Query\ParameterBuilder;

use WapplerSystems\Meilisearch\Domain\Search\Query\AbstractQueryBuilder;
use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;

/**
 * The Spellchecking ParameterProvider is responsible to build the meilisearch query parameters
 * that are needed for the spellchecking.
 */
class Spellchecking extends AbstractDeactivatable implements ParameterBuilderInterface
{
    protected int $maxCollationTries = 0;

    /**
     * Spellchecking constructor.
     */
    public function __construct(
        bool $isEnabled = false,
        int $maxCollationTries = 0
    ) {
        $this->isEnabled = $isEnabled;
        $this->maxCollationTries = $maxCollationTries;
    }

    public function getMaxCollationTries(): int
    {
        return $this->maxCollationTries;
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $meilisearchConfiguration): Spellchecking
    {
        $isEnabled = $meilisearchConfiguration->getSearchSpellchecking();
        if (!$isEnabled) {
            return new Spellchecking(false);
        }

        $maxCollationTries = $meilisearchConfiguration->getSearchSpellcheckingNumberOfSuggestionsToTry();

        return new Spellchecking(true, $maxCollationTries);
    }

    public static function getEmpty(): Spellchecking
    {
        return new Spellchecking(false);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            $query->removeComponent($query->getSpellcheck());
            return $parentBuilder;
        }

        $query->getSpellcheck()->setMaxCollationTries($this->getMaxCollationTries());
        $query->getSpellcheck()->setCollate(true);
        return $parentBuilder;
    }
}
