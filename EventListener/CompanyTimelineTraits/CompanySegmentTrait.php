<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait CompanySegmentTrait
{
    private function addCompanySegmentEvents(CompanyTimelineEvent $event, $eventType, $eventTypeName, $icon, $bundle = null, $object = null, $action = null, $contentTemplate = null): void
    {
        $eventTypeName = $this->translator->trans($eventTypeName);
        $event->addEventType($eventType, $eventTypeName);

        if (!$event->isApplicable($eventType)) {
            return;
        }
        $events = $this->customCompanyEventLogModel->getEvents(
            $event->getCompany(),
            $bundle,
            $object,
            $action,
            $event->getQueryOptions()
        );

        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $companySegment     = $this->companySegmentModel->getRepository()->find($log['object_id']);
            $companySegmentName = 'Unknown Segment';
            if ($companySegment) {
                $companySegmentName = $companySegment->getName();
            }
            $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.add', [
                '%segment%' => $companySegmentName,
            ]);

            if (!empty($action) && 'removed' === $action) {
                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.remove', [
                    '%segment%' => $companySegmentName,
                ]);
            }

            $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
                '%segment%' => $companySegmentName,
            ]);
            $eventLabel = [
                'label' => $eventSegmentLabelName,
                'href'  => $this->router->generate(
                    'mautic_company_segments_action',
                    [
                        'objectAction' => 'view',
                        'objectId'     => $log['object_id'],
                    ]
                ),
            ];

            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventName, $icon, $contentTemplate, $eventLabel)
            );
        }
    }

}