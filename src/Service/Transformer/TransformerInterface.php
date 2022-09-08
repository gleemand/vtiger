<?php

namespace App\Service\Transformer;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Orders\Order;

interface TransformerInterface
{
    public function crmCustomerTransform(array $contact): Customer;

    public function vtigerCustomerTransform(Customer $customer): array;
}
