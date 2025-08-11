<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\Unit\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Model\CustomCompanyEventLogModel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CustomCompanyEventLogModelTest extends TestCase
{
    private $model;
    private $em;
    private $dispatcher;
    private $userHelper;
    private $coreParametersHelper;
    private $security;
    private $router;
    private $translator;
    private $logger;

    protected function setUp(): void
    {
        $this->em         = $this->createMock(EntityManagerInterface::class);
        $connectionMock   = $this->createMock(\Doctrine\DBAL\Connection::class);
        $queryBuilderMock = $this->getMockBuilder(\Doctrine\DBAL\Query\QueryBuilder::class)
            ->setConstructorArgs([$connectionMock])
            ->getMock();

        $queryBuilderMock->method('select')->willReturnSelf();
        $queryBuilderMock->method('from')->willReturnSelf();
        $queryBuilderMock->method('andWhere')->willReturnSelf();
        $queryBuilderMock->method('setParameter')->willReturnSelf();
        $queryBuilderMock->method('expr')->willReturn($queryBuilderMock); // or a separate expr mock if needed

        $connectionMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['createQueryBuilder'])
            ->getMock();
        $connectionMock->method('createQueryBuilder')->willReturn($queryBuilderMock);

        $this->em->method('getConnection')->willReturn($connectionMock);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->security   = $this->createMock(CorePermissions::class);
        $this->router     = $this->createMock(UrlGeneratorInterface::class);
        $this->translator = $this->createMock(Translator::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
        $tokenStorage     = $this->createMock(TokenStorageInterface::class);
        $this->userHelper = $this->getMockBuilder(UserHelper::class)
            ->setConstructorArgs([$tokenStorage]) // Pass required argument(s)
            ->getMock();
        $ContainerInterface         = $this->createMock(ContainerInterface::class);
        $this->coreParametersHelper = $this->getMockBuilder(CoreParametersHelper::class)
            ->setConstructorArgs([$ContainerInterface]) // Pass required argument(s)
            ->getMock();

        $this->model = $this->getMockBuilder(CustomCompanyEventLogModel::class)
            ->setConstructorArgs([
                $this->em,
                $this->security,
                $this->dispatcher,
                $this->router,
                $this->translator,
                $this->userHelper,
                $this->logger,
                $this->coreParametersHelper,
            ])
            ->onlyMethods(['getRepository', 'getTimelineResults', 'saveEntity'])
            ->getMock();
    }

    public function testGetEngagementsReturnsArray(): void
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(1);

        $repo = $this->createMock(CompanyLeadRepository::class);
        $repo->method('getCompanyLeads')->willReturn([['lead_id' => 2]]);
        $repo->method('getEntities')->willReturn([new Lead()]);

        $this->em->method('getRepository')->willReturn($repo);

        $eventMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getEvents', 'getEventTypes', 'getEventCounter', 'getMaxPage', 'getSerializerGroups'])
            ->getMock();
        $eventMock->method('getEvents')->willReturn([]);
        $eventMock->method('getEventTypes')->willReturn(['type1']);
        $eventMock->method('getEventCounter')->willReturn(['total' => 1]);
        $eventMock->method('getMaxPage')->willReturn(1);
        $eventMock->method('getSerializerGroups')->willReturn(['group1']);

        $this->dispatcher->method('dispatch')->willReturn($eventMock);
        $this->coreParametersHelper->method('get')->willReturn('http://localhost');

        $result = $this->model->getEngagements($company, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('events', $result);
    }

    public function testGetEngagementTypesReturnsArray(): void
    {
        $eventMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['fetchTypesOnly', 'getEventTypes'])
            ->getMock();
        $eventMock->method('getEventTypes')->willReturn(['type1']);

        $this->dispatcher->method('dispatch')->willReturn($eventMock);

        $result = $this->model->getEngagementTypes();

        $this->assertIsArray($result);
    }

    public function testGetEventsReturnsArray(): void
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(1);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $repoMock        = $this->getMockBuilder(CompanyEventLogRepository::class)
            ->setConstructorArgs([$managerRegistry])
            ->getMock();

        $repoMock->method('getTableAlias')->willReturn('c');

        $this->model->method('getRepository')->willReturn($repoMock);
        $this->model->method('getTimelineResults')->willReturn([['id' => 1, 'company_id' => 1]]);

        $result = $this->model->getEvents($company);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result[0]['company_id']);
    }

    public function testSaveCompanyEventLogOfCompanyTags(): void
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(1);

        $tag = $this->createMock(CompanyTags::class);
        $tag->method('getId')->willReturn(2);
        $tag->method('getName')->willReturn('TagName');

        $this->model->expects($this->atLeastOnce())->method('saveEntity');

        $this->model->saveCompanyEventLogOfCompanyTags($company, ['added' => [$tag]]);
    }

    public function testSaveCompanyScoreCalculatedChanged(): void
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(1);
        $company->method('getName')->willReturn('TestCompany');
        $company->method('getDateModified')->willReturn(new \DateTime());

        $this->model->expects($this->once())->method('saveEntity');

        $this->model->saveCompanyScoreCalculatedChanged($company, [10, 20]);
    }

    public function testWriteToLog(): void
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(1);

        $this->model->expects($this->once())->method('saveEntity');

        $log = [
            'company'    => $company,
            'bundle'     => 'company',
            'action'     => 'added',
            'object'     => 'company_tag',
            'objectId'   => 2,
            'date_added' => $company,
            'details'    => ['foo' => 'bar'],
        ];

        $this->model->writeToLog($log);
    }
}
