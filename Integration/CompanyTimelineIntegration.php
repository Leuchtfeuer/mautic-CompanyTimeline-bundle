<?php
declare(strict_types=1);

namespace MauticPlugin\CompanyTimelineBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class CompanyTimelineIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const INTEGRATION_NAME = 'companytimeline';
    public const DISPLAY_NAME     = 'Add a detailed history tab for Companies';

    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/CompanyTimelineBundle/Assets/img/icon.png';
    }
}