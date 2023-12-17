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

namespace WapplerSystems\Meilisearch\Domain\Search\ResultSet\Result\Parser;

use WapplerSystems\Meilisearch\System\Configuration\TypoScriptConfiguration;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\Util;

/**
 * Applies htmlspecialschars on documents of a solr response.
 */
class DocumentEscapeService
{
    protected TypoScriptConfiguration $typoScriptConfiguration;

    /**
     * DocumentEscapeService constructor.
     */
    public function __construct(TypoScriptConfiguration $typoScriptConfiguration = null)
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getMeilisearchConfiguration();
    }

    /**
     * This method is used to apply htmlspecialchars on all document fields that
     * are not configured to be secure. Secure mean that we know where the content is coming from.
     *
     * @param Document[] $documents
     * @return Document[]
     */
    public function applyHtmlSpecialCharsOnAllFields(array $documents): array
    {
        $trustedMeilisearchFields = $this->typoScriptConfiguration->getSearchTrustedFieldsArray();

        foreach ($documents as $key => $document) {
            $fieldNames = array_keys($document->getFields() ?? []);

            foreach ($fieldNames as $fieldName) {
                if (is_array($trustedMeilisearchFields) && in_array($fieldName, $trustedMeilisearchFields)) {
                    // we skip this field, since it was marked as secure
                    continue;
                }

                $value = $this->applyHtmlSpecialCharsOnSingleFieldValue($document[$fieldName]);
                $document->setField($fieldName, $value);
            }

            $documents[$key] = $document;
        }

        return $documents;
    }

    /**
     * Applies htmlspecialchars on all items of an array of a single value.
     */
    protected function applyHtmlSpecialCharsOnSingleFieldValue(mixed $fieldValue): array|string
    {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $fieldValueItem) {
                $fieldValue[$key] = htmlspecialchars((string)$fieldValueItem, ENT_COMPAT, 'UTF-8', false);
            }
        } else {
            $fieldValue = htmlspecialchars((string)$fieldValue, ENT_COMPAT, 'UTF-8', false);
        }

        return $fieldValue;
    }
}
