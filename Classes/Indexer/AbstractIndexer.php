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

namespace WapplerSystems\Meilisearch\Indexer;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use WapplerSystems\Meilisearch\ConnectionManager;
use WapplerSystems\Meilisearch\ContentObject\Classification;
use WapplerSystems\Meilisearch\ContentObject\Multivalue;
use WapplerSystems\Meilisearch\ContentObject\Relation;
use WapplerSystems\Meilisearch\Domain\Search\MeilisearchDocument\Builder;
use WapplerSystems\Meilisearch\Domain\Site\Site;
use WapplerSystems\Meilisearch\Domain\Site\SiteRepository;
use WapplerSystems\Meilisearch\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use WapplerSystems\Meilisearch\Event\Indexing\BeforeDocumentsAreIndexedEvent;
use WapplerSystems\Meilisearch\Exception as EXTMeilisearchException;
use WapplerSystems\Meilisearch\FieldProcessor\Service;
use WapplerSystems\Meilisearch\FrontendEnvironment;
use WapplerSystems\Meilisearch\FrontendEnvironment\Exception\Exception as FrontendEnvironmentException;
use WapplerSystems\Meilisearch\FrontendEnvironment\Tsfe;
use WapplerSystems\Meilisearch\Indexer\Exception\IndexingException;
use WapplerSystems\Meilisearch\NoMeilisearchConnectionFoundException;
use WapplerSystems\Meilisearch\System\Logging\MeilisearchLogManager;
use WapplerSystems\Meilisearch\System\Records\Pages\PagesRepository;
use WapplerSystems\Meilisearch\System\Meilisearch\Document\Document;
use WapplerSystems\Meilisearch\System\Meilisearch\ResponseAdapter;
use WapplerSystems\Meilisearch\System\Meilisearch\MeilisearchConnection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * A general purpose indexer to be used for indexing of any kind of regular
 * records like news records, tt_address, and so on.
 * Specialized indexers can extend this class to handle advanced stuff like
 * category resolution in news records or file indexing.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @copyright  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 */
class AbstractIndexer
{

    use LoggerAwareTrait;

    /**
     * Holds field names that are denied to overwrite in thy indexing configuration.
     */
    protected static array $unAllowedOverrideFields = ['type'];

    /**
     * To log or not to log... #Shakespeare
     */
    protected bool $loggingEnabled = false;


    public function __construct(
        readonly PagesRepository          $pagesRepository,
        readonly Builder                  $documentBuilder,
        readonly ConnectionManager        $connectionManager,
        readonly FrontendEnvironment      $frontendEnvironment,
        readonly EventDispatcherInterface $eventDispatcher,
        readonly array                    $options = [],
    )
    {
    }



    public static function isAllowedToOverrideField(string $meilisearchFieldName): bool
    {
        return !in_array($meilisearchFieldName, static::$unAllowedOverrideFields);
    }

    /**
     * Adds fields to the document as defined in $indexingConfiguration
     *
     * @param Document $document base document to add fields to
     * @param array $indexingConfiguration Indexing configuration / mapping
     * @param array $data Record data
     * @return Document Modified document with added fields
     */
    protected function addDocumentFieldsFromTyposcript(Document $document, array $indexingConfiguration, array $data, TypoScriptFrontendController $tsfe): Document
    {
        $data = static::addVirtualContentFieldToRecord($document, $data);

        // mapping of record fields => meilisearch document fields, resolving cObj
        foreach ($indexingConfiguration as $meilisearchFieldName => $recordFieldName) {
            if (is_array($recordFieldName)) {
                // configuration for a content object, skipping
                continue;
            }

            if (!static::isAllowedToOverrideField($meilisearchFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field .' . $meilisearchFieldName,
                    1435441863
                );
            }

            $fieldValue = $this->resolveFieldValue($indexingConfiguration, $meilisearchFieldName, $data, $tsfe);
            if ($fieldValue === null
                || $fieldValue === ''
                || (is_array($fieldValue) && empty($fieldValue))
            ) {
                continue;
            }

            $document->setField($meilisearchFieldName, $fieldValue);
        }

        return $document;
    }

