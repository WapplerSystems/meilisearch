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

namespace WapplerSystems\Meilisearch\IndexQueue;

use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;
use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use WapplerSystems\Meilisearch\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;
use WapplerSystems\Meilisearch\Traits\SkipMonitoringTrait;
use WapplerSystems\Meilisearch\Util;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\Page\PageNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * A class that monitors changes to the records so that the changed record get
 * passed to the index queue to update the according index document.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class RecordMonitor
{
    use SkipMonitoringTrait;

    protected array $trackedRecords = [];
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     *
     * @noinspection PhpMissingParamTypeInspection, because it is the TYPO3 core implementation.
     */
    public function processCmdmap_preProcess(
        $command,
        $table,
        $uid,
    ): void {
        if (($GLOBALS['BE_USER']->workspace ?? null) != 0) {
            return;
        }

        if ($command === 'delete' && $table === 'tt_content') {
            $this->eventDispatcher->dispatch(
                new ContentElementDeletedEvent($uid)
            );
        } elseif ($command === 'move' && $table === 'pages') {
            $pageRow = BackendUtility::getRecord('pages', $uid);
            $this->trackedRecords['pages'][$uid] = $pageRow;
        }
    }

    /**
     * Hooks into TCE main and tracks workspace publish/swap events and
     * page move commands in LIVE workspace.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     *
     * @noinspection PhpMissingParamTypeInspection, because it is the TYPO3 core implementation.
     */
    public function processCmdmap_postProcess(
        $command,
        $table,
        $uid,
        $value
    ): void {
        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        // track publish / swap events for records (workspace support)
        // command "version"
        if ($command === 'version' && $value['action'] === 'swap') {
            $this->eventDispatcher->dispatch(
                new VersionSwappedEvent($uid, $table)
            );
        }

        // moving pages/records in LIVE workspace
        if ($command === 'move' && ($GLOBALS['BE_USER']->workspace ?? null) == 0) {
            $event = new RecordMovedEvent($uid, $table);
            if ($table === 'pages' && ($this->trackedRecords['pages'][$uid] ?? null) !== null) {
                $event->setPreviousParentId((int)$this->trackedRecords['pages'][$uid]['pid']);
            }
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * Hooks into TCE Main and watches all record creations and updates. If it
     * detects that the new/updated record belongs to a table configured for
     * indexing through Meilisearch, we add the record to the index queue.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param int|string $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data
     * @param DataHandler $tceMain TYPO3 Core Engine parent object
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $uid,
        array $fields,
        DataHandler $tceMain,
    ): void {
        $recordUid = $uid;
        if ($this->skipMonitoringOfTable($table)) {
            return;
        }

        $recordPid = $fields['pid'] ?? null;
        if (is_null($recordPid) && MathUtility::canBeInterpretedAsInteger($recordUid)) {
            $recordInfo = $tceMain->recordInfo($table, (int)$recordUid);
            if (!is_null($recordInfo)) {
                $recordPid = $recordInfo['pid'] ?? null;
            }
        }
        if (!is_null($recordPid) && $this->skipRecordByRootlineConfiguration((int)$recordPid)) {
            return;
        }

        if ($status === 'new' && !MathUtility::canBeInterpretedAsInteger($recordUid)) {
            if (isset($tceMain->substNEWwithIDs[$recordUid])) {
                $recordUid = $tceMain->substNEWwithIDs[$recordUid];
            } else {
                return;
            }
        }
        if (Util::isDraftRecord($table, (int)$recordUid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        $this->eventDispatcher->dispatch(
            new RecordUpdatedEvent((int)$recordUid, $table, $fields)
        );
    }

    /**
     * Check if at least one page in the record's rootline is configured to exclude sub-entries from indexing
     */
    protected function skipRecordByRootlineConfiguration(int $pid): bool
    {
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pid);
        try {
            $rootline = $rootlineUtility->get();
        } catch (PageNotFoundException) {
            return true;
        }
        foreach ($rootline as $page) {
            if (isset($page['no_search_sub_entries']) && $page['no_search_sub_entries']) {
                return true;
            }
        }
        return false;
    }
}
