<?php
/**
 * Copyright © CommerceGears. All rights reserved.
 */
declare(strict_types=1);

namespace CommerceGears\Ga4\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Customer data for Google enhanced conversions (gtag user_data format): email, phone, names and street as
 * SHA-256 hex of the normalised values; city, region, postal code and country in plain text, as Google requires.
 * The browser only sets it after the visitor has granted ad_user_data (see ga4.js).
 */
class UserData
{
    public function __construct(
        private readonly Config $config,
        private readonly UserDataNormaliser $normaliser
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromOrder(OrderInterface $order): array
    {
        if (!$this->config->isUserDataEnabled((int)$order->getStoreId())) {
            return [];
        }
        $n = $this->normaliser;
        $address = $order->getBillingAddress();
        $data = ['sha256_email_address' => $n->hash($n->email((string)$order->getCustomerEmail()))];
        if ($address) {
            $data['sha256_phone_number'] = $n->hash($n->phone((string)$address->getTelephone(), (string)$address->getCountryId()));
            $data['address'] = array_filter([
                'sha256_first_name' => $n->hash($n->name((string)$address->getFirstname())),
                'sha256_last_name' => $n->hash($n->name((string)$address->getLastname())),
                'sha256_street' => $n->hash($n->street($address->getStreet())),
                'city' => $n->name((string)$address->getCity()),
                'region' => $n->name((string)$address->getRegion()),
                'postal_code' => $n->postalCode((string)$address->getPostcode()),
                'country' => $n->country((string)$address->getCountryId()),
            ]);
        }
        return array_filter($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromCustomer(CustomerInterface $customer): array
    {
        if (!$this->config->isUserDataEnabled((int)$customer->getStoreId())) {
            return [];
        }
        $n = $this->normaliser;
        return array_filter([
            'sha256_email_address' => $n->hash($n->email((string)$customer->getEmail())),
            'address' => array_filter([
                'sha256_first_name' => $n->hash($n->name((string)$customer->getFirstname())),
                'sha256_last_name' => $n->hash($n->name((string)$customer->getLastname())),
            ]),
        ]);
    }
}
