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
 * Click events: one "event_name: CSS selector" per line, checked when saved.
 */
class ClickRules extends Value
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
        $lines = [];
        $rules = Config::parseClickRules($value);
        if (count($rules) > Config::MAX_CLICK_RULES) {
            throw new LocalizedException(__('Enter at most %1 click events.', Config::MAX_CLICK_RULES));
        }
        foreach ($rules as $rule) {
            switch (Config::clickRuleError($rule['event'], $rule['selector'])) {
                case 'event':
                    throw new LocalizedException(__(
                        '"%1": the event name must start with a lower-case letter and use only a-z, 0-9 and _ (up to 40 characters), followed by ":" and a CSS selector.',
                        $rule['line']
                    ));
                case 'reserved':
                    throw new LocalizedException(__(
                        '"%1": %2 is sent by this module or reserved by Google Analytics. Choose another event name, for example demo_open.',
                        $rule['line'],
                        $rule['event']
                    ));
                case 'selector':
                    throw new LocalizedException(__(
                        '"%1": enter a CSS selector after the ":" (up to 300 characters, without { } or ;), for example a[href*="demo.example.com"].',
                        $rule['line']
                    ));
            }
            $lines[] = $rule['event'] . ': ' . $rule['selector'];
        }
        return implode("\n", $lines);
    }
}
