<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;

trait CompanyImportTrait
{
    private function addCompanyImportUpdate(
        LeuchtfeuerCompanyTimelineEvent $event,
        string $eventType,
        string $icon,
    ): void {
        $events = $this->customCompanyEventLogModel->getEvents(
            $event->getCompany(),
            'company',
            'import',
            'update',
            $event->getQueryOptions()
        );
        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        $eventName       = $this->translator->trans('mautic.company_timeline.timeline.companyimport.label');
        $contentTemplate = '@LeuchtfeuerCompanyTimeline/SubscriberEvents/Timeline/companyimport.html.twig';

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $properties = json_decode($log['properties'], true);
            $fileName   = 'Unknown File';
            if (isset($properties['file']) && !empty($properties['file'])) {
                $fileName = $properties['file'];
            }
            $eventLabelName = $this->translator->trans('mautic.company_timeline.timeline.companyimport.changed',
                [
                    '%import%' => $fileName,
                ]
            );
            $eventLabel = [
                'label' => $eventLabelName,
                'href'  => $this->router->generate(
                    'mautic_import_action',
                    [
                        'object'       => 'company',
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
