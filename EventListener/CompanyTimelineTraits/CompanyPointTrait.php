<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait CompanyPointTrait
{
    private function addChangePoints(
        LeuchtfeuerCompanyTimelineEvent $event,
        $eventType,
        $icon,
    ): void {
        $events = $this->customCompanyEventLogModel->getEvents(
            $event->getCompany(),
            'company',
            'company_points',
            'changed',
            $event->getQueryOptions()
        );

        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        $eventName       = $this->translator->trans('mautic.company_timeline.timeline.companypoints.label');
        $contentTemplate = '@LeuchtfeuerCompanyTimeline/SubscriberEvents/Timeline/companypoints.html.twig';
        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $properties = json_decode($log['properties'], true);
            $eventLabel = $this->translator->trans('mautic.company_timeline.timeline.companypoints.changed', [
                '%oldpoints%' => (int) $properties['changes']['from'],
                '%points%'    => $properties['changes']['to'],
            ]);

            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventName, $icon, $contentTemplate, $eventLabel)
            );
        }
    }
}
