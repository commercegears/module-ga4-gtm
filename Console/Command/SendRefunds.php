<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Softaware\Ga4\Model\RefundSender;
use Softaware\Ga4\Model\ResourceModel\RefundQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento softaware:ga4:refunds [--send] [--dry-run]
 */
class SendRefunds extends Command
{
    public function __construct(
        private readonly RefundSender $sender,
        private readonly RefundQueue $queue,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('softaware:ga4:refunds')
            ->setDescription('List queued GA4 refund events; --send sends pending ones now (cron does this every 5 minutes)')
            ->addOption('send', null, InputOption::VALUE_NONE, 'Send pending refunds now')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the Measurement Protocol payloads without sending');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Throwable) {
            // already set
        }
        if ($input->getOption('send') || $input->getOption('dry-run')) {
            $result = $this->sender->sendPending(
                (bool)$input->getOption('dry-run'),
                static function (string $line) use ($output): void {
                    $output->writeln($line);
                }
            );
            $output->writeln(sprintf('<info>Sent: %d, failed: %d, skipped (refunds disabled for store): %d</info>', $result['sent'], $result['failed'], $result['skipped']));
            return Command::SUCCESS;
        }
        $table = new Table($output);
        $table->setHeaders(['ID', 'Order', 'Credit memo', 'Store', 'Status', 'Attempts', 'Created', 'Sent', 'Last error']);
        foreach ($this->queue->getLatest() as $row) {
            $table->addRow([$row['refund_id'], $row['order_id'], $row['creditmemo_id'], $row['store_id'], $row['status'], $row['attempts'], $row['created_at'], $row['sent_at'], mb_substr((string)$row['last_error'], 0, 60)]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}
