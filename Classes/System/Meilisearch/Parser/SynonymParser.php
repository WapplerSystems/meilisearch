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

namespace WapplerSystems\Meilisearch\System\Meilisearch\Parser;

/**
 * Class to parse the synonyms from a meilisearch response.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SynonymParser
{
    /**
     * Parse the meilisearch synonyms response from a json string to an array.
     */
    public function parseJson(string $baseWord, string $jsonString): array
    {
        $decodedResponse = json_decode($jsonString);
        $synonyms = [];
        if (!empty($baseWord)) {
            if (is_array($decodedResponse->{$baseWord})) {
                $synonyms = $decodedResponse->{$baseWord};
            }
        } elseif (isset($decodedResponse->synonymMappings->managedMap)) {
            $synonyms = (array)$decodedResponse->synonymMappings->managedMap;
        }

        return $synonyms;
    }

    /**
     * Converts base-word and its synonyms to JSON string
     */
    public function toJson(string $baseWord, array $synonyms): string
    {
        return json_encode([$baseWord => $synonyms]);
    }
}
