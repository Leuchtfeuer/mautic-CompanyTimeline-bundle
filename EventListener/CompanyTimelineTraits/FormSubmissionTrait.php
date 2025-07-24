<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait FormSubmissionTrait
{
    public function addFormsSubmittedEvent(CompanyTimelineEvent $event): void
    {
        $leads = $event->getLeads();
        foreach ($leads as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            // Add form submissions
            $this->addFormSubmittedEvent($event, $lead);
        }
    }

    private function addFormSubmittedEvent(CompanyTimelineEvent $event, Lead $lead): void
    {
        // Set available event types
        $eventTypeKey  = 'form.submitted';
        $eventTypeName = $this->translator->trans('mautic.form.event.submitted');
        $event->addEventType($eventTypeKey, $eventTypeName);
        $event->addSerializerGroup(['formList', 'submissionEventDetails']);

        if (!$event->isApplicable($eventTypeKey)) {
            return;
        }

        //        $rows = $this->submissionRepository->getSubmissions($event->getQueryOptions());
        $queryOptions           = $event->getQueryOptions();
        $queryOptions['leadId'] = $lead->getId();
        $rows                   = $this->submissionModel->getRepository()->getSubmissions($queryOptions);

        // Add total to counter
        $event->addToCounter($eventTypeKey, $rows);

        if (!$event->isEngagementCount()) {
            // Add the submissions to the event array
            foreach ($rows['results'] as $row) {
                // Convert to local from UTC
                $form       = $this->formModel->getEntity($row['form_id']);
                $submission = $this->submissionModel->getRepository()->getEntity($row['id']);

                $event->addEvent(
                    [
                        'event'      => $eventTypeKey,
                        'eventId'    => $eventTypeKey.'-'.$lead->getId().''.$row['id'],
                        'eventLabel' => [
                            'label' => $lead->getName().':'.$form->getName(),
                            'href'  => $this->router->generate('mautic_form_action', ['objectAction' => 'view', 'objectId' => $form->getId()]),
                        ],
                        'eventType' => $eventTypeName,
                        'timestamp' => $row['dateSubmitted'],
                        'extra'     => [
                            'submission' => $submission,
                            'form'       => $form,
                            'page'       => $this->pageModel->getEntity($row['page_id']),
                        ],
                        'contentTemplate' => '@MauticForm/SubscribedEvents/Timeline/index.html.twig',
                        'icon'            => 'ri-edit-2-line',
                        'contactId'       => $row['lead_id'],
                    ]
                );
            }
        }
    }
}
