<?php

namespace App\Service\SinceDateTime;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class SinceDateTime implements SinceDateTimeInterface
{
    private Filesystem $filesystem;

    private string $file;

    private LoggerInterface $logger;

    private string $since;

    public function __construct(
        Filesystem $filesystem,
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->file = __DIR__ . '/../../../' . $params->get('app.since_datetime_file');
        $this->logger = $logger;
    }

    private function now()
    {
        $now = new \DateTime();

        return $now
            ->setTimezone(new \DateTimeZone('Europe/Madrid'))
            ->format('U');
    }

    public function save(): void
    {
        if (!$this->filesystem->exists($this->file)) {
            $this->filesystem->touch($this->file);
        }

        $this->logger->debug('Save SinceDateTime: ' . $this->since);

        $this->filesystem->dumpFile($this->file, $this->since);
    }

    public function get(): string
    {
        $since = (new \DateTime())
            ->sub(new \DateInterval('P1M'))
            ->format('U');

        if ($this->filesystem->exists($this->file)) {
            $since = file_get_contents($this->file);
        }

        $this->logger->debug('Get SinceDateTime: ' . $since);

        return $since;
    }

    public function set(): void
    {
        $this->since = $this->now();

        $this->logger->debug('Set SinceDateTime: ' . $this->since);
    }
}
