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
 * The TrigramPhraseFields class
 */
class TrigramPhraseFields extends AbstractFieldList implements ParameterBuilderInterface
{
    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     */
    public static function fromString(string $fieldListString, string $delimiter = ','): TrigramPhraseFields
    {
        return self::initializeFromString($fieldListString, $delimiter);
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $meilisearchConfiguration): TrigramPhraseFields
    {
        $isEnabled = $meilisearchConfiguration->getTrigramPhraseSearchIsEnabled();
        if (!$isEnabled) {
            return new TrigramPhraseFields(false);
        }

        return self::fromString($meilisearchConfiguration->getSearchQueryTrigramPhraseFields());
    }

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     */
    protected static function initializeFromString(string $fieldListString, string $delimiter = ','): TrigramPhraseFields
    {
        $fieldList = self::buildFieldList($fieldListString, $delimiter);
        return new TrigramPhraseFields(true, $fieldList);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $trigramPhraseFieldsString = $this->toString();
        if ($trigramPhraseFieldsString === '' || !$this->getIsEnabled()) {
            return $parentBuilder;
        }
        $parentBuilder->getQuery()->getEDisMax()->setPhraseTrigramFields($trigramPhraseFieldsString);
        return $parentBuilder;
    }
}
