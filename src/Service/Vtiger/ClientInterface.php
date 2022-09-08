<?php

namespace App\Service\Vtiger;

interface ClientInterface
{
    public function syncContacts(int $time): ?array;

    public function createContact(array $contact): ?string;

    public function editContact(string $id, array $contact): ?array;
}