<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait EmailTrait
{
    private function addEmailsEvent(CompanyTimelineEvent $event)
    {
        $leads = $event->getLeads();
        foreach ($leads as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            // Add sent emails
            $this->addEmailEvents($lead, $event, 'sent');

            // Add read emails
            $this->addEmailEvents($lead, $event, 'read');

            // Add failed emails
            $this->addEmailEvents($lead, $event, 'failed');
        }
    }

    private function addEmailEvents(Lead $lead, CompanyTimelineEvent $event, $state): void
    {
        // Set available event types
        $eventTypeKey  = 'company.email.'.$state;
        $eventTypeName = $this->translator->trans('mautic.email.'.$state);
        $event->addEventType($eventTypeKey, $eventTypeName);
        $event->addSerializerGroup('emailList');

        // Decide if those events are filtered
        if (!$event->isApplicable($eventTypeKey)) {
            return;
        }

        $queryOptions          = $event->getQueryOptions();
        $queryOptions['state'] = $state;
//        $stats                 = $this->statRepository->getLeadStats($lead->getId(), $queryOptions);
        $stats                 = $this->emailStatModel->getRepository()->getLeadStats($lead->getId(), $queryOptions);

        // Add total to counter
        $event->addToCounter($eventTypeKey, $stats);

        if (!$event->isEngagementCount()) {
            // Add the events to the event array
            foreach ($stats['results'] as $stat) {
                if (!empty($stat['email_name'])) {
                    $label = $stat['email_name'];
                } elseif (!empty($stat['storedSubject'])) {
                    $label = $this->translator->trans('mautic.email.timeline.event.custom_email').': '.$stat['storedSubject'];
                } else {
                    $label = $this->translator->trans('mautic.email.timeline.event.custom_email');
                }

                if (!empty($stat['idHash'])) {
                    $eventName = [
                        'label'      => $lead->getName().': '.$label,
                        'href'       => $this->router->generate('mautic_email_webview', ['idHash' => $stat['idHash']]),
                        'isExternal' => true,
                    ];
                } else {
                    $eventName = $lead->getName().': '.$label;
                }
                if ('failed' == $state or 'sent' == $state) { // this is to get the correct column for date dateSent
                    $dateSent = 'sent';
                } else {
                    $dateSent = 'read';
                }

                $contactId = $stat['lead_id'];
                unset($stat['lead_id']);

                $event->addEvent(
                    [
                        'event'      => $eventTypeKey,
                        'eventId'    => $eventTypeKey.'-'.$lead->getId().'-'.$stat['id'],
                        'eventLabel' => $eventName,
                        'eventType'  => $eventTypeName,
                        'timestamp'  => $stat['date'.ucfirst($dateSent)],
                        'extra'      => [
                            'stat' => $stat,
                            'type' => $state,
                        ],
                        'contentTemplate' => '@MauticEmail/SubscribedEvents/Timeline/index.html.twig',
                        'icon'            => ('read' == $state) ? 'ri-mail-open-line' : 'ri-mail-unread-line',
                        'contactId'       => $contactId,
                    ]
                );
            }
        }
    }

}