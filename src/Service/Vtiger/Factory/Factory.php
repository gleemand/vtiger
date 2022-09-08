<?php

namespace App\Service\Vtiger\Factory;

use App\Service\Vtiger\Client;
use App\Service\Vtiger\ClientInterface;
use Psr\Log\LoggerInterface;
use Salaros\Vtiger\VTWSCLib\WSClient;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class Factory implements FactoryInterface
{
    private WSClient $client;

    private LoggerInterface $logger;

    public function __construct(
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->client = new WSClient(
            $params->get('vtiger.api_url'),
            $params->get('vtiger.api_username'),
            $params->get('vtiger.api_access_key')
        );
    }

    public function create(): ClientInterface
    {
        return new Client($this->client, $this->logger);
    }
}