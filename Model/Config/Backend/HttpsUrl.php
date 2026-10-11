<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class HttpsUrl extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = rtrim(trim((string)$this->getValue()), '/');
        if ($value !== '' && !preg_match('#^https://[a-z0-9.-]+(:[0-9]+)?(/[A-Za-z0-9._~/-]*)?$#i', $value)) {
            throw new LocalizedException(__('"%1" must be an https:// address without query string.', $value));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
