<?php

namespace App\Service\Vtiger;

use Psr\Log\LoggerInterface;
use Salaros\Vtiger\VTWSCLib\WSClient;
use Salaros\Vtiger\VTWSCLib\WSException;

class Client implements ClientInterface
{
    public CONST ENTITY_CONTACTS = 'Contacts';
    public CONST ENTITY_USERS = 'Users';
    public CONST ENTITY_CURRENCY = 'Currency';

    private WSClient $client;

    private LoggerInterface $logger;

    public function __construct(
        WSClient $client,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function getInfo(): array
    {
        return $this->client->getVtigerInfo();
    }

    public function syncContacts(int $time): ?array
    {
        try {
            return $this->sync($time, self::ENTITY_CONTACTS);
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'time' => $time
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }
    }

    public function findCustomerById(string $id): ?array
    {
        try {
            return $this->client->entities->findOneByID(self::ENTITY_CONTACTS, $id);
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'customerId' => $id
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }
    }

    public function findUserById(string $id): ?array
    {
        try {
            return $this->client->entities->findOne(self::ENTITY_USERS, ['last_name' => $id]);
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'userId' => $id
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }
    }

    public function findCurrency(): ?array
    {
        try {
            return $this->client->entities->findOne(self::ENTITY_CURRENCY, ['currency_code' => 'CLP']);
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'userId' => $id
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }
    }

    public function createContact(array $contact): ?string
    {
        try {
            $contactCreated = $this->client->entities->createOne(self::ENTITY_CONTACTS, $contact);

            return $contactCreated['id'];
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'contact' => $contact
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }

    }

    public function editContact(string $id, array $contact): ?array
    {
        try {
            return $this->client->entities->updateOne(self::ENTITY_CONTACTS, $id, $contact);
        } catch (WSException $e) {
            $this->logger->error($e->getMessage(), [
                    'data' => [
                        'id' => $id,
                        'contact' => $contact
                    ],
                    'function' => __FUNCTION__,
                    'line' => __LINE__,
                    'file' => __FILE__,
                ]
            );

            return null;
        }
    }

    private function sync(int $modifiedTime, string $moduleName): array
    {
        return $this->client->entities->sync($modifiedTime, $moduleName);
    }
}
