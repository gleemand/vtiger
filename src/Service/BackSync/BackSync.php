<?php

namespace App\Service\BackSync;

use App\Service\Vtiger\ClientInterface;
use App\Service\Vtiger\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceId\SinceIdInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class BackSync implements BackSyncInterface
{
    private ClientInterface $vtiger;
    private ApiWrapperInterface $simla;
    private SinceIdInterface $sinceId;
    private TransformerInterface $transformer;
    private LoggerInterface $logger;
    private array $customFields;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceIdInterface $sinceId,
        TransformerInterface $transformer,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->vtiger = $factory->create();
        $this->simla = $simla;
        $this->sinceId = $sinceId;
        $this->transformer = $transformer;
        $this->logger = $logger;
        $this->customFields = json_decode($params->get('crm.custom_fields'), true);
    }

    public function run()
    {
        $this->logger->info('----------BackSync START----------');

        $sinceId = $this->sinceId->get();
        $this->sinceId->set($sinceId);
        $history = $this->simla->customersHistory($sinceId);

        $ids = [];

        if (is_iterable($history)) {
            foreach ($history as $change) {
                $ids[$change->customer->id] = $change->customer->id;

                $this->sinceId->set($change->id);
                $this->sinceId->save();
            }
        }

        foreach ($ids as $id) {
            $customer = $this->simla->customerGet($id);

            if (!$customer) {
                continue;
            }

            $this->logger->debug('Customer: ' . print_r($customer, true));

            $contact = $this->transformer->vtigerCustomerTransform($customer);

            if (!$customer->externalId) {
                $createdContactId = $this->vtiger->createContact($contact);

                if ($createdContactId) {
                    $this->simla->customerEdit($this->newCustomerWithVtigerId($customer, $createdContactId));
                }
            } else {
                $this->vtiger->editContact($customer->externalId, $contact);
            }
        }

        $this->logger->info('-----------BackSync END-----------');
    }

    private function newCustomerWithVtigerId(Customer $customer, string $vtigerId): Customer
    {
        $customer->externalId = $this->transformer::prepareExternalId($vtigerId);

        return $customer;
    }
}