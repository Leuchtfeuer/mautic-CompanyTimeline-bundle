<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait CompanySegmentTrait
{
    private function addCompanySegmentEvents(LeuchtfeuerCompanyTimelineEvent $event, $eventType, $eventTypeName, $icon, $bundle = null, $object = null, $action = null, $contentTemplate = null): void
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
            $companyName = 'Unknown Company';
            if ($event->getCompany()) {
                $companyName = $event->getCompany()->getName();
            }
            $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.add', [
                '%segment%' => $companySegmentName,
            ]);

            if (!empty($action) && 'removed' === $action) {
                $eventName = $this->translator->trans('mautic.company_segments.timeline.segment.remove', [
                    '%segment%' => $companySegmentName,
                ]);
            }
            $fromTo = 'from';
            if ('added' === $action) {
                $fromTo = 'to';
            }
            $eventSegmentLabelName = $this->translator->trans('mautic.company_segments.timeline.segment_label_name', [
                '%segment%' => $companySegmentName,
                '%action%'  => $action,
                '%fromto%'  => $fromTo,
            ]);

            $eventLabel = $eventSegmentLabelName;

            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventName, $icon, $contentTemplate, $eventLabel)
            );
        }
    }
}
