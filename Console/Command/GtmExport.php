<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Softaware\Ga4\Model\GtmContainerExport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento softaware:ga4:gtm-export [--store=default] [--output=file.json]
 */
class GtmExport extends Command
{
    public function __construct(
        private readonly GtmContainerExport $export,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('softaware:ga4:gtm-export')
            ->setDescription('Write a GTM container (tags, triggers, variables for all GA4 events) to import into Google Tag Manager')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store view code or id whose measurement ID is used', '0')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File to write (default: print to the console)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $json = $this->export->toJson($input->getOption('store'));
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $file = $input->getOption('output');
        if ($file) {
            if (@file_put_contents((string)$file, $json) === false) {
                $output->writeln('<error>Could not write ' . $file . '</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>Written to ' . $file . '</info>');
        } else {
            $output->writeln($json);
        }
        return Command::SUCCESS;
    }
}
