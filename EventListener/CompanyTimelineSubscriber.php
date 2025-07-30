<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener;

use Mautic\AssetBundle\Model\AssetModel;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Event\LeuchtfeuerCompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\AssestDownloadTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyImportTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyPointTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanySegmentTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyTagsTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\EmailTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\FormSubmissionTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\LeadCompanyEventsTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener\CompanyTimelineTraits\PageHitTrait;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\LeuchtfeuerCompanyTimelineEvents;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Model\CustomCompanyEventLogModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class CompanyTimelineSubscriber implements EventSubscriberInterface
{
    use TimelineCompanyEventLogTrait;
    use PageHitTrait;
    use CompanySegmentTrait;
    use CompanyTagsTrait;
    use CompanyTrait;
    use AssestDownloadTrait;
    use EmailTrait;
    use FormSubmissionTrait;
    use LeadCompanyEventsTrait;
    use CompanyPointTrait;
    use CompanyImportTrait;

    public function __construct(
        private CustomCompanyEventLogModel $customCompanyEventLogModel,
        private Translator $translator,
        private CompanySegmentModel $companySegmentModel,
        private RouterInterface $router,
        private CompanyTagModel $companyTagModel,
        private AssetModel $assetModel,
        private CompanyModel $companyModel,
        private EmailStatModel $emailStatModel,
        private SubmissionModel $submissionModel,
        private FormModel $formModel,
        private PageModel $pageModel,
        private ModelFactory $modelFactory,
        private LeadModel $leadModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyTimelineEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * Compile events for the lead timeline.
     */
    public function onTimelineGenerate(LeuchtfeuerCompanyTimelineEvent $event): void
    {
        $eventTypes = [
            'company.created'        => 'mautic.company_timeline.timeline.company.created',
            'company.segmentadd'     => 'mautic.company_timeline.timeline.segment.add',
            'company.segmentremove'  => 'mautic.company_timeline.timeline.segment.remove',
            'company.tagadd'         => 'mautic.company_timeline.timeline.companytag.add',
            'company.tagremove'      => 'mautic.company_timeline.timeline.companytag.remove',
            'company.points'         => 'mautic.company_timeline.timeline.company.points',
            'company.import'         => 'mautic.company_timeline.timeline.company.imported',
            // event to leads
            'lead.asset.download'          => 'mautic.asset.event.download',
            'lead.company.email'           => 'mautic.company_timeline.timeline.company.added',
            'lead.form.company.submitted'  => 'mautic.company_timeline.timeline.company.form.submitted',
            'lead.company.page.hit'        => 'mautic.page.event.hit',
            'lead.added'                   => 'mautic.company_timeline.timeline.contact.added',
            'lead.removed'                 => 'mautic.company_timeline.timeline.contact.removed',
        ];

        $event->getEventFilters();

        foreach ($eventTypes as $type => $label) {
            $name = $this->translator->trans($label);
            $event->addEventType($type, $name);

            if (!$event->isApplicable($type)) {
                continue;
            }

            switch ($type) {
                case 'company.segmentadd':
                    $this->timelineSegmentAdd($event, $type);
                    break;

                case 'company.segmentremove':
                    $this->timelineSegmentRemove($event, $type);
                    break;

                case 'company.tagadd':
                    $this->timelineTagAdded($event, $type);
                    break;

                case 'company.tagremove':
                    $this->timelineTagRemoved($event, $type);
                    break;

                case 'company.created':
                    $this->timelineCompanyCreated($event, $type);
                    break;

                case 'lead.asset.download':
                    $this->timelineAssetDownload($event, $type, $name);
                    break;

                case 'lead.company.email':
                    $this->timelineEmails($event);
                    break;

                case 'lead.form.company.submitted':
                    $this->timelineFormSubmission($event);

                    break;

                case 'lead.company.page.hit':
                    $this->timelinePageHit($event);
                    break;

                case 'lead.added':
                    $this->addLeadToCompany($event, $type);
                    break;
                case 'lead.removed':
                    $this->removeLeadFromCompany(
                        $event,
                        $type
                    );
                    break;
                case 'company.points':
                    $this->timelineCompanyPoints($event, $type);
                    break;

                case 'company.import':
                    $this->addCompanyImportedEvent(
                        $event,
                        $type,
                    );
                    break;
            }
        }
    }

    private function addCompanyImportedEvent(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanyImportUpdate(
            $event,
            $eventType,
            'ri-import-fill',
        );
    }

    private function timelineCompanyPoints(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addChangePoints(
            $event,
            $eventType,
            'ri-checkbox-fill',
        );
    }

    /**
     * Add a lead to the company timeline.
     */
    private function removeLeadFromCompany(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addLeadAdded(
            $event,
            $eventType,
            'mautic.company_timeline.timeline.contact.removed',
            'ri-add-box-fill',
            'company',
            'lead',
            'removed',
        );
    }

    /**
     * Add a company tag event to the timeline.
     */
    private function addLeadToCompany(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addLeadAdded(
            $event,
            $eventType,
            'mautic.company_timeline.timeline.contact.added',
            'ri-add-box-fill',
            'company',
            'lead',
            'added',
        );
    }

    private function timelineTagAdded(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->timelineTag(
            $event,
            $eventType,
            'added',
        );
    }

    private function timelineTagRemoved(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->timelineTag(
            $event,
            $eventType,
            'removed',
        );
    }

    private function timelineTag(LeuchtfeuerCompanyTimelineEvent $event, string $eventType, string $action): void
    {
        $this->addCompanyTagsEvent(
            $event,
            $eventType,
            'mautic.company_timeline.timeline.companytag.'.$action,
            'ri-add-box-fill',
            'company',
            'company_tag',
            $action,
        );
    }

    private function timelineSegmentAdd(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanySegmentEvents(
            $event,
            $eventType,
            'mautic.company_segments.timeline.segment.add',
            'ri-pie-chart-line',
            'company',
            'company_segment',
            'added',
        );
    }

    private function timelineSegmentRemove(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanySegmentEvents(
            $event,
            $eventType,
            'mautic.company_segments.timeline.segment.remove',
            'ri-pie-chart-line',
            'company',
            'company_segment',
            'removed',
        );
    }

    private function timelineCompanyCreated(LeuchtfeuerCompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanyCreatedEvent(
            $event,
            $eventType,
            'ri-spy-line',
        );
    }

    private function timelineAssetDownload(LeuchtfeuerCompanyTimelineEvent $event, string $type, string $name): void
    {
        $this->addLeadsAssetDownload($event, $type, $name, 'ri-add-box-fill');
    }

    private function timelineEmails(LeuchtfeuerCompanyTimelineEvent $event): void
    {
        $this->addEmailsEvent($event);
    }

    private function timelineFormSubmission(LeuchtfeuerCompanyTimelineEvent $event): void
    {
        $this->addFormsSubmittedEvent($event);
    }

    private function timelinePageHit(LeuchtfeuerCompanyTimelineEvent $event): void
    {
        $this->addCompanyLeadsPageHits(
            $event,
        );
    }
}
