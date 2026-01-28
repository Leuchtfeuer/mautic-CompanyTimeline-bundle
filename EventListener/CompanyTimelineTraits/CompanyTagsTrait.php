<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait CompanyTagsTrait
{
    private function addCompanyTagsEvent(
        LeuchtfeuerCompanyTimelineEvent $event,
        $eventType,
        $eventTypeName,
        $icon,
        $bundle = null,
        $object = null,
        $action = null,
        $contentTemplate = null,
    ): void {
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
            $companyTag     = $this->companyTagModel->getRepository()->find($log['object_id']);
            $companyTagName = 'Unknown Segment';
            if ($companyTag) {
                $companyTagName = $companyTag->getName();
            }
            $eventName = $this->translator->trans('mautic.company_timeline.timeline.tag.add', [
                '%tag%' => $companyTagName,
            ]);

            if (!empty($action) && 'removed' === $action) {
                $eventName = $this->translator->trans('mautic.company_timeline.timeline.tag.remove', [
                    '%tag%' => $companyTagName,
                ]);
            }

            $eventTagLabelName = $this->translator->trans('mautic.company_timeline.timeline.tag_label_name', [
                '%tag%' => $companyTagName,
            ]);

            $eventLabel = [
                'label' => $eventTagLabelName,
                'href'  => $this->router->generate(
                    'mautic_companytag_action',
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
