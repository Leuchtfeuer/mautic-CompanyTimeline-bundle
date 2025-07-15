<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\ChannelTimelineInterface;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;

trait PageHitTrait
{
    /**
     * Compile events for the lead timeline.
     */
    public function addCompanyLeadsPageHits(CompanyTimelineEvent $event): void
    {
        // Set available event types
        $eventTypeKey  = 'company.page.hit';
        $eventTypeName = $this->translator->trans('mautic.page.event.hit');
        $event->addEventType($eventTypeKey, $eventTypeName);
        $event->addSerializerGroup(['pageList', 'hitDetails']);


        if (!$event->isApplicable($eventTypeKey)) {
            return;
        }

        $leads = $event->getLeads();
        if (empty($leads)) {
            return;
        }


        foreach ($leads as $lead) {
            if (!$lead instanceof Lead) {
                continue;
            }

            // Add page hits
            $this->addPageHits($event, $lead,$eventTypeKey,$eventTypeName);
        }

    }

    private function addPageHits(CompanyTimelineEvent $event, Lead $lead, string $eventTypeKey, string $eventTypeName): void
    {
//        dd($event->getQueryOptions());
        $queryOptions = $event->getQueryOptions();
        $queryOptions['leadId'] = $lead->getId();
        $hits = $this->pageModel->getHitRepository()->getLeadHits(
            $lead->getId(),
            $queryOptions
        );

        // Add to counter
        $event->addToCounter($eventTypeKey, $hits);

        if (!$event->isEngagementCount()) {
            // Add the hits to the event array
            foreach ($hits['results'] as $hit) {
                $template = '@MauticPage/SubscribedEvents/Timeline/index.html.twig';
                $icon     = 'ri-link';

                if (!empty($hit['source'])) {
                    if ($channelModel = $this->getChannelModel($hit['source'])) {
                        if ($channelModel instanceof ChannelTimelineInterface) {
                            if ($overrideTemplate = $channelModel->getChannelTimelineTemplate($eventTypeKey, $hit)) {
                                $template = $overrideTemplate;
                            }

                            if ($overrideEventTypeName = $channelModel->getChannelTimelineLabel($eventTypeKey, $hit)) {
                                $eventTypeName = $overrideEventTypeName;
                            }

                            if ($overrideIcon = $channelModel->getChannelTimelineIcon($eventTypeKey, $hit)) {
                                $icon = $overrideIcon;
                            }
                        }

                        /* @deprecated - BC support to be removed in 3.0 */
                        // Allow a custom template if applicable
                        if (method_exists($channelModel, 'getPageHitLeadTimelineTemplate')) {
                            $template = $channelModel->getPageHitLeadTimelineTemplate($hit);
                        }
                        if (method_exists($channelModel, 'getPageHitLeadTimelineLabel')) {
                            $eventTypeName = $channelModel->getPageHitLeadTimelineLabel($hit);
                        }
                        if (method_exists($channelModel, 'getPageHitLeadTimelineIcon')) {
                            $icon = $channelModel->getPageHitLeadTimelineIcon($hit);
                        }
                        /* end deprecation */

                        if (!empty($hit['sourceId'])) {
                            if ($source = $this->getChannelEntityName($hit['source'], $hit['sourceId'], true)) {
                                $hit['sourceName']  = $source['name'];
                                $hit['sourceRoute'] = $source['url'];
                            }
                        }
                    }
                }

                if (!empty($hit['page_id'])) {
                    $page       = $this->pageModel->getEntity($hit['page_id']);
                    $eventLabel = [
                        'label' => $lead->getName().': '.$page->getTitle(),
                        'href'  => $this->router->generate('mautic_page_action', ['objectAction' => 'view', 'objectId' => $hit['page_id']]),
                    ];
                } else {
                    $label = $hit['urlTitle'] ?? $hit['url'];
                    $eventLabel = [
                        'label'      => $lead->getName().': '.$label,
                        'href'       => $hit['url'],
                        'isExternal' => true,
                    ];
                }

                $contactId = $hit['lead_id'];
                unset($hit['lead_id']);

                $event->addEvent(
                    [
                        'event'      => $eventTypeKey,
                        'eventId'    => $hit['hitId'],
                        'eventLabel' => $eventLabel,
                        'eventType'  => $eventTypeName,
                        'timestamp'  => $hit['dateHit'],
                        'extra'      => [
                            'hit' => $hit,
                        ],
                        'contentTemplate' => $template,
                        'icon'            => $icon,
                        'contactId'       => $contactId,
                    ]
                );
            }
        }
    }

    /**
     * Get the model for a channel.
     *
     * @return mixed
     */
    protected function getChannelModel($channel)
    {
        if ($this->modelFactory->hasModel($channel)) {
            return $this->modelFactory->getModel($channel);
        }

        return false;
    }

    /**
     * Get the name and/or view URL for a channel entity.
     *
     * @param bool $returnWithViewUrl
     *
     * @return array|bool|string
     */
    protected function getChannelEntityName($channel, $channelId, $returnWithViewUrl = false)
    {
        if ($channelEntity = $this->getChannelEntity($channel, $channelId)) {
            $channelModel = $this->getChannelModel($channel);
            $name         = false;
            if (method_exists($channelEntity, $channelModel->getNameGetter())) {
                $name = $channelEntity->{$channelModel->getNameGetter()}();
            }

            if ($name && $returnWithViewUrl) {
                $url           = null;
                $baseRouteName = str_replace('.', '_', $channel);
                if (method_exists($channelModel, 'getActionRouteBase')) {
                    $baseRouteName = $channelModel->getActionRouteBase();
                }
                $routeSourceName = 'mautic_'.$baseRouteName.'_action';

                if (null !== $this->router->getRouteCollection()->get($routeSourceName)) {
                    $url = $this->router->generate(
                        $routeSourceName,
                        [
                            'objectAction' => 'view',
                            'objectId'     => $channelId,
                        ]
                    );
                }

                return [
                    'name' => $name,
                    'url'  => $url,
                ];
            }

            return $name;
        }

        return false;
    }

    /**
     * Get the entity for a channel item.
     *
     * @return mixed
     */
    protected function getChannelEntity($channel, $channelId)
    {
        $channelEntity = null;
        if ($channelModel = $this->getChannelModel($channel)) {
            try {
                $channelEntity = $channelModel->getEntity($channelId);
            } catch (\Exception) {
                // Not found
            }
        }

        return $channelEntity;
    }
}