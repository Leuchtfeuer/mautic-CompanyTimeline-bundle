<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait AssestDownloadTrait
{
    private function addLeadsAssetDownload(
        CompanyTimelineEvent $event,
        string $eventType,
        string $eventTypeName,
        string $icon,
    ): void {
        $event->addEventType($eventType, $eventTypeName);
        $event->addSerializerGroup('assetList');

        // Decide if those events are filtered
        if (!$event->isApplicable($eventType)) {
            return;
        }

        $leads = $event->getLeads();

        foreach ($leads as $lead) {
            $leadId = $lead->getId();
            if (empty($leadId)) {
                continue;
            }

            // Get the downloads for the lead
            $this->addAssetDownload($lead, $event, $eventType, $eventTypeName);
        }
    }

    private function addAssetDownload(Lead $lead, CompanyTimelineEvent $event, string $eventType, string $eventTypeName): void
    {
        $leadId    = $lead->getId();
        $downloads = $this->assetModel->getDownloadRepository()->getLeadDownloads($leadId, $event->getQueryOptions());

        //        $downloads = $this->downloadRepository->getLeadDownloads($event->getLeadId(), $event->getQueryOptions());

        // Add total number to counter
        $event->addToCounter($eventType, $downloads);

        if (!$event->isEngagementCount()) {
            // Add the downloads to the event array
            foreach ($downloads['results'] as $download) {
                $asset = $this->assetModel->getEntity($download['asset_id']);
                $event->addEvent(
                    [
                        'event'      => $eventType,
                        'eventId'    => $eventType.'-'.$leadId.'-'.$download['download_id'],
                        'eventLabel' => [
                            'label' => $lead->getName().': '.$download['title'],
                            'href'  => $this->router->generate('mautic_asset_action', ['objectAction' => 'view', 'objectId' => $download['asset_id']]),
                        ],
                        'extra' => [
                            'asset'            => $asset,
                            'assetDownloadUrl' => $this->assetModel->generateUrl($asset),
                        ],
                        'eventType'       => $eventTypeName,
                        'timestamp'       => $download['dateDownload'],
                        'icon'            => 'ri-download-line',
                        'contentTemplate' => '@MauticAsset/SubscribedEvents/Timeline/index.html.twig',
                        'contactId'       => $download['lead_id'],
                    ]
                );
            }
        }
    }
}
