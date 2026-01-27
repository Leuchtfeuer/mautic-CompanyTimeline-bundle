<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\DefaultTraits\ActivePluginTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CompanyEventLogSubscriberTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->useCleanupRollback = false;
        $this->activePlugin();
        $this->setUpSymfony($this->configParams);
        // Re-login user after kernel restart
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        $this->assertInstanceOf(\Mautic\UserBundle\Entity\User::class, $user);
        $this->loginUser($user);
    }

    public function testEventLogCompanyPointsChanged(): void
    {
        $companyEventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($companyEventLogModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel);
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertEmpty($eventLog, 'Event log should be empty before creating a company.');

        $score1   = 10;
        $score2   = 20;

        $company = $this->createCompany('Test Company', $score1);

        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(1, $eventLog, 'Event log should contain one entry after creating a company with custom score.');

        $companyModel = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof CompanyModel);
        $company->addUpdatedField('companyscore_calculated', $score2);
        $companyModel->saveEntity($company);

        $allEventLogs = $companyEventLogModel->getRepository()->findAll();
        $this->assertCount(2, $allEventLogs);
    }

    public function testEventLogCompanyTagsChanged(): void
    {
        $companyEventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($companyEventLogModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel);
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertEmpty($eventLog, 'Event log should be empty before creating a company.');

        $company  = $this->createCompany('Test Company');
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(0, $eventLog, 'Event log should contain one entry after creating a company.');

        $tagName1         = 'Test Tag 1';
        $tagName2         = 'Test Tag 2';
        $tag1             = $this->createCompanyTag($tagName1);
        $tag2             = $this->createCompanyTag($tagName2);
        $tagsToAdd        = [$tag1, $tag2];
        $tagsToRemove     = [];
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTagsModel->updateCompanyTags($company, $tagsToAdd, $tagsToRemove);

        // Check if the event log is updated
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        foreach ($eventLog as $logEntry) {
            assert($logEntry instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
        }
        self::assertCount(2, $eventLog, 'Event log should contain two entries after adding tags to the company.');

        // Check if the last event log entry contains the added tags
        $lastEventLog = end($eventLog);
        assert($lastEventLog instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
        self::assertNotEmpty($lastEventLog->getProperties(), 'Last event log entry should contain properties.');
        self::assertSame($lastEventLog->getProperties()['object_description'], $tagName2, 'Last event log entry should be of type company.');
    }

    public function testImportCompanyEventLog(): void
    {
        $eventLogModel     = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($eventLogModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel);
        $allEventLogBefore = $eventLogModel->getRepository()->findAll();
        $companyModel      = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);
        $companiesBefore = $companyModel->getRepository()->findAll();
        $this->runCompanyCsv();
        $this->runCompanyCsv();
        $companiesLater   = $companyModel->getRepository()->findAll();
        $allEventLogLater = $eventLogModel->getRepository()->findAll();
        self::assertNotSame($companiesBefore, $companiesLater);
        self::assertNotSame(count($allEventLogBefore), count($allEventLogLater), 'Event log should be updated after importing companies.');
        $lastCompany = end($companiesLater);
        assert($lastCompany instanceof Company);
        $this->client->request('GET', '/s/companies/view/'.$lastCompany->getId());
        $this->assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString('Company import from by', $this->client->getResponse()->getContent(), 'Company import event log should be present in the company view.');
    }

    private function runCompanyCsv(): void
    {
        $crawler    = $this->client->request('GET', '/s/companies/import/new');
        $uploadForm = $crawler->selectButton('Upload')->form();
        $file       = new UploadedFile(__DIR__.'/../../Fixtures/companies.csv', 'companies.csv', 'itext/csv');
        $uploadForm['lead_import[file]']->setValue((string) $file);
        $crawler                                        = $this->client->submit($uploadForm);
        $mappingForm                                    = $crawler->selectButton('Import')->form();
        $firstUser                                      = $this->em->getRepository(\Mautic\UserBundle\Entity\User::class)->findOneBy([], ['id' => 'ASC']);
        $this->assertInstanceOf(\Mautic\UserBundle\Entity\User::class, $firstUser);
        $mappingForm['lead_field_import[company_name]'] = 'companyname';
        $mappingForm['lead_field_import[company_name]'] = 'companyname';
        $mappingForm['lead_field_import[owner]']        = $firstUser->getId();
        $this->client->submit($mappingForm);
        $imports    = $this->em->getRepository(Import::class)->findAll();
        $lastImport = end($imports);
        $this->assertInstanceOf(Import::class, $lastImport);
        $this->em->clear();
        $output = $this->testSymfonyCommand('mautic:import', ['-e' => 'dev', '--id' => $lastImport->getId(), '--limit' => 10000]);
        self::assertStringContainsString('3 lines were processed', $output->getDisplay(), 'Import command should process 3 lines.');
        $this->em->clear();
    }

    public function testAddRemoveLeadToCompany(): void
    {
        $eventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($eventLogModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel);
        $companyModel  = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);
        $leadModel = self::getContainer()->get('mautic.lead.model.lead');
        assert($leadModel instanceof \Mautic\LeadBundle\Model\LeadModel);

        $company = $this->createCompany('Test Company');
        $lead    = $this->createLead('Test Lead');

        // Add lead to company
        $companyModel->addLeadToCompany($company, $lead);

        $eventLogAfterAdd = $eventLogModel->getRepository()->findAll();
        $companyModel->removeLeadFromCompany($company, $lead);
        $eventLogAfterRemove = $eventLogModel->getRepository()->findAll();

        self::assertNotSame(count($eventLogAfterAdd), count($eventLogAfterRemove));

        $this->client->request('GET', '/s/companies/view/'.$company->getId());
        $this->assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString('to added company.', $this->client->getResponse()->getContent(), 'Lead added event log should be present in the company view.');
        self::assertStringContainsString('from removed company.', $this->client->getResponse()->getContent(), 'Lead added event log should be present in the company view.');
    }

    private function createLead(string $name): \Mautic\LeadBundle\Entity\Lead
    {
        $leadModel = self::getContainer()->get('mautic.lead.model.lead');
        assert($leadModel instanceof \Mautic\LeadBundle\Model\LeadModel);
        $lead = new \Mautic\LeadBundle\Entity\Lead();
        $lead->setFirstname($name);
        $lead->setEmail($name.'@example.com');
        $leadModel->saveEntity($lead);

        return $lead;
    }

    private function createCompanyTag(string $name): \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags
    {
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTag = new \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags();
        $companyTag->setTag($name);
        $companyTagsModel->saveEntity($companyTag);

        return $companyTag;
    }

    private function createCompany(string $name, ?int $score = null): \Mautic\LeadBundle\Entity\Company
    {
        $fieldModel = self::getContainer()->get('mautic.lead.model.field');
        assert($fieldModel instanceof FieldModel);
        $customField = $fieldModel->getRepository()->findOneBy(['alias' => 'companyscore_calculated']);
        self::assertNotNull($customField, 'Custom field companyscore_calculated should exist. The CompanyPoints plugin may not be properly installed.');

        $companyModel = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof CompanyModel);

        $company = new Company();
        $company->setName($name);
        if (null !== $score) {
            $company->addUpdatedField('companyscore_calculated', $score);
        }
        $companyModel->saveEntity($company);

        return $company;
    }
}
