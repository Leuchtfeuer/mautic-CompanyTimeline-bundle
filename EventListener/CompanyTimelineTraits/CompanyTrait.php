<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait CompanyTrait
{
    private function addCompanyCreatedEvent(
        CompanyTimelineEvent $event,
        string $eventType,
        string $icon,
    ): void {
        $companyName = 'Unknown Company';
        $dateAdded   = 'Unknown Date';
        if ($event->getCompany()) {
            $companyName = $event->getCompany()->getName();
            if (null !== $event->getCompany()->getDateAdded()) {
                $dateAdded = $event->getCompany()->getDateAdded()->format('Y-m-d H:i:s');
            }
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
