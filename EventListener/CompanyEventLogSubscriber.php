<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Deduplicate\CompanyDeduper;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\Event\LeadChangeCompanyEvent;
use Mautic\LeadBundle\Exception\UniqueFieldNotFoundException;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagsEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Model\CustomCompanyEventLogModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CompanyEventLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomCompanyEventLogModel $customCompanyEventLogModel,
        private IpLookupHelper $ipLookupHelper,
        protected CompanyDeduper $companyDeduper,
        private UserModel $userModel,
        private Config $config,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::IMPORT_ON_PROCESS       => ['onImportProcess', 200],
            LeadEvents::COMPANY_POST_SAVE       => [
                ['onCompanyPointsChanged', 0],
            ],

            LeuchtfeuerCompanyTagsEvents::COMPANYTAG_COMPANY_POS_UPDATE => [
                ['onCompanyTagPosUpdate', 0],
            ],
            LeadEvents::LEAD_COMPANY_CHANGE => [
                ['onLeadChangedCompany', 0],
            ],
        ];
    }

    public function onLeadChangedCompany(LeadChangeCompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $company = $event->getCompany();
        $leads   = $event->getLeads();
        if (null === $leads && null !== $event->getLead()) {
            $leads = [$event->getLead()];
        }

        if (!$company || !$leads) {
            return;
        }

        $added = $event->wasAdded();
        foreach ($leads as $lead) {
            if (!$lead instanceof \Mautic\LeadBundle\Entity\Lead) {
                continue;
            }
            // Ensure the lead is associated with the company
            $changes = [
                'company'   => $company,
                'bundle'    => 'company',
                'object'    => 'lead',
                'objectId'  => $lead->getId(),
                'action'    => $added ? 'added' : 'removed',
                'details'   => [
                    'leads'              => array_map(fn ($lead) => $lead->getId(), $leads),
                    'company_id'         => $company->getId(),
                    'object_description' => $company->getName(),
                ],
                'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
                'date_added'=> new \DateTime(),
            ];
            $this->customCompanyEventLogModel->writeToLog($changes);
        }
    }

    public function onCompanyTagPosUpdate(CompanyTagsEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $this->customCompanyEventLogModel->saveCompanyEventLogOfCompanyTags(
            $event->getCompany(),
            $event->getTags()
        );
    }

    public function onCompanyPointsChanged(CompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

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

    public function onImportProcess(ImportProcessEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $data                    = $event->rowData;
        $data['file']            = $event->import->getOriginalFile();
        $data['totalLine']       = $event->import->getLineCount();
        $data['created_by_name'] = 'Unknown User';
        $data['created_by_id']   = 0;
        $ownerId                 = $event->import->getDefault('owner');
        if (null !== $ownerId) {
            $user = $this->userModel->getRepository()->find($ownerId);
            if (null !== $user) {
                $data['created_by_name'] = $user->getName();
                $data['created_by_id']   = $user->getId();
            }
        }


        try {
            $duplicateCompanies = $this->companyDeduper->checkForDuplicateCompanies($this->getFieldData($event->import->getMatchedFields(), $data));
        } catch (UniqueFieldNotFoundException) {
            return;
        }

        $company = !empty($duplicateCompanies) ? $duplicateCompanies[0] : null;
        if (null === $company) {
            return;
        }
        $log = [
            'company'   => $company,
            'bundle'    => 'company',
            'object'    => 'import',
            'objectId'  => $event->import->getId(),
            'action'    => ($event->import->isNew()) ? 'create' : 'update',
            'details'   => $data,
            'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
        $this->customCompanyEventLogModel->writeToLog($log);
    }

    /**
     * @param array $fields
     * @param array $data
     */
    protected function getFieldData($fields, $data): array
    {
        // Set profile data using the form so that values are validated
        $fieldData = [];
        foreach ($fields as $importField => $entityField) {
            // Prevent overwriting existing data with empty data
            if (array_key_exists($importField, $data) && !is_null($data[$importField]) && '' != $data[$importField]) {
                $fieldData[$entityField] = $data[$importField];
            }
        }

        return $fieldData;
    }
}
