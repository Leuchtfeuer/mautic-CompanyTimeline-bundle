<?php

namespace MauticPlugin\CompanyTimelineBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\ImportEvent;
use Mautic\LeadBundle\Event\ImportMappingEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\CompanyTimelineBundle\Model\CustomCompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;

class CompanyEventLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomCompanyEventLogModel $customCompanyEventLogModel,
        private IpLookupHelper $ipLookupHelper,
        private CompanyModel $companyModel

    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents()
    {
        return [
//            LeadEvents::IMPORT_ON_PROCESS       => ['onImportProcess'],
//            LeadEvents::IMPORT_POST_SAVE => [
//                ['onLeadImportSave', 0],
//            ],

            LeadEvents::COMPANY_POST_SAVE => [
                ['onCompanyPointsChanged', 0],
            ],

            LeuchtfeuerCompanyTagsEvents::COMPANYTAG_COMPANY_POS_UPDATE => [
                ['onCompanyTagPosUpdate', 0],
            ],
        ];

    }

    public function onCompanyTagPosUpdate(CompanyTagsEvent $event): void
    {
        $this->customCompanyEventLogModel->saveCompanyEventLogOfCompanyTags(
            $event->getCompany(),
            $event->getTags()
        );
    }

    public function onCompanyPointsChanged(CompanyEvent $event): void
    {
        if (!$event->getCompany()) {
            return;
        }
        if (!$event->getChanges()) {
            return;
        }

        $changes = $event->getChanges();
        if (!isset($changes['fields'])) {
            return;
        }

        if (!isset($changes['fields']['companyscore_calculated'])) {
            return;
        }

        $this->customCompanyEventLogModel->saveCompanyScoreCalculatedChanged(
            $event->getCompany(),
            $changes['fields']['companyscore_calculated']
        );
    }

    public function onImportProcess(ImportProcessEvent $event)
    {

        if ($event->importIsForObject('company')) {
            $lead = $event->getLead();
            $details = $event->getChanges();
            $log = [
                'bundle'    => 'company',
                'object'    => 'import',
                'objectId'  => $lead->getId(),
                'action'    => ($lead->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
            ];
            $this->customCompanyEventLogModel->writeToLog($log);
        }
    }

    public function onLeadImportSave(ImportEvent $event)
    {
        $entity = $event->getEntity();
        assert($entity instanceof Import);
        $lead = $event->getLead();
        if (!$lead) {
            return;
        }
        $primaryCompany = $lead->getPrimaryCompany();
        if (!$primaryCompany) {
            return;
        }
        $companies = $this->companyModel->getCompanyLeadRepository()->getCompaniesByLeadId($lead->getId());
        if (empty($companies)) {
            return;
        }
    }
}
