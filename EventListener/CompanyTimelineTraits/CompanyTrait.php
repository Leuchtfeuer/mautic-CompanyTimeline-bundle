<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;
trait CompanyTrait
{
    private function addCompanyCreatedEvent(
        CompanyTimelineEvent $event,
        string $eventType,
        string $eventTypeName,
        string $icon,
    ): void {
        $event->addEventType($eventType, $eventTypeName);
        $company = $event->getCompany();
        $data = [
            'timestamp' => $company->getDateAdded()->format('Y-m-d H:i:s'),
            'event' => $eventType,
            'extra' => [
                'object_id' => $company->getId(),
                'object_name' => $company->getName(),
            ],
            'icon' => $icon,
            'eventLabel' => $eventTypeName,
            'eventType' => $eventType,

        ];

        $event->addEvent($data);
    }
}