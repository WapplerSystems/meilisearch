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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The QueryFields class holds all information for the query which fields should be used to query (Meilisearch qf parameter).
 */
class QueryFields implements ParameterBuilderInterface
{
    protected array $queryFields = [];

    /**
     * QueryFields constructor.
     */
    public function __construct(array $queryFields = [])
    {
        $this->queryFields = $queryFields;
    }

    public function set(string $fieldName, float $boost = 1.0): void
    {
        $this->queryFields[$fieldName] = $boost;
    }

    /**
     * Creates the string representation
     */
    public function toString(string $delimiter = ' '): string
    {
        $queryFieldString = '';

        foreach ($this->queryFields as $fieldName => $fieldBoost) {
            $queryFieldString .= $fieldName;

            if ($fieldBoost != 1.0) {
                $queryFieldString .= '^' . number_format($fieldBoost, 1, '.', '');
            }

            $queryFieldString .= $delimiter;
        }

        return rtrim($queryFieldString, $delimiter);
    }

    /**
     * Parses the string representation of the queryFields (e.g. content^100, title^10) to the object representation.
     */
    public static function fromString(string $queryFieldsString, string $delimiter = ','): QueryFields
    {
        $fields = GeneralUtility::trimExplode($delimiter, $queryFieldsString, true);
        $queryFields = [];

        foreach ($fields as $field) {
            $fieldNameAndBoost = explode('^', $field);

            $boost = 1.0;
            if (isset($fieldNameAndBoost[1])) {
                $boost = (float)($fieldNameAndBoost[1]);
            }

            $fieldName = $fieldNameAndBoost[0];
            $queryFields[$fieldName] = $boost;
        }

        return new QueryFields($queryFields);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $parentBuilder->getQuery()->getEDisMax()->setQueryFields($this->toString());
        return $parentBuilder;
    }
}
