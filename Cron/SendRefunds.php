<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Cron;

use Softaware\Ga4\Model\RefundSender;

class SendRefunds
{
    public function __construct(private readonly RefundSender $sender)
    {
    }

    public function execute(): void
    {
        $this->sender->sendPending();
    }
}
