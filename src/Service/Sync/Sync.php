<?php

namespace App\Service\Sync;

use App\Service\Vtiger\ClientInterface;
use App\Service\Vtiger\Factory\Factory;
use App\Service\Vtiger\Factory\FactoryInterface;
use App\Service\Simla\ApiWrapperInterface;
use App\Service\SinceDateTime\SinceDateTimeInterface;
use App\Service\Transformer\TransformerInterface;
use Psr\Log\LoggerInterface;

class Sync implements SyncInterface
{
    private ClientInterface $vtiger;

    private ApiWrapperInterface $simla;

    private SinceDateTimeInterface $sinceDateTime;

    private TransformerInterface $transformer;

    private LoggerInterface $logger;

    public function __construct(
        FactoryInterface $factory,
        ApiWrapperInterface $simla,
        SinceDateTimeInterface $sinceDateTime,
        TransformerInterface $transformer,
        LoggerInterface $logger
    ) {
        $this->vtiger = $factory->create();
        $this->simla = $simla;
        $this->sinceDateTime = $sinceDateTime;
        $this->transformer = $transformer;
        $this->logger = $logger;
    }

    public function run()
    {
        $this->logger->info('----------Sync START----------');
        $since = $this->sinceDateTime->get();
        $this->sinceDateTime->set();

        $syncContacts = $this->vtiger->syncContacts($since);
        $this->logger->debug('Sync contacts:', $syncContacts);

        if (is_iterable($syncContacts)) {
            foreach ($syncContacts as $syncType => $contacts) {
                if ($syncType === 'updated' && count($syncContacts['updated'])) {
                    foreach ($contacts as $contact) {
                        // do not sync customers with Leotarot-SoloEmail
                        if ('Leotarot-SoloEmail' === $contact['leadsource']) {
                            continue;
                        }

                        $transformedCustomer = $this->transformer->crmCustomerTransform($contact);

                        $customer = $this->simla->customerGet($this->transformer::prepareExternalId($contact['id']), true);
                        
                        if (!$customer) {
                            $this->simla->customerCreate($transformedCustomer);
                        } else {
                            $transformedCustomer->site = $customer->site;
                            $transformedCustomer->id = $customer->id;

                            $this->simla->customerEdit($transformedCustomer);
                        }
                    }
                }
            }

            $this->sinceDateTime->save();
        }

        $this->logger->info('-----------Sync END-----------');
    }
}