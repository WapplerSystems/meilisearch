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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Logging;

use WapplerSystems\Meilisearch\System\Logging\DebugWriter;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use Psr\Log\LogLevel;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DebugWriterTest extends SetUpUnitTestCase
{
    /**
     * @test
     */
    public function testDebugMessageIsWrittenForMessageFromSolr(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(true);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(true);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::once())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDevIpMaskIsNotMatching(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(false);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(true);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::never())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDebugOutputIsDisabled(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(true);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(false);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::never())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }
}
