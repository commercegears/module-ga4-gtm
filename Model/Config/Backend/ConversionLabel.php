<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\Ga4\Model\Config;

/**
 * Google Ads conversion label (the part after "/" in send_to). A pasted "AW-123/label" keeps the label only.
 */
class ConversionLabel extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $this->setValue(self::normalise((string)$this->getValue()));
        return parent::beforeSave();
    }

    /**
     * @throws LocalizedException
     */
    public static function normalise(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^AW-\d+/(.+)$#i', $value, $m)) {
            $value = trim($m[1]);
        }
        if (!Config::isValidConversionLabel($value)) {
            throw new LocalizedException(__(
                '"%1" is not a Google Ads conversion label. Copy the label from the conversion\'s event snippet: it is the part after the "/" in send_to (letters, digits, - and _), for example AbC-D_efG-h12_34-567.',
                $value
            ));
        }
        if (strtolower($value) === 'abc-d_efg-h12_34-567') {
            throw new LocalizedException(__('"%1" is the example from the instructions. Enter the label of your conversion action.', $value));
        }
        return $value;
    }
}
