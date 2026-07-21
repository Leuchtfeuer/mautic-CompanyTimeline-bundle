<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait CompanyTrait
{
    private function addCompanyCreatedEvent(
        LeuchtfeuerCompanyTimelineEvent $event,
        string $eventType,
        string $icon,
    ): void {
        $companyName = 'Unknown Company';
        $dateAdded   = null;
        if ($event->getCompany()) {
            $companyName = $event->getCompany()->getName();
            $dateAdded   = $event->getCompany()->getDateAdded();
        }
        $eventName = $this->translator->trans('mautic.company_timeline.timeline.company.created.label', [
            '%company%' => $companyName,
        ]);

        $event->addEventType($eventType, $eventName);
        $company = $event->getCompany();

        $data = [
            'timestamp' => $dateAdded,
            'event'     => $eventType,
            'extra'     => [
                'object_id'   => $company->getId(),
                'object_name' => $company->getName(),
            ],
            'icon'       => $icon,
            'eventLabel' => $eventName,
            'eventType'  => $this->translator->trans('mautic.company_timeline.timeline.company.created'),
        ];

        $event->addEvent($data);
    }
}
