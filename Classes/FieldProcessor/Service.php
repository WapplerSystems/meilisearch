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

namespace WapplerSystems\Meilisearch\FieldProcessor;

use WapplerSystems\Meilisearch\Exception as ExtMeilisearchException;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service class that modifies fields in an Apache Meilisearch Document, used for
 * common field processing during indexing or resolving
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @copyright (c) 2009-2015 Daniel Poetzinger <poetzinger@aoemedia.de>
 */
class Service
{
    /**
     * Modifies a list of documents
     *
     * @param Document[] $documents
     *
     * @throws DBALException
     * @throws ExtMeilisearchException
     */
    public function processDocuments(array $documents, array $processingConfiguration): void
    {
        foreach ($documents as $document) {
            $this->processDocument($document, $processingConfiguration);
        }
    }

    /**
     * modifies a document according to the given configuration
     *
     * @throws DBALException
     * @throws ExtMeilisearchException
     */
    public function processDocument(Document $document, array $processingConfiguration): void
    {
        foreach ($processingConfiguration as $fieldName => $instruction) {
            $fieldValue = $document[$fieldName] ?? false;
            $isSingleValueField = false;

            if ($fieldValue !== false) {
                if (!is_array($fieldValue)) {
                    // turn single value field into multi value field
                    $fieldValue = [$fieldValue];
                    $isSingleValueField = true;
                }

                switch ($instruction) {
                    case 'timestampToUtcIsoDate':
                        /** @var TimestampToUtcIsoDate $processor */
                        $processor = GeneralUtility::makeInstance(TimestampToUtcIsoDate::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'timestampToIsoDate':
                        /** @var TimestampToIsoDate $processor */
                        $processor = GeneralUtility::makeInstance(TimestampToIsoDate::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'pathToHierarchy':
                        /** @var PathToHierarchy $processor */
                        $processor = GeneralUtility::makeInstance(PathToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'pageUidToHierarchy':
                        /** @var PageUidToHierarchy $processor */
                        $processor = GeneralUtility::makeInstance(PageUidToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'categoryUidToHierarchy':
                        /** @var CategoryUidToHierarchy $processor */
                        $processor = GeneralUtility::makeInstance(CategoryUidToHierarchy::class);
                        $fieldValue = $processor->process($fieldValue);
                        break;
                    case 'uppercase':
                        $fieldValue = array_map('mb_strtoupper', $fieldValue);
                        break;
                    default:
                        $classReference = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['fieldProcessor'][$instruction] ?? false;
                        if ($classReference) {
                            $customFieldProcessor = GeneralUtility::makeInstance($classReference);
                            if ($customFieldProcessor instanceof FieldProcessor) {
                                $fieldValue = $customFieldProcessor->process($fieldValue);
                            } else {
                                throw new ExtMeilisearchException('A FieldProcessor must implement the FieldProcessor interface', 1635082295);
                            }
                        } else {
                            throw new ExtMeilisearchException(sprintf('FieldProcessor %s is not implemented', $instruction), 1635082296);
                        }
                }

                if ($isSingleValueField) {
                    // turn multi value field back into single value field
                    $fieldValue = $fieldValue[0] ?? '';
                }

                $document->setField($fieldName, $fieldValue);
            }
        }
    }
}
