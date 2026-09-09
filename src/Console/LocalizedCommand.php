<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console;

use Illuminate\Console\Command;
use Rankbeam\Seo\I18n\DisplayLocale;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** English CLI presentation by default; content locale and machine codes stay independent. */
abstract class LocalizedCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
        $this->getDefinition()->addOption(new InputOption('display-locale', null, InputOption::VALUE_REQUIRED, 'Language for translated CLI messages (default: English); does not change content locale'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = str_starts_with((string) $this->getName(), 'seo-pro:') ? 'seo-pro.cli_locale' : 'seo.cli_locale';
        $locale = (string) ($input->getOption('display-locale') ?: config($config, 'en') ?: 'en');

        return DisplayLocale::run($locale, fn (): int => parent::execute($input, $output));
    }
}
