<?php declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests/');

$config = new PhpCsFixer\Config;
$config->setFinder($finder)
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true);

$config->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());

return $config;
