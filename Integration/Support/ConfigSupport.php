<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\LeuchtfeuerCompanyTimelineIntegration;

class ConfigSupport extends LeuchtfeuerCompanyTimelineIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
