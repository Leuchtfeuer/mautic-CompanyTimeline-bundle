<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait LeadCompanyEventsTrait
{
    private function addLeadAdded(
        LeuchtfeuerCompanyTimelineEvent $event,
        $eventType,
        $eventTypeName,
        $icon,
        $bundle = null,
        $object = null,
        $action = null,
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
            $lead     = $this->leadModel->getRepository()->find($log['object_id']);
            $leadName = 'Unknown Name';
            if ($lead) {
                $leadName = $lead->getName();
            }
            $eventName = $this->translator->trans('mautic.company_timeline.timeline.lead.add', [
                '%name%' => $leadName,
            ]);

            if (!empty($action) && 'removed' === $action) {
                $eventName = $this->translator->trans('mautic.company_timeline.timeline.lead.remove', [
                    '%name%' => $leadName,
                ]);
            }

            $verb = 'to';
            if ('added' !== $action) {
                $verb = 'from';
            }

            $eventLeadLabelName = $this->translator->trans('mautic.company_timeline.timeline.lead_label_name', [
                '%action%' => $action,
                '%verb%'   => $verb,
                '%name%'   => $leadName,
            ]);

            $eventLabel = [
                'label' => $eventLeadLabelName,
                'href'  => $this->router->generate(
                    'mautic_contact_action',
                    [
                        'objectAction' => 'view',
                        'objectId'     => $log['object_id'],
                    ]
                ),
            ];

            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventName, $icon, null, $eventLabel)
            );
        }
    }
}
