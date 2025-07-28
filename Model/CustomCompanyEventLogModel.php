<?php

namespace MauticPlugin\CompanyTimelineBundle\Model;

use Doctrine\DBAL\ArrayParameterType;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CompanyTimelineBundle\CompanyTimelineEvents;
use MauticPlugin\CompanyTimelineBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel as BaseCompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CustomCompanyEventLogModel extends BaseCompanyEventLogModel
{
    use TimelineTrait;

    /**
     * @param array<mixed, mixed>|null $filters
     */
    public function getEngagements(?Company $company = null, ?array $filters = null, ?array $orderBy = null, int $page = 1, int $limit = 25, bool $forTimeline = true): array
    {
        $leads = [];
        if (!is_null($company)) {
            $leadsId = $this->em->getRepository(CompanyLead::class)->getCompanyLeads($company->getId());
            $leadsId = array_column($leadsId, 'lead_id');
            $leads   = $this->em->getRepository(Lead::class)->getEntities(['ids' => $leadsId, 'ignore_paginator' => false]);
        }
        $event = $this->dispatcher->dispatch(
            new CompanyTimelineEvent($company, $filters, $orderBy, $page, $limit, $forTimeline, $this->coreParametersHelper->get('site_url'), $leads),
            CompanyTimelineEvents::TIMELINE_ON_GENERATE
        );

        $payload = [
            'events'   => $event->getEvents(),
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $event->getEventTypes(),
            'total'    => $event->getEventCounter()['total'],
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => $event->getMaxPage(),
        ];

        return ($forTimeline) ? $payload : [$payload, $event->getSerializerGroups()];
    }

    /**
     * @return array
     */
    public function getEngagementTypes(): array
    {
        $event = new CompanyTimelineEvent();
        $event->fetchTypesOnly();

        $this->dispatcher->dispatch($event, CompanyTimelineEvents::TIMELINE_ON_GENERATE);

        return $event->getEventTypes();
    }

    /**
     * @param ?string                          $bundle
     * @param ?string                          $object
     * @param array<string,string>|string|null $actions
     * @param array<string,string>             $options
     *
     * @return array<mixed>
     */
    public function getEvents(?Company $company = null, $bundle = null, $object = null, $actions = null, array $options = [])
    {
        $alias = $this->getRepository()->getTableAlias();
        $qb    = $this->em->getConnection()->createQueryBuilder()
            ->select('*')
            ->from(MAUTIC_TABLE_PREFIX.'company_event_log', $alias);

        if ($company) {
            $qb->andWhere($alias.'.company_id = :company')
                ->setParameter('company', $company->getId());
        }

        if ($bundle) {
            $qb->andWhere($alias.'.bundle = :bundle')
                ->setParameter('bundle', $bundle);
        }

        if ($object) {
            $qb->andWhere($alias.'.object = :object')
                ->setParameter('object', $object);
        }

        if ($actions) {
            if (is_array($actions)) {
                $qb->andWhere(
                    $qb->expr()->in($alias.'.action', ':actions')
                )
                    ->setParameter('actions', $actions, ArrayParameterType::STRING);
            } else {
                $qb->andWhere($alias.'.action = :action')
                    ->setParameter('action', $actions);
            }
        }

        if (!empty($options['search'])) {
            $qb->andWhere($qb->expr()->like('LOWER('.$alias.'.properties)', $qb->expr()->literal('%'.strtolower($options['search']).'%')));
        }

        return $this->getTimelineResults($qb, $options, $alias.'.action', $alias.'.date_added', [], ['date_added'], null, $alias.'.id');
    }

    /**
     * Save a company event log for a specific action and company segment.
     */
    public function saveCompanyEventLogOfCompanyTags(Company $company, array $actionTags): void
    {
        foreach ($actionTags as $keyTag => $tags) {
            foreach ($tags as $tag) {
                if (!$tag instanceof CompanyTags) {
                    continue;
                }
                $action = 'added' === $keyTag ? 'added' : 'removed';
                $this->saveUniqueCompanyEventLogOfCompany($company, $tag, $action);
            }
        }
    }

    private function saveUniqueCompanyEventLogOfCompany(Company $company, CompanyTags $companyTag, string $action): void
    {
        $companyEventLog = new CompanyEventLog();
        $companyEventLog->setCompany($company);
        $companyEventLog->setBundle('company');
        $companyEventLog->setAction($action);
        $companyEventLog->setObject('company_tag');
        $companyEventLog->setObjectId($companyTag->getId());
        $companyEventLog->setDateAdded(new \DateTime());
        $userId      = null; // Set the user ID if available
        $userName    = 'System'; // or use the actual user name if available
        $currentUser = $this->userHelper->getUser();
        if (!is_null($currentUser)) {
            $userId   = $currentUser->getId();
            $userName = $currentUser->getUsername();
        }
        $tagId   = is_null($companyTag->getId()) ? 0 : $companyTag->getId();
        $tagName = is_null($companyTag->getName()) ? '' : $companyTag->getName();

        $companyEventLog->setProperties([
            'company_tag_id'       => $tagId,
            'company_tag_name'     => $tagName,
            'company_id'           => $company->getId(),
            'object_description'   => $tagName,
        ]);
        $companyEventLog->setUserId($userId); // Set the user ID if available
        $companyEventLog->setUserName($userName); // or use the actual user name if available
        $this->saveEntity($companyEventLog);
    }

    public function saveCompanyScoreCalculatedChanged(Company $company, array $changes): void
    {
        $companyEventLog = new CompanyEventLog();
        $companyEventLog->setCompany($company);
        $companyEventLog->setBundle('company');
        $companyEventLog->setAction('changed');
        $companyEventLog->setObject('company_points');
        $companyEventLog->setObjectId($company->getId());
        $companyEventLog->setDateAdded($company->getDateModified() ?? new \DateTime());
        $userId      = null; // Set the user ID if available
        $userName    = 'System'; // or use the actual user name if available
        $currentUser = $this->userHelper->getUser();
        if (!is_null($currentUser)) {
            $userId   = $currentUser->getId();
            $userName = $currentUser->getUsername();
        }

        $name = $company->getName().' score changed';

        $companyEventLog->setProperties([
            'changes'       => [
                'from' => $changes[0] ?? '',
                'to'   => $changes[1] ?? '',
            ],
            'company_id'           => $company->getId(),
            'object_description'   => $name,
        ]);
        $companyEventLog->setUserId($userId); // Set the user ID if available
        $companyEventLog->setUserName($userName); // or use the actual user name if available
        $this->saveEntity($companyEventLog);
    }

    public function writeToLog(array $log): void
    {
        $companyEventLog = new CompanyEventLog();
        $companyEventLog->setCompany($log['company']);
        $companyEventLog->setBundle($log['bundle']);
        $companyEventLog->setAction($log['action']);
        $companyEventLog->setObject($log['object']);
        $companyEventLog->setObjectId($log['objectId'] ?? 0);

        $companyEventLog->setDateAdded($log['date_added']->getDateModified() ?? new \DateTime());
        $userId      = null; // Set the user ID if available
        $userName    = 'System'; // or use the actual user name if available
        $currentUser = $this->userHelper->getUser();
        if (!is_null($currentUser)) {
            $userId   = $currentUser->getId();
            $userName = $currentUser->getUsername();
        }
        $companyEventLog->setProperties($log['details'] ?? []);
        $companyEventLog->setUserId($userId); // Set the user ID if available
        $companyEventLog->setUserName($userName); // or use the actual user name if available
        $this->saveEntity($companyEventLog);
    }
}
