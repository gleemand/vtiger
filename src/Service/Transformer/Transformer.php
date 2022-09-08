<?php

namespace App\Service\Transformer;

use App\Service\Vtiger\ClientInterface;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Customers\CustomerAddress;
use RetailCrm\Api\Model\Entity\Customers\CustomerPhone;
use RetailCrm\Api\Model\Entity\Customers\CustomerTag;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Orders\SerializedRelationCustomer;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\Vtiger\Factory\FactoryInterface;

class Transformer implements TransformerInterface
{
    private array $customFields;
    private array $customFieldsDirs;

    private array $managers;
    private array $defManagers;

    private string $site;

    public function __construct(
        ContainerBagInterface $params
    ) {
        $customFields = $params->get('crm.custom_fields');
        $this->customFields = json_decode($customFields, true);

        $customFieldsDirs = $params->get('crm.custom_fields_dirs');
        $this->customFieldsDirs = json_decode($customFieldsDirs, true);

        $managers = $params->get('mapping.manager');
        $this->managers = json_decode($managers, true);

        $defManagers = $params->get('mapping.default_manager');
        $this->defManagers = json_decode($defManagers, true);

        $this->site = $params->get('crm.site');
    }

    public function crmCustomerTransform(array $contact): Customer
    {
        $customer = new Customer();

        $customer->externalId   = self::prepareExternalId($contact['id']);
        $customer->firstName    = $contact['firstname'] ?? null;
        $customer->lastName     = $contact['lastname'] ?? null;
        $customer->managerId    = $this->managers[$contact['assigned_user_id']] ?? $this->defManagers['crm'] ?? null;
        $customer->email        = strtolower($contact['email'] ?? '');
        $customer->site         = $this->site;
        $customer->createdAt    = !empty($contact['createdtime'])
            ? new \DateTime($contact['createdtime'])
            : null;
        $customer->birthday     = !empty($contact['birthday'])
            ? new \DateTime($contact['birthday'])
            : null;
        $customer->phones       = array_filter([
            !empty($contact['phone']) ? new CustomerPhone($contact['phone']) : null,
        ]);
        $customer->tags = array_filter(array_merge(
            $this->prepareTagsFromValues('estado ult. llamada fría: ', $contact['cf_contacts_estadoultllamadafra'] ?? []),
            $this->prepareTagsFromValues('tipo consumo: ', $contact['cf_contacts_tipoconsumo2'] ?? []),
            $this->prepareTagsFromValues('tipo llamada: ', $contact['cf_contacts_tipollamada2'] ?? []),
            $this->prepareTagsFromValues('tipo cliente: ', $contact['cf_contacts_tipocliente2'] ?? []),
            $this->prepareTagsFromValues('token: ', $contact['cf_contacts_token2'] ?? []),
        ));

        $customFields = [];
        $customFieldsList = array_flip($this->customFields);

        foreach ($customFieldsList as $field => $crmField) {
            $customFields[$crmField] = array_key_exists($crmField, $this->customFieldsDirs)
                ? ($this->customFieldsDirs[$crmField][$contact[$field] ?? null] ?? null)
                : ($contact[$field] ?? null);
        }

        $customer->customFields = array_filter($customFields);

        if ($customer->customFields['clientedebe']) {
            $customer->customFields['clientedebe'] = (float) $customer->customFields['clientedebe'];
        }

        if (!empty($contact['mailingstate']) || !empty($contact['mailingcity']) || !empty($contact['mailingzip']) || !empty($contact['mailingcountry'])) {
            $customer->address              = new CustomerAddress();
            $customer->address->countryIso  = $contact['mailingcountry'] ?? null;
            $customer->address->index       = $contact['mailingzip'] ?? null;
            $customer->address->city        = $contact['mailingcity'] ?? null;
            $customer->address->region      = $contact['mailingstate'] ?? null;
        }

        return $customer;
    }

    public function vtigerCustomerTransform(Customer $customer): array
    {
        $fields = [];

        foreach ($this->customFields as $crmField => $field) {
            $fields[$field] = array_key_exists($crmField, $this->customFieldsDirs)
                ? (array_flip($this->customFieldsDirs[$crmField])[$customer->customFields[$crmField] ?? null] ?? null)
                : ($customer->customFields[$crmField] ?? null);
        }

        return array_merge([
            'assigned_user_id' => array_flip($this->managers)[$customer->managerId] ?? $this->defManagers['vtiger'] ?? null,
            'firstname' => $customer->firstName ?? null,
            'lastname' => $customer->lastName ?? null,
            'mailingstate' => $customer->address->region ?? null,
            'mailingcity' => $customer->address->city ?? null,
            'mailingzip' => $customer->address->index ?? null,
            'mailingcountry' => $customer->address->countryIso ?? null,
            'email' => $customer->email ?? null,
            'phone' => count($customer->phones)
                ? '+' . trim(filter_var(
                    reset($customer->phones)->number,
                    FILTER_SANITIZE_NUMBER_INT
                ), '+')
                : null,
            'createdtime' => $customer->createdAt ? $customer->createdAt->format('Y-m-d H:i:s') : null,
            'cf_contacts_fechadeinicio' => $customer->createdAt ? $customer->createdAt->format('Y-m-d H:i:s') : null,
            'birthday' => $customer->birthday ? $customer->birthday->format('Y-m-d') : null,
            'cf_contacts_estadoultllamadafra' => $this->findValuesFromTags('estado ult. llamada fría: ', $customer->tags),
            'cf_contacts_tipoconsumo2' => $this->findValuesFromTags('tipo consumo: ', $customer->tags),
            'cf_contacts_tipollamada2' => $this->findValuesFromTags('tipo llamada: ', $customer->tags),
            'cf_contacts_tipocliente2' => $this->findValuesFromTags('tipo cliente: ', $customer->tags),
            'cf_contacts_token2' => $this->findValuesFromTags('token: ', $customer->tags),
            'cf_contacts_7clientedebe' => '0',
        ], $fields);
    }

    public static function prepareExternalId(string $id): string
    {
        return $id;
    }

    private function prepareTagsFromValues(string $prefix, string $values): array
    {
        return array_map(
            function ($value) use ($prefix, $values) { return $value ? new CustomerTag($prefix . strtolower($value)) : null; },
            explode(' |##| ', $values)
        );
    }

    /**
     * @param string $prefix
     * @param CustomerTag[] $customerTags
     * @return string
     */
    private function findValuesFromTags(string $prefix, array $customerTags): string
    {
        $tags = [];

        foreach ($customerTags as $customerTag) {
            if (false !== strpos($customerTag->name, $prefix)) {
                [, $tag] = explode($prefix, $customerTag->name);
                $tags[] = $tag;
            }
        }

        return implode(' |##| ', $tags);
    }
}
