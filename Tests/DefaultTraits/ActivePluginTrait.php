<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\DefaultTraits;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;

trait ActivePluginTrait
{
    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');

        // Install Company Tags
        $this->installPlugin('LeuchtfeuerCompanyTimelineBundle');

        // Install Company Event Log
        $this->installPlugin('LeuchtfeuerCompanyEventLogBundle');

        // Install Company Points
        $this->installPlugin('LeuchtfeuerCompanyPointsBundle');

        // Install Company Tags
        $this->installPlugin('LeuchtfeuerCompanyTagsBundle');
    }

    private function installPlugin(string $nameBundle, bool $isPublished = true): void
    {
        $nameIntegration = str_replace('Bundle', '', $nameBundle);
        $integration     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration]);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName($nameIntegration);
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished(true);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