    /**
     * Adds the content of the field 'content' from the meilisearch document as virtual field __meilisearch_content in the record,
     * to have it available in typoscript.
     */
    public static function addVirtualContentFieldToRecord(Document $document, array $data): array
    {
        if (isset($document['content'])) {
            $data['__meilisearch_content'] = $document['content'];
            return $data;
        }
        return $data;
    }

    /**
     * Resolves a field to its value depending on its configuration.
     *
     * This enables you to configure the indexer to put the item/record through
     * cObj processing if wanted/needed. Otherwise, the plain item/record value
     * is taken.
     *
     * @param array $indexingConfiguration Indexing configuration as defined in plugin.tx_meilisearch_index.queue.[indexingConfigurationName].fields
     * @param string $meilisearchFieldName A Meilisearch field name that is configured in the indexing configuration
     * @param array $data A record or item's data
     * @return array|float|int|string|null The resolved string value to be indexed; null if value could not be resolved
     */
    protected function resolveFieldValue(
        array                        $indexingConfiguration,
        string                       $meilisearchFieldName,
        array                        $data,
        TypoScriptFrontendController $tsfe
    ): mixed
    {
        if (isset($indexingConfiguration[$meilisearchFieldName . '.'])) {
            // configuration found => need to resolve a cObj

            // need to change directory to make IMAGE content objects work in BE context
            // see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
            $backupWorkingDirectory = getcwd();
            chdir(Environment::getPublicPath() . '/');

            $tsfe->cObj->start($data, $this->type);
            $fieldValue = $tsfe->cObj->cObjGetSingle(
                $indexingConfiguration[$meilisearchFieldName],
                $indexingConfiguration[$meilisearchFieldName . '.']
            );

            chdir($backupWorkingDirectory);

            if ($this->isSerializedValue(
                $indexingConfiguration,
                $meilisearchFieldName
            )
            ) {
                $fieldValue = unserialize($fieldValue);
            }
        } elseif (
            str_starts_with($indexingConfiguration[$meilisearchFieldName], '<')
        ) {
            $referencedTsPath = trim(substr(
                $indexingConfiguration[$meilisearchFieldName],
                1
            ));
            $typoScriptParser = GeneralUtility::makeInstance(TypoScriptParser::class);
            // $name and $conf is loaded with the referenced values.
            [$name, $conf] = $typoScriptParser->getVal($referencedTsPath, $GLOBALS['TSFE']->tmpl->setup);

            // need to change directory to make IMAGE content objects work in BE context
            // see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
            $backupWorkingDirectory = getcwd();
            chdir(Environment::getPublicPath() . '/');

            $tsfe->cObj->start($data, $this->type);
            $fieldValue = $tsfe->cObj->cObjGetSingle($name, $conf);

            chdir($backupWorkingDirectory);

            if ($this->isSerializedValue(
                $indexingConfiguration,
                $meilisearchFieldName
            )
            ) {
                $fieldValue = unserialize($fieldValue);
            }
        } else {
            $indexingFieldName = $indexingConfiguration[$meilisearchFieldName] ?? null;
            if (empty($indexingFieldName) ||
                !is_string($indexingFieldName) ||
                !array_key_exists($indexingFieldName, $data)) {
                return null;
            }
            $fieldValue = $data[$indexingFieldName];
        }

        // detect and correct type for dynamic fields

        // find last underscore, substr from there, cut off last character (S/M)
        $fieldType = substr(
            $meilisearchFieldName,
            strrpos($meilisearchFieldName, '_') + 1,
            -1
        );
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $value) {
                $fieldValue[$key] = $this->ensureFieldValueType(
                    $value,
                    $fieldType
                );
            }
        } else {
            $fieldValue = $this->ensureFieldValueType($fieldValue, $fieldType);
        }

        return $fieldValue;
    }

    // Utility methods

    /**
     * Uses a field's configuration to detect whether its value returned by a
     * content object is expected to be serialized and thus needs to be
     * unserialized.
     *
     * @param array $indexingConfiguration Current item's indexing configuration
     * @param string $meilisearchFieldName Current field being indexed
     * @return bool TRUE if the value is expected to be serialized, FALSE otherwise
     */
    public static function isSerializedValue(array $indexingConfiguration, string $meilisearchFieldName): bool
    {
        return static::isSerializedResultFromRegisteredHook($indexingConfiguration, $meilisearchFieldName)
            || static::isSerializedResultFromCustomContentElement($indexingConfiguration, $meilisearchFieldName);
    }

    /**
     * Checks if the response comes from a custom content element that returns a serialized value.
     */
    protected static function isSerializedResultFromCustomContentElement(array $indexingConfiguration, string $meilisearchFieldName): bool
    {
        $isSerialized = false;

        // MEILISEARCH_CLASSIFICATION - always returns serialized array
        if (($indexingConfiguration[$meilisearchFieldName] ?? null) == Classification::CONTENT_OBJECT_NAME) {
            $isSerialized = true;
        }

        // MEILISEARCH_MULTIVALUE - always returns serialized array
        if (($indexingConfiguration[$meilisearchFieldName] ?? null) == Multivalue::CONTENT_OBJECT_NAME) {
            $isSerialized = true;
        }

        // MEILISEARCH_RELATION - returns serialized array if multiValue option is set
        if (($indexingConfiguration[$meilisearchFieldName] ?? null) == Relation::CONTENT_OBJECT_NAME && !empty($indexingConfiguration[$meilisearchFieldName . '.']['multiValue'])) {
            $isSerialized = true;
        }

        return $isSerialized;
    }

    /**
     * Checks registered hooks if a SerializedValueDetector detects a serialized response.
     */
    protected static function isSerializedResultFromRegisteredHook(array $indexingConfiguration, string $meilisearchFieldName): bool
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['detectSerializedValue'] ?? null)) {
            return false;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['meilisearch']['detectSerializedValue'] as $classReference) {
            $serializedValueDetector = GeneralUtility::makeInstance($classReference);
            if (!$serializedValueDetector instanceof SerializedValueDetector) {
                $message = get_class($serializedValueDetector) . ' must implement interface ' . SerializedValueDetector::class;
                throw new UnexpectedValueException($message, 1404471741);
            }

            $isSerialized = (bool)$serializedValueDetector->isSerializedValue($indexingConfiguration, $meilisearchFieldName);
            if ($isSerialized) {
                return true;
            }
        }
        return false;
    }

    /**
     * Makes sure a field's value matches a (dynamic) field's type.
     *
     * @param mixed $value Value to be added to a document
     * @param string $fieldType The dynamic field's type
     * @return int|float|string|null Returns the value in the correct format for the field type
     */
    protected function ensureFieldValueType(mixed $value, string $fieldType): mixed
    {
        switch ($fieldType) {
            case 'int':
            case 'tInt':
                $value = (int)$value;
                break;

            case 'float':
            case 'tFloat':
                $value = (float)$value;
                break;
            case 'long':
                // long and double do not exist in PHP
                // simply make sure it somehow looks like a number
                // <insert PHP rant here>
            case 'tLong':
                // remove anything that's not a number or negative/minus sign
                $value = preg_replace('/[^0-9\\-]/', '', $value);
                if (trim($value) === '') {
                    $value = 0;
                }
                break;
            case 'double':
            case 'tDouble':
            case 'tDouble4':
                // as long as it's numeric we'll take it, int or float doesn't matter
                if (!is_numeric($value)) {
                    $value = 0;
                }
                break;

            default:
                // assume things are correct for non-dynamic fields
        }

        return $value;
    }


    /**
     * Indexes an item from the indexing queue and returns true when indexed, false when not
     *
     * @throws DBALException
     * @throws EXTMeilisearchException
     * @throws FrontendEnvironmentException
     * @throws NoMeilisearchConnectionFoundException
     * @throws SiteNotFoundException
     * @throws IndexingException
     */
    public function index(Item $item): bool
    {
        $indexed = true;

        $this->setLogging($item);

        if (!$this->isItemIndexable($item)) {
            return false;
        }

        $meilisearchConnections = $this->getMeilisearchConnectionsByItem($item);
        foreach ($meilisearchConnections as $systemLanguageUid => $meilisearchConnection) {

            $contentAccessGroups = $this->getAccessGroupsFromContent($item, $systemLanguageUid);
            foreach ($contentAccessGroups as $userGroup) {
                if (!$this->indexItem($item, $meilisearchConnection, (int)$systemLanguageUid, $userGroup)) {
                    /*
                     * A single language voting for "not indexed" should make the whole
                     * item count as being not indexed, even if all other languages are
                     * indexed.
                     * If there is no translation for a single language, this item counts
                     * as TRUE since it's not an error which that should make the item
                     * being reindexed during another index run.
                     */
                    $indexed = false;
                }
            }

        }

        return $indexed;
    }


    protected function isItemIndexable(Item $item): bool
    {
        return true;
    }


    /**
     * Creates a single Meilisearch Document for an item in a specific language.
     *
     * @param Item $item An index queue item to index.
     * @param int $language The language to use.
     *
     * @return bool TRUE if item was indexed successfully, FALSE on failure
     *
     * @throws DBALException
     * @throws EXTMeilisearchException
     * @throws FrontendEnvironmentException
     * @throws IndexingException
     * @throws SiteNotFoundException
     */
    protected function indexItem(Item $item, MeilisearchConnection $connection, int $language = 0, ?int $userGroup = 0): bool
    {
        $itemDocument = $this->itemToDocument($item, $language);
        if (is_null($itemDocument)) {
            /*
             * If there is no itemDocument, this means there was no translation
             * for this record. This should not stop the current item to count as
             * being valid because not-indexing not-translated items is perfectly
             * fine.
             */
            return true;
        }

        $documents = $this->getAdditionalDocuments($itemDocument, $item, $language);

        $documents = $this->processDocuments($item, $documents);

        $event = new BeforeDocumentsAreIndexedEvent(
            $itemDocument,
            $item,
            $documents,
            $this->getTsfeByItemAndLanguageId(
                $item,
                $language,
            ),
        );
        $event = $this->eventDispatcher->dispatch($event);
        $documents = $event->getDocuments();

        $response = $connection->getService()->addDocuments($documents);
        if ($response->getHttpStatus() !== 200) {
            $responseData = json_decode($response->getRawResponse() ?? '', true);
            throw new IndexingException(
                $response->getHttpStatusMessage() . ': ' . ($responseData['error']['msg'] ?? $response->getHttpStatus()),
                1678693955
            );
        }

        $this->log($item, $documents, $response);

        return true;
    }

    /**
     * Gets the full item record.
     *
     * This general record indexer simply gets the record from the item. Other
     * more specialized indexers may provide more data for their specific item
     * types.
     *
     * @param Item $item The item to be indexed
     * @param int $language Language Id (sys_language.uid)
     *
     * @return array|null The full record with fields of data to be used for indexing or NULL to prevent an item from being indexed
     *
     * @throws DBALException
     * @throws FrontendEnvironmentException
     * @throws SiteNotFoundException
     */
    protected function getFullItemRecord(Item $item, int $language = 0): ?array
    {
        $itemRecord = $this->getItemRecordOverlayed($item, $language);

        if (!is_null($itemRecord)) {
            $itemRecord['__meilisearch_index_language'] = $language;
        }

        return $itemRecord;
    }

    /**
     * Returns the overlaid item record.
     *
     * @throws DBALException
     * @throws FrontendEnvironmentException
     * @throws SiteNotFoundException
     */
    protected function getItemRecordOverlayed(Item $item, int $language): ?array
    {
        $itemRecord = $item->getRecord();
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        // skip "free content mode"-record for other languages, if item is a "free content mode"-record
        if ($this->isAFreeContentModeItemRecord($item)
            && isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) !== $language
        ) {
            return null;
        }
        // skip fallback for "free content mode"-languages
        if ($this->isLanguageInAFreeContentMode($item, $language)
            && isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) !== $language
        ) {
            return null;
        }
        // skip translated records for default language within "free content mode"-languages
        if ($language === 0
            && isset($languageField)
            && (int)($itemRecord[$languageField] ?? null) !== $language
            && $this->isLanguageInAFreeContentMode($item, (int)($itemRecord[$languageField] ?? null))
        ) {
            return null;
        }

        $pidToUse = $this->getPageIdOfItem($item);

        $globalTsfe = GeneralUtility::makeInstance(Tsfe::class);
        $specializedTsfe = $globalTsfe->getTsfeByPageIdAndLanguageId($pidToUse, $language, $item->getRootPageUid());

        if ($specializedTsfe === null) {
            return null;
        }

        return $specializedTsfe->sys_page->getLanguageOverlay($item->getType(), $itemRecord);
    }

    protected function isAFreeContentModeItemRecord(Item $item): bool
    {
        $languageField = $GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'] ?? null;
        $itemRecord = $item->getRecord();

        $l10nParentField = $GLOBALS['TCA'][$item->getType()]['ctrl']['transOrigPointerField'] ?? null;
        if ($languageField === null || $l10nParentField === null) {
            return true;
        }
        $languageOfRecord = (int)($itemRecord[$languageField] ?? null);
        $l10nParentRecordUid = (int)($itemRecord[$l10nParentField] ?? null);

        return $languageOfRecord > 0 && $l10nParentRecordUid === 0;
    }

    /**
     * Gets the configuration how to process an item's fields for indexing.
     *
     * @param Item $item An index queue item
     * @param int $language Language ID
     *
     * @return array Configuration array from TypoScript
     *
     * @throws DBALException
     */
    protected function getItemTypeConfiguration(Item $item, int $language = 0): array
    {
        $indexConfigurationName = $item->getIndexingConfigurationName();
        $fields = $this->getFieldConfigurationFromItemRecordPage($item, $language, $indexConfigurationName);
        if (!$this->isRootPageIdPartOfRootLine($item) || count($fields) === 0) {
            $fields = $this->getFieldConfigurationFromItemRootPage($item, $language, $indexConfigurationName);
            if (count($fields) === 0) {
                throw new RuntimeException('The item indexing configuration "' . $item->getIndexingConfigurationName() .
                    '" on root page uid ' . $item->getRootPageUid() . ' could not be found!', 1455530112);
            }
        }

        return $fields;
    }

    /**
     * The method retrieves the field configuration of the items record page id (pid).
     */
    protected function getFieldConfigurationFromItemRecordPage(Item $item, int $language, string $indexConfigurationName): array
    {
        try {
            $pageId = $this->getPageIdOfItem($item);
            $meilisearchConfiguration = $this->frontendEnvironment->getMeilisearchConfigurationFromPageId($pageId, $language, $item->getRootPageUid());
            return $meilisearchConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
        } catch (Throwable) {
            return [];
        }
    }

    protected function getPageIdOfItem(Item $item): ?int
    {
        if ($item->getType() === 'pages') {
            return $item->getRecordUid();
        }
        return $item->getRecordPageId();
    }

    /**
     * The method returns the field configuration of the items root page id (uid of the related root page).
     *
     * @throws DBALException
     */
    protected function getFieldConfigurationFromItemRootPage(Item $item, int $language, string $indexConfigurationName): array
    {
        $meilisearchConfiguration = $this->frontendEnvironment->getMeilisearchConfigurationFromPageId($item->getRootPageUid(), $language);

        return $meilisearchConfiguration->getIndexQueueFieldsConfigurationByConfigurationName($indexConfigurationName);
    }

    /**
     * In case of additionalStoragePid config recordPageId can be outside siteroot.
     * In that case we should not read TS config of foreign siteroot.
     */
    protected function isRootPageIdPartOfRootLine(Item $item): bool
    {
        $rootPageId = (int)$item->getRootPageUid();
        $buildRootlineWithPid = $this->getPageIdOfItem($item);
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $buildRootlineWithPid);
        $rootline = $rootlineUtility->get();

        $pageInRootline = array_filter($rootline, static function ($page) use ($rootPageId) {
            return (int)$page['uid'] === $rootPageId;
        });
        return !empty($pageInRootline);
    }

    /**
     * Converts an item array (record) to a Meilisearch document by mapping the
     * record's fields onto Meilisearch document fields as configured in TypoScript.
     *
     * @param Item $item An index queue item
     * @param int $language Language Id
     *
     * @return Document|null The Meilisearch document converted from the record
     *
     * @throws FrontendEnvironmentException
     * @throws SiteNotFoundException
     * @throws DBALException
     */
    protected function itemToDocument(Item $item, int $language = 0): ?Document
    {
        $document = null;

        $itemRecord = $this->getFullItemRecord($item, $language);
        if (!is_null($itemRecord)) {
            $itemIndexingConfiguration = $this->getItemTypeConfiguration($item, $language);
            $document = $this->getBaseDocument($item, $itemRecord);
            $tsfe = $this->getTsfeByItemAndLanguageId($item, $language);
            $document = $this->addDocumentFieldsFromTyposcript($document, $itemIndexingConfiguration, $itemRecord, $tsfe);
        }

        return $document;
    }

    protected function getTsfeByItemAndLanguageId(
        Item $item,
        int  $language = 0,
    ): TypoScriptFrontendController
    {
        $pidToUse = $this->getPageIdOfItem($item);
        return GeneralUtility::makeInstance(Tsfe::class)
            ->getTsfeByPageIdAndLanguageId(
                $pidToUse,
                $language,
                $item->getRootPageUid()
            );
    }

    /**
     * Creates a Meilisearch document with the basic / core fields set already.
     *
     * @param Item $item The item to index
     * @param array $itemRecord The record to use to build the base document
     *
     * @return Document A basic Meilisearch document
     *
     * @throws DBALException
     */
    protected function getBaseDocument(Item $item, array $itemRecord): Document
    {
        $type = $item->getType();
        $rootPageUid = $item->getRootPageUid();
        $accessRootLine = $this->getAccessRootline($item);
        return $this->documentBuilder->fromRecord($itemRecord, $type, $rootPageUid, $accessRootLine);
    }

    /**
     * Generates an Access Rootline for an item.
     *
     * @param Item $item Index Queue item to index.
     *
     * @return string The Access Rootline for the item
     */
    protected function getAccessRootline(Item $item): string
    {
        $accessRestriction = '0';
        $itemRecord = $item->getRecord();

        // TODO support access restrictions set on storage page

        if (isset($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group'])) {
            $accessRestriction = $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group']];

            if (empty($accessRestriction)) {
                // public
                $accessRestriction = '0';
            }
        }

        return 'r:' . $accessRestriction;
    }

    /**
     * Adds the document to the list of all documents (done in the event constructor),
     * and allows to add more documents before processing all of them.
     *
     * @return Document[]
     */
    protected function getAdditionalDocuments(Document $itemDocument, Item $item, int $language): array
    {
        $event = new BeforeDocumentIsProcessedForIndexingEvent(
            $itemDocument,
            $item,
            $this->getTsfeByItemAndLanguageId(
                $item,
                $language,
            )
        );
        $event = $this->eventDispatcher->dispatch($event);
        return $event->getDocuments();
    }

    /**
     * Sends the documents to the field processing service which takes care of
     * manipulating fields as defined in the field's configuration.
     *
     * @param Item $item An index queue item
     * @param array $documents An array of {@link Document} objects to manipulate.
     *
     * @return Document[] An array of manipulated Document objects.
     *
     * @throws DBALException
     * @throws EXTMeilisearchException
     */
    protected function processDocuments(Item $item, array $documents): array
    {
        //        // needs to respect the TS settings for the page the item is on, conditions may apply
        //        $meilisearchConfiguration = $this->frontendEnvironment->getMeilisearchConfigurationFromPageId($item->getRootPageUid());

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $meilisearchConfiguration = $siteRepository->getSiteByPageId($item->getRootPageUid())->getMeilisearchConfiguration();
        $fieldProcessingInstructions = $meilisearchConfiguration->getIndexFieldProcessingInstructionsConfiguration();

        // same as in the FE indexer
        if (is_array($fieldProcessingInstructions)) {
            $service = GeneralUtility::makeInstance(Service::class);
            $service->processDocuments($documents, $fieldProcessingInstructions);
        }

        return $documents;
    }

    // Initialization

    /**
     * Gets the Meilisearch connections applicable for an item.
     *
     * The connections include the default connection and connections to be used
     * for translations of an item.
     *
     * @return MeilisearchConnection[] An array of connections, the array's keys are the sys_language_uid of the language of the connection
     *
     * @throws NoMeilisearchConnectionFoundException
     * @throws DBALException
     */
    protected function getMeilisearchConnectionsByItem(Item $item): array
    {
        $meilisearchConnections = [];

        $rootPageId = $item->getRootPageUid();
        if ($item->getType() === 'pages') {
            $pageId = $item->getRecordUid();
        } else {
            $pageId = $item->getRecordPageId();
        }

        // Meilisearch configurations possible for this item
        $site = $item->getSite();
        $meilisearchConfigurationsBySite = $site->getAllMeilisearchConnectionConfigurations();
        $siteLanguages = [];
        foreach ($meilisearchConfigurationsBySite as $meilisearchConfiguration) {
            $siteLanguages[] = $meilisearchConfiguration['language'];
        }

        $defaultLanguageUid = $this->getDefaultLanguageUid($item, $site->getRootPageRecord(), $siteLanguages);
        $translationOverlays = $this->getTranslationOverlaysWithConfiguredSite((int)$pageId, $site, $siteLanguages);

        $mountPointIdentifier = $item->getMountPointIdentifier() ?? '';
        if ($mountPointIdentifier !== '') {
            $defaultConnection = $this->connectionManager->getConnectionByPageId($rootPageId, $defaultLanguageUid, $mountPointIdentifier);
        } else {
            $defaultConnection = $this->connectionManager->getConnectionByRootPageId($rootPageId, $defaultLanguageUid);
        }

        $translationConnections = $this->getConnectionsForIndexableLanguages($translationOverlays, $rootPageId);

        if ($defaultLanguageUid == 0) {
            $meilisearchConnections[0] = $defaultConnection;
        }

        foreach ($translationConnections as $systemLanguageUid => $meilisearchConnection) {
            $meilisearchConnections[$systemLanguageUid] = $meilisearchConnection;
        }
        return $meilisearchConnections;
    }

    /**
     * Returns the translation overlay
     *
     * @throws DBALException
     */
    protected function getTranslationOverlaysWithConfiguredSite(int $pageId, Site $site, array $siteLanguages): array
    {
        $translationOverlays = $this->pagesRepository->findTranslationOverlaysByPageId($pageId);
        $translatedLanguages = [];
        foreach ($translationOverlays as $key => $translationOverlay) {
            if (!in_array($translationOverlay['sys_language_uid'], $siteLanguages)) {
                unset($translationOverlays[$key]);
            } else {
                $translatedLanguages[] = (int)$translationOverlay['sys_language_uid'];
            }
        }

        if (count($translationOverlays) + 1 !== count($siteLanguages)) {
            // not all Languages are translated
            // add Language Fallback
            foreach ($siteLanguages as $languageId) {
                if ($languageId !== 0 && !in_array((int)$languageId, $translatedLanguages, true)) {
                    $fallbackLanguageIds = $this->getFallbackOrder($site, (int)$languageId);
                    foreach ($fallbackLanguageIds as $fallbackLanguageId) {
                        if ($fallbackLanguageId === 0 || in_array((int)$fallbackLanguageId, $translatedLanguages, true)) {
                            $translationOverlay = [
                                'pid' => $pageId,
                                'sys_language_uid' => $languageId,
                                'l10n_parent' => $pageId,
                            ];
                            $translationOverlays[] = $translationOverlay;
                            continue 2;
                        }
                    }
                }
            }
        }
        return $translationOverlays;
    }

    /**
     * Returns the fallback order for sites language
     */
    protected function getFallbackOrder(Site $site, int $languageId): array
    {
        $fallbackChain = [];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByRootPageId($site->getRootPageId());
            $languageAspect = LanguageAspectFactory::createFromSiteLanguage($site->getLanguageById($languageId));
            $fallbackChain = $languageAspect->getFallbackChain();
        } catch (SiteNotFoundException) {
        }
        return $fallbackChain;
    }

    /**
     * Returns default language id for given root page record and available languages.
     *
     * @throws RuntimeException
     */
    protected function getDefaultLanguageUid(Item $item, array $rootPageRecord, array $siteLanguages): int
    {
        $defaultLanguageUid = 0;
        if (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1 && $siteLanguages[min(array_keys($siteLanguages))] > 0) {
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) > 1) {
            unset($siteLanguages[array_search('0', $siteLanguages)]);
            $defaultLanguageUid = $siteLanguages[min(array_keys($siteLanguages))];
        } elseif (($rootPageRecord['l18n_cfg'] & 1) == 1 && count($siteLanguages) == 1) {
            $message = 'Root page ' . (int)$item->getRootPageUid() . ' is set to hide default translation, but no other language is configured!';
            throw new RuntimeException($message);
        }

        return $defaultLanguageUid;
    }

    /**
     * Checks for which languages connections have been configured for translation overlays and returns these connections.
     *
     * @param array $translationOverlays
     * @param int $rootPageId
     * @return MeilisearchConnection[]
     *
     * @throws DBALException
     */
    protected function getConnectionsForIndexableLanguages(array $translationOverlays, int $rootPageId): array
    {
        $connections = [];

        foreach ($translationOverlays as $translationOverlay) {
            $languageId = $translationOverlay['sys_language_uid'];

            try {
                $connection = $this->connectionManager->getConnectionByRootPageId($rootPageId, $languageId);
                $connections[$languageId] = $connection;
            } catch (NoMeilisearchConnectionFoundException) {
                // ignore the exception as we seek only those connections
                // actually available
            }
        }

        return $connections;
    }

    // Utility methods

    // FIXME extract log() and setLogging() to WapplerSystems\Meilisearch\Indexer\AbstractIndexer
    // FIXME extract an interface Tx_Meilisearch_IndexQueue_ItemInterface

    /**
     * Enables logging dependent on the configuration of the item's site
     *
     * @throws DBALException
     */
    protected function setLogging(Item $item): void
    {
        $meilisearchConfiguration = $this->frontendEnvironment->getMeilisearchConfigurationFromPageId($item->getRootPageUid());
        $this->loggingEnabled = $meilisearchConfiguration->getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack(
            $item->getIndexingConfigurationName()
        );
    }

    /**
     * Logs the item and what document was created from it
     *
     * @param Item $item The item that is being indexed.
     * @param Document[] $itemDocuments An array of Meilisearch documents created from the item's data
     * @param ResponseAdapter $response The Meilisearch response for the particular index document
     */
    protected function log(Item $item, array $itemDocuments, ResponseAdapter $response): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        $message = 'Index Queue indexing ' . $item->getType() . ':' . $item->getRecordUid() . ' - ';

        // preparing data
        $documents = [];
        foreach ($itemDocuments as $document) {
            $documents[] = (array)$document;
        }

        $logData = ['item' => (array)$item, 'documents' => $documents, 'response' => (array)$response];

        if ($response->getHttpStatus() == 200) {
            $severity = LogLevel::NOTICE;
            $message .= 'Success';
        } else {
            $severity = LogLevel::ERROR;
            $message .= 'Failure';

            $logData['status'] = $response->getHttpStatus();
            $logData['status message'] = $response->getHttpStatusMessage();
        }

        $this->logger->log($severity, $message, $logData);
    }

    /**
     * Checks the given language, if it is in "free" mode.
     *
     * @throws DBALException
     */
    protected function isLanguageInAFreeContentMode(Item $item, int $language): bool
    {
        if ($language === 0 || $language === -1) {
            return false;
        }
        $typo3site = $item->getSite()->getTypo3SiteObject();
        $typo3siteLanguage = $typo3site->getLanguageById($language);
        $typo3siteLanguageFallbackType = $typo3siteLanguage->getFallbackType();

        return $typo3siteLanguageFallbackType === 'free';
    }

    private function getLogger()
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }



}
