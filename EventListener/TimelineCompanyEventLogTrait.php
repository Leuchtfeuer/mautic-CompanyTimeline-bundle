<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener;

use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\Model\ChannelTimelineInterface;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;
use Mautic\LeadBundle\Entity\Lead;

trait TimelineCompanyEventLogTrait
{
    private function addEvents(CompanyTimelineEvent $event, $eventType, $eventTypeName, $icon, $bundle = null, $object = null, $action = null, $contentTemplate = null): void
    {
        $eventTypeName = $this->translator->trans($eventTypeName);
        $event->addEventType($eventType, $eventTypeName);

        if (!$event->isApplicable($eventType)) {
            return;
        }

        $events = $this->customCompanyEventLogRepository->getEvents($event->getCompany(), $bundle, $object, $action, $event->getQueryOptions());

        // Add to counter
        $event->addToCounter($eventType, $events);

        if ($event->isEngagementCount()) {
            return;
        }

        // Add the logs to the event array
        foreach ($events['results'] as $log) {
            $event->addEvent(
                $this->getEventEntry($log, $eventType, $eventTypeName, $icon, $contentTemplate)
            );
        }
    }

    private function getEventEntry(array $log, string $eventType, $eventTypeName, $icon, $contentTemplate, $eventLabel = null): array
    {
        $properties = json_decode($log['properties'], true);
        if (null === $eventLabel) {
            $eventLabel = $this->getSourceName($log, $eventType);
        }

        $entry = [
            'event'      => $eventType,
            'eventId'    => $eventType.$log['id'],
            'eventType'  => $eventTypeName,
            'eventLabel' => $eventLabel,
            'timestamp'  => $log['date_added'],
            'icon'       => $icon,
            'contactId'  => $log['company_id'],
            'extra'      => $properties,
        ];

        if ($contentTemplate) {
            $entry['contentTemplate'] = $contentTemplate;
        }

        return $entry;
    }

    /**
     * @return string
     */
    private function getSourceName(array $log, string $eventType)
    {
        $properties = json_decode($log['properties'], true);

        if (!empty($properties['object_description'])) {
            $customString = 'mautic.company.timeline.'.$eventType.'_by_object';
            if ($this->translator->hasId($customString)) {
                return $this->translator->trans(
                    $customString,
                    [
                        '%name%' => $properties['object_description'],
                    ]
                );
            }

            $customString = 'mautic.company.timeline.'.$eventType.'_'.$log['action'].'_by_object';
            if ($this->translator->hasId($customString)) {
                return $this->translator->trans(
                    $customString,
                    [
                        '%name%' => $properties['object_description'],
                    ]
                );
            }
        }

        $customString = 'mautic.company.timeline.'.$log['bundle'].'.'.$log['object'];
        if ($this->translator->hasId($customString)) {
            return $this->translator->trans($customString);
        }

        $customString = 'mautic.company.timeline.'.$log['bundle'].'.'.$log['object'].'.'.$log['action'];
        if ($this->translator->hasId($customString)) {
            return $this->translator->trans($customString);
        }

        return $this->translator->trans(
            'mautic.company.timeline.'.$eventType,
            [
                '%bundle%' => $log['bundle'],
                '%object%' => $log['object'],
                '%action%' => $log['action'],
            ]
        );
    }

    private function addLeadAddedToCompany(
        CompanyTimelineEvent $event,
        string $eventType,
        string $eventTypeName,
        string $icon,
    )
    {
        $leads = $event->getLeads();

        foreach ($leads as $lead) {
            $event->addEventType($eventType, $eventTypeName);
            $companyLead = $this->companyModel->getCompanyLeadRepository()->findBy(
                [
                    'company' => $event->getCompany(),
                    'lead'    => $lead,
                ]
            );
            if (empty($companyLead)) {
                continue;
            }
            $companyLead = $companyLead[0];
            assert($companyLead instanceof CompanyLead);
            $data = [
                'timestamp' => $companyLead->getDateAdded()->format('Y-m-d H:i:s'),
                'event' => $eventType,
                'extra' => [
                    'object_id' => $companyLead->getLead()->getId(),
                    'object_name' => $companyLead->getLead()->getName(),
                ],
                'icon' => $icon,
                'eventLabel' => $companyLead->getLead()->getName().': '.$eventTypeName,
                'eventType' => $eventType,

            ];

            $event->addEvent($data);
        }
    }







}
