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

namespace WapplerSystems\Meilisearch\Tests\Unit\System\Util;

use WapplerSystems\Meilisearch\System\Util\SiteUtility;
use WapplerSystems\Meilisearch\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Testcase for the SiteUtilityTest helper class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteUtilityTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        SiteUtility::reset();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canFallbackToLanguageSpecificReadProperty()
    {
        $languageConfiguration = ['meilisearch_core_read' => 'readcore'];
        $languageMock = $this->createMock(SiteLanguage::class);
        $languageMock->expects(self::any())->method('toArray')->willReturn($languageConfiguration);

        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getLanguageById')->willReturn($languageMock);
        $property = SiteUtility::getConnectionProperty($siteMock, 'core', 2, 'write');

        self::assertSame('readcore', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @test
     */
    public function canFallbackToGlobalPropertyWhenLanguageSpecificPropertyIsNotSet()
    {
        $languageConfiguration = ['meilisearch_core_read' => 'readcore'];
        $languageMock = $this->createMock(SiteLanguage::class);
        $languageMock->expects(self::any())->method('toArray')->willReturn($languageConfiguration);

        $globalConfiguration = ['meilisearch_host_read' => 'readhost'];
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getLanguageById')->willReturn($languageMock);
        $siteMock->expects(self::any())->method('getConfiguration')->willReturn($globalConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 2, 'read');

        self::assertSame('readhost', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @return array
     */
    public function writeConnectionTestsDataProvider(): array
    {
        return [
            [ // enabling meilisearch_use_write_connection, resolves to specified write host
                'expectedMeilisearchHost' => 'writehost',
                'expectedSiteMockConfiguration' => [
                    'meilisearch_host_read' => 'readhost',
                    'meilisearch_use_write_connection' => true,
                    'meilisearch_host_write' => 'writehost',
                ],
            ],
            [ // enabling meilisearch_use_write_connection but not specifying write host, falls back to specified read host
                'expectedMeilisearchHost' => 'readhost',
                'expectedSiteMockConfiguration' => [
                    'meilisearch_host_read' => 'readhost',
                    'meilisearch_use_write_connection' => true,
                ],
            ],
            [ // disabling meilisearch_use_write_connection and specifying write host, falls back to specified read host
                'expectedMeilisearchHost' => 'readhost',
                'expectedSiteMockConfiguration' => [
                    'meilisearch_host_read' => 'readhost',
                    'meilisearch_use_write_connection' => false,
                    'meilisearch_host_write' => 'writehost',
                ],
            ],
        ];
    }

    /**
     * meilisearch_use_write_connection is functional
     *
     * @dataProvider writeConnectionTestsDataProvider
     * @test
     */
    public function meilisearch_use_write_connectionSiteSettingInfluencesTheWriteConnection(string $expectedMeilisearchHost, array $expectedSiteMockConfiguration)
    {
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getConfiguration')->willReturn($expectedSiteMockConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 0, 'write');

        self::assertEquals(
            $expectedMeilisearchHost,
            $property,
            'The setting "meilisearch_use_write_connection" from sites config.yaml has no influence on system.' .
            'The setting "meilisearch_use_write_connection=true/false" must enable or disable the write connection respectively.'
        );
    }

    /**
     * @test
     */
    public function canLanguageSpecificConfigurationOverwriteGlobalConfiguration()
    {
        $languageConfiguration = ['meilisearch_host_read' => 'readhost.local.de'];
        $languageMock = $this->createMock(SiteLanguage::class);
        $languageMock->expects(self::any())->method('toArray')->willReturn($languageConfiguration);

        $globalConfiguration = ['meilisearch_host_read' => 'readhost.global.de'];
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getLanguageById')->willReturn($languageMock);
        $siteMock->expects(self::any())->method('getConfiguration')->willReturn($globalConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 2, 'read');

        self::assertSame('readhost.local.de', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @test
     */
    public function specifiedDefaultValueIsReturnedByGetConnectionPropertyIfPropertyIsNotDefinedInConfiguration()
    {
        $languageMock = $this->createMock(SiteLanguage::class);
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getLanguageById')->willReturn($languageMock);
        $property = SiteUtility::getConnectionProperty($siteMock, 'some_property', 2, 'read', 'value-of_some_property');

        self::assertEquals('value-of_some_property', $property, 'Can not fall back to defaultValue.');
    }

    /**
     * Data provider for testing boolean value handling
     *
     * @return array
     */
    public function siteConfigurationValueHandlingDataProvider(): array
    {
        return [
            [ // directly set boolean value (true) for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => true,
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => true,
            ],
            [ // directly set boolean value (false) for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => false,
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => false,
            ],
            [ // boolean value (true) set via environment variable for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => 'true',
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => true,
            ],
            [ // boolean value (false) set via environment variable for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => 'false',
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => false,
            ],
            [ // string '0' for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => '0',
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => '0',
            ],
            [ // int 0 value for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => 0,
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => 0,
            ],
            [ // int 0 value for meilisearch_enabled
                'fakeConfiguration' => [
                    'meilisearch_enabled' => 0,
                ],
                'property' => 'enabled',
                'scope' => 'read',
                'expectedConfigurationValue' => 0,
            ],
            [ // int 8080 value for meilisearch_port_read
                'fakeConfiguration' => [
                    'meilisearch_port_read' => 8080,
                ],
                'property' => 'port',
                'scope' => 'read',
                'expectedConfigurationValue' => 8080,
            ],
            [ // core_en value for meilisearch_core_read
                'fakeConfiguration' => [
                    'meilisearch_core_read' => 'core_en',
                    'meilisearch_core_write' => 'core_en_write',
                ],
                'property' => 'core',
                'scope' => 'read',
                'expectedConfigurationValue' => 'core_en',
            ],
            [ // core_en_write value for meilisearch_core_write, use right scope
                'fakeConfiguration' => [
                    'meilisearch_use_write_connection' => 1,
                    'meilisearch_core_read' => 'core_en',
                    'meilisearch_core_write' => 'core_en_write',
                ],
                'property' => 'core',
                'scope' => 'write',
                'expectedConfigurationValue' => 'core_en_write',
            ],
            [ // core_en value for meilisearch_core_read, tests fallback to read
                'fakeConfiguration' => [
                    'meilisearch_use_write_connection' => 1,
                    'meilisearch_core_read' => 'core_en',
                ],
                'property' => 'core',
                'scope' => 'write',
                'expectedConfigurationValue' => 'core_en',
            ],
            [ // disabled write connection via int 0 for meilisearch_enabled_write, use right scope
                'fakeConfiguration' => [
                    'meilisearch_use_write_connection' => 1,
                    'meilisearch_enabled' => '1',
                ],
                'property' => 'enabled',
                'scope' => 'write',
                'expectedConfigurationValue' => '0',
            ],
        ];
    }

    /**
     * Tests if boolean values in site configuration can be handled
     *
     * @param array $fakeConfiguration
     * @param string $property
     * @param string $scope
     * @param mixed $expectedConfigurationValue
     *
     * @test
     * @dataProvider siteConfigurationValueHandlingDataProvider
     */
    public function canHandleSiteConfigurationValues(
        array $fakeConfiguration,
        string $property,
        string $scope,
        $expectedConfigurationValue
    ) {
        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getConfiguration')->willReturn($fakeConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, $property, 0, $scope);

        self::assertEquals($expectedConfigurationValue, $property, 'Value from site configuration not read/handled correctly.');
    }
}
