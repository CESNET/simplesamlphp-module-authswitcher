<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(ArraySyntaxFixer::class)
        ->call('configure', [[
            'syntax' => 'short',
        ]])
    ;

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PATHS, [__DIR__ . '/ecs.php', __DIR__ . '/www', __DIR__ . '/lib']);

    $parameters->set(
        Option::SETS,
        [
            SetList::ARRAY,
            SetList::CLEAN_CODE,
            SetList::COMMENTS,
            SetList::COMMON,
            SetList::CONTROL_STRUCTURES,
            SetList::DOCBLOCK,
            SetList::NAMESPACES,
            SetList::PHPUNIT,
            SetList::SPACES,
            SetList::SYMPLIFY,
        ]
    );
};
