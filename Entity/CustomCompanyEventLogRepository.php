<?php

namespace MauticPlugin\CompanyTimelineBundle\Entity;

use Doctrine\DBAL\ArrayParameterType;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\Company;

class CustomCompanyEventLogRepository extends CommonRepository
{

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
        $alias = $this->getTableAlias();
        $qb    = $this->getEntityManager()->getConnection()->createQueryBuilder()
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
}