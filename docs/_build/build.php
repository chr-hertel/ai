#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SymfonyDocsBuilder\BuildConfig;
use SymfonyDocsBuilder\DocBuilder;

(new Application('Symfony AI Docs Builder', '1.0'))
    ->register('build-docs')
    ->addOption('disable-cache', null, InputOption::VALUE_NONE, 'Use this option to force a full regeneration of all doc contents')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $output->writeln('<error>ERROR: The application that builds Symfony AI Docs does not support Windows. You can try using a Linux distribution via WSL (Windows Subsystem for Linux).</error>');

            return 1;
        }

        $io = new SymfonyStyle($input, $output);
        $io->text('Building Symfony AI Docs...');

        $outputDir = __DIR__.'/output';
        $buildConfig = (new BuildConfig())
            ->setSymfonyVersion('7.3')
            ->setContentDir(__DIR__.'/..')
            ->setOutputDir($outputDir)
            ->setImagesDir($outputDir.'/_images')
            ->setImagesPublicPrefix('_images')
            ->setTheme('rtd');

        $buildConfig->disableJsonFileGeneration();
        $buildConfig->setExcludedPaths(['.github/', '_build/']);

        $isCacheDisabled = $input->getOption('disable-cache');
        if ($isCacheDisabled) {
            $buildConfig->disableBuildCache();
        }

        $io->comment(sprintf('cache: %s', $isCacheDisabled ? 'disabled' : 'enabled'));
        if (!$isCacheDisabled) {
            $io->comment('Tip: add the --disable-cache option to this command to force the re-build of all docs.');
        }

        $result = (new DocBuilder())->build($buildConfig);

        if ($result->isSuccessful()) {
            $io->success(sprintf('The Symfony AI Docs were successfully built at %s', realpath($outputDir)));

            return 0;
        }

        $io->error(sprintf("There were some errors while building the docs:\n\n%s\n", $result->getErrorTrace()));
        $io->newLine();
        $io->comment('Tip: you can add the -v, -vv or -vvv flags to this command to get debug information.');

        return 1;
    })
    ->getApplication()
    ->setDefaultCommand('build-docs', true)
    ->run();
