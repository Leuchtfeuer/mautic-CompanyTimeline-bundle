<?php

declare(strict_types=1);

namespace MauticPlugin\CompanyTimelineBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\CompanyTimelineBundle\Integration\CompanyTimelineIntegration;

class ConfigSupport extends CompanyTimelineIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
