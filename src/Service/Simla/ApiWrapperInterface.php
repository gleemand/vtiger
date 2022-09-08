<?php

namespace App\Service\Simla;

use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Orders\Order;

interface ApiWrapperInterface
{
    public function customerGet(int $externalId): ?Customer;
    public function customerCreate(Customer $customer): void;
    public function customerEdit(Customer $customer): void;
    public function customersHistory(int $sinceId): ?\Generator;
}