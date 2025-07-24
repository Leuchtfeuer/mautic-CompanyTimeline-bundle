<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener;

use Mautic\AssetBundle\Model\AssetModel;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\CompanyTimelineBundle\CompanyTimelineEvents;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\AssestDownloadTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanySegmentTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyTagsTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\CompanyTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\EmailTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\FormSubmissionTrait;
use MauticPlugin\CompanyTimelineBundle\EventListener\CompanyTimelineTraits\PageHitTrait;
use MauticPlugin\CompanyTimelineBundle\Model\CustomCompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanyTimelineEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * Compile events for the lead timeline.
     */
    public function onTimelineGenerate(CompanyTimelineEvent $event): void
    {
        $eventTypes = [
            'company.created'       => 'mautic.company_timeline.timeline.company.created',
            'company.segmentadd'    => 'mautic.company_timeline.timeline.segment.add',
            'company.segmentremove' => 'mautic.company_timeline.timeline.segment.remove',
            'company.tagadd'        => 'mautic.company_timeline.timeline.companytag.add',
            'company.tagremove'     => 'mautic.company_timeline.timeline.companytag.remove',
            // event to leads
            'lead.asset.download'         => 'mautic.asset.event.download',
            'lead.company.email'          => 'mautic.company_timeline.timeline.company.added',
            'lead.form.company.submitted' => 'mautic.company_timeline.timeline.company.form.submitted',
            'lead.company.page.hit'       => 'mautic.page.event.hit',
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
            }
        }
    }

    private function timelineTagAdded(CompanyTimelineEvent $event, string $eventType): void
    {
        $this->timelineTag(
            $event,
            $eventType,
            'added',
        );
    }

    private function timelineTagRemoved(CompanyTimelineEvent $event, string $eventType): void
    {
        $this->timelineTag(
            $event,
            $eventType,
            'removed',
        );
    }

    private function timelineTag(CompanyTimelineEvent $event, string $eventType, string $action): void
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

    private function timelineSegmentAdd(CompanyTimelineEvent $event, string $eventType): void
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

    private function timelineSegmentRemove(CompanyTimelineEvent $event, string $eventType): void
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

    private function timelineCompanyCreated(CompanyTimelineEvent $event, string $eventType): void
    {
        $this->addCompanyCreatedEvent(
            $event,
            $eventType,
            'ri-spy-line',
        );
    }

    private function timelineAssetDownload(CompanyTimelineEvent $event, string $type, string $name): void
    {
        $this->addLeadsAssetDownload($event, $type, $name, 'ri-add-box-fill');
    }

    private function timelineEmails(CompanyTimelineEvent $event): void
    {
        $this->addEmailsEvent($event);
    }

    private function timelineFormSubmission(CompanyTimelineEvent $event): void
    {
        $this->addFormsSubmittedEvent($event);
    }

    private function timelinePageHit(CompanyTimelineEvent $event): void
    {
        $this->addCompanyLeadsPageHits(
            $event,
        );
    }
}
