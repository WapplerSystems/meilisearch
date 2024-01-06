<?php

declare(strict_types=1);

namespace WapplerSystems\Meilisearch;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WapplerSystems\Meilisearch\Attribute\Indexer;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    $containerBuilder->registerAttributeForAutoconfiguration(
        Indexer::class,
        static function (ChildDefinition $definition, Indexer $attribute): void {
            $definition->addTag(Indexer::TAG_NAME, ['type' => $attribute->type, 'priority' => $attribute->priority]);
        }
    );
};
