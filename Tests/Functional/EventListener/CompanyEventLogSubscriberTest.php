<?php

namespace MauticPlugin\CompanyTimelineBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\CompanyTimelineBundle\Tests\DefaultTraits\ActivePluginTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;

class CompanyEventLogSubscriberTest extends MauticMysqlTestCase
{

    use ActivePluginTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testEventLogCompanyPointsChanged(): void
    {
        $companyEventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($companyEventLogModel instanceof CompanyEventLogModel);
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertEmpty($eventLog, 'Event log should be empty before creating a company.');
        $score1 = 10;
        $score2 = 20;
        $company = $this->createCompany('Test Company',$score1);
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(1, $eventLog, 'Event log should contain one entry after creating a company.');
        assert($eventLog[0] instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
        self::assertEquals($score1, $eventLog[0]->getCompany()->getField('companyscore_calculated')['value'], 'Company score should match the initial score.');
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$company->getId());
        $form = $crawler->filter('form[name=company]')->form();
        $values = $form->getValues();
        $values['company[companyname]'] = 'New Company Name';
        $values['company[companyscore_calculated]'] = $score2;
        $form->setValues($values);
        $this->client->submit($form, $values);
        $lastEventLog = $companyEventLogModel->getRepository()->findOneBy([], ['id' => 'DESC']);
        self::assertNotEmpty($lastEventLog, 'Last event log entry should not be empty after updating the company.');
        assert($lastEventLog instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
        self::assertEquals($score2, $lastEventLog->getCompany()->getField('companyscore_calculated')['value'], 'Company score should match the updated score.');

    }

    public function testEventLogCompanyTagsChanged(): void
    {
        $companyEventLogModel = self::getContainer()->get('mautic.company_segments.model.company_event_log');
        assert($companyEventLogModel instanceof CompanyEventLogModel);
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertEmpty($eventLog, 'Event log should be empty before creating a company.');

        $company = $this->createCompany('Test Company');
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        self::assertCount(0, $eventLog, 'Event log should contain one entry after creating a company.');

        // Add tags to the company
        $tagName1 = 'Test Tag 1';
        $tagName2 = 'Test Tag 2';
        $tag1 = $this->createCompanyTag($tagName1);
        $tag2 = $this->createCompanyTag($tagName2);
        $tagsToAdd = [$tag1, $tag2];
        $tagsToRemove = [];
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTagsModel->updateCompanyTags($company, $tagsToAdd, $tagsToRemove);

        // Check if the event log is updated
        $eventLog = $companyEventLogModel->getRepository()->findAll();
        forEach ($eventLog as $logEntry) {
            assert($logEntry instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
//            dump($logEntry->getProperties(),$logEntry->getObject());
        }
        self::assertCount(2, $eventLog, 'Event log should contain two entries after adding tags to the company.');

        // Check if the last event log entry contains the added tags
        $lastEventLog = end($eventLog);
        assert($lastEventLog instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog);
        self::assertNotEmpty($lastEventLog->getProperties(), 'Last event log entry should contain properties.');
        self::assertSame($lastEventLog->getProperties()['object_description'], $tagName2, 'Last event log entry should be of type company.');
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
        $crawler = $this->client->request('GET', '/s/companies/new');
        $this->assertTrue($this->client->getResponse()->isSuccessful(), 'Company creation page should be accessible.');
        $form = $crawler->filter('form[name=company]')->form();
        $values = $form->getValues();
        $values['company[companyname]'] = $name;
        if ($score !== null) {
            $values['company[companyscore_calculated]'] = $score;
        }
        $form->setValues($values);
        $this->client->submit($form, $values);
        $this->assertTrue($this->client->getResponse()->isSuccessful(), 'Form submission should be successful.');
        return $this->em->getRepository(\Mautic\LeadBundle\Entity\Company::class)->findOneBy([], ['id' => 'DESC']);
    }


}