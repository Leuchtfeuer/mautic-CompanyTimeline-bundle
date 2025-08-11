<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle;

final class LeuchtfeuerCompanyTimelineEvents
{
    /**
     * The mautic.company_timeline_on_generate event is thrown to generate the company timeline.
     *
     * The event listener receives a MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent instance.
     *
     * @var string
     */
    public const TIMELINE_ON_GENERATE = 'mautic.company_timeline_on_generate';
}
