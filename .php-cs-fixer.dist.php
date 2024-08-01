<?php

declare(strict_types=1);

/*
 * Pipeline of the project is using  https://github.com/shopware/github-actions/blob/main/.github/workflows/cs-fixer.yml
 *
 * This file is added to make it easier to run php-cs-fixer during development.
 *
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
    )
    ;