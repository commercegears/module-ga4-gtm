<?php
/**
 * Copyright © Softaware Commerce. All rights reserved.
 */
declare(strict_types=1);

namespace Softaware\Ga4\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Softaware\Ga4\Model\GtmContainerExport;

/**
 * POST: downloads the GTM container JSON for the chosen store view.
 */
class Download extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_Ga4::export';

    public function __construct(
        Context $context,
        private readonly GtmContainerExport $export,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResponseInterface
    {
        $store = (string)$this->getRequest()->getParam('store', '0');
        $json = $this->export->toJson(ctype_digit($store) ? (int)$store : $store);
        return $this->fileFactory->create(
            'softaware-ga4-gtm-container.json',
            $json,
            DirectoryList::VAR_DIR,
            'application/json'
        );
    }
}
