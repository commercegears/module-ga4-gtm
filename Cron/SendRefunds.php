<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Cron;

use CommerceGears\Ga4\Model\RefundSender;

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
