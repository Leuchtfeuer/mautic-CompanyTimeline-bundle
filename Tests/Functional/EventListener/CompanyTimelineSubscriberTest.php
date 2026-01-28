<?php

namespace MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Tests\DefaultTraits\ActivePluginTrait;

class CompanyTimelineSubscriberTest extends MauticMysqlTestCase
{
    use ActivePluginTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->useCleanupRollback = false;
        $this->activePlugin();
        $this->setUpSymfony($this->configParams);
        // Re-login user after kernel restart
        $user = $this->em->getRepository(\Mautic\UserBundle\Entity\User::class)->findOneBy(['username' => 'admin']);
        $this->assertInstanceOf(\Mautic\UserBundle\Entity\User::class, $user);
        $this->loginUser($user);
    }

    public function testCompanyTimeline(): void
    {
        $company = $this->createCompany('Test Company');

        $companySegment = $this->createCompanySegment('Test Company Segment');

        $this->addSegmentToCompany($company, $companySegment);
        $this->removeSegmentFromCompany($company, $companySegment);
        $companyTag1 = $this->createCompanyTag('Test Company Tag 1');
        $companyTag2 = $this->createCompanyTag('Test Company Tag 2');
        $this->addTagToCompany($company, $companyTag1);
        $this->addTagToCompany($company, $companyTag2);
        $this->removeTagFromCompany($company, $companyTag1);
        $this->adjustPoints($company);
        $this->addEmailActions('read');

        $translation = self::getContainer()->get('translator');
        assert($translation instanceof \Symfony\Contracts\Translation\TranslatorInterface);
        $companyTitleToCreate = $translation->trans('mautic.company_timeline.timeline.company.created');

        $this->client->request('GET', '/s/companies/view/'.$company->getId());
        $this->assertIsString($this->client->getResponse()->getContent());
        self::assertStringContainsString($companyTitleToCreate, $this->client->getResponse()->getContent());

        $companyTitleToTagAdded = $translation->trans('mautic.company_timeline.timeline.companytag.added',
            ['%tag%' => $companyTag2->getTag()]
        );
        $companyTitleToTagRemoved = $translation->trans('mautic.company_timeline.timeline.companytag.removed',
            ['%tag%' => $companyTag1->getTag()]
        );

        self::assertStringContainsString($companyTitleToTagAdded, $this->client->getResponse()->getContent());
        self::assertStringContainsString($companyTitleToTagRemoved, $this->client->getResponse()->getContent());
        //        self::assertStringContainsString('Email read', $this->client->getResponse()->getContent());
        //        self::assertStringContainsString('Test User Email 1 read:', $this->client->getResponse()->getContent());
    }

    public function addEmailActions(string $action): void
    {
        $emailModel = self::getContainer()->get('mautic.email.model.email');
        assert($emailModel instanceof \Mautic\EmailBundle\Model\EmailModel);

        $email = new Email();
        $email->setName('Test Email');
        $email->setSubject('Test Email Subject');
        //        $email->setText('This is a test email body.');
        $email->setIsPublished(true);
        $email->setDateAdded(new \DateTime());
        $email->setDateModified(new \DateTime());

        $this->em->persist($email);
        $this->em->flush();

        $contacts = [
            $this->createLead('Lead 1'.uniqid().'@example.com', 'Test User Email 1 '.$action),
            $this->createLead('Lead 2'.uniqid().'@example.com', 'Test User Email 2 '.$action),
            $this->createLead('Lead 3'.uniqid().'@example.com', 'Test User Email 3 '.$action),
        ];

        $this->emulateEmailSend($email, $contacts, $action);
    }

    private function createLead(string $email, string $name = 'Joe'): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($name);
        $lead->setEmail($email);
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    /**
     * @param Lead[] $contacts
     */
    private function emulateEmailSend(Email $email, array $contacts, string $status): void
    {
        $emailModel = self::getContainer()->get('mautic.email.model.email');

        assert($emailModel instanceof \Mautic\EmailBundle\Model\EmailModel);

        $emailStatModel = self::getContainer()->get('mautic.email.model.stat');
        assert($emailStatModel instanceof \Mautic\EmailBundle\Model\EmailStatModel);
        $listEmailStat = [];

        foreach ($contacts as $contact) {
            $emailStat = new Stat();
            $emailStat->setEmail($email);
            $contactEmail = is_string($contact->getEmail()) ? $contact->getEmail() : '';
            $emailStat->setEmailAddress($contactEmail);
            $emailStat->setLead($contact);
            $emailStat->setDateSent(new \DateTime());
            if ('sent' === $status) {
                $emailStat->setIsRead(true);
            }
            if ('read' === $status) {
                $emailStat->setIsRead(true);
            }
            if ('sent' === $status) {
                $emailStat->setDateSent(new \DateTime());
            }
            $listEmailStat[] = $emailStat;
        }

        $emailStatModel->saveEntities($listEmailStat);
    }

    public function adjustPoints(Company $company): void
    {
        $companyModel = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);

        $company->setScore(20);
        $companyModel->saveEntity($company);
    }

    public function createCompany(string $name, int $score = 0): Company
    {
        $companyModel = self::getContainer()->get('mautic.lead.model.company');
        assert($companyModel instanceof \Mautic\LeadBundle\Model\CompanyModel);

        $company = new Company();
        $company->setName($name);

        $companyModel->saveEntity($company);

        return $company;
    }

    public function createCompanySegment(string $name): CompanySegment
    {
        $companySegmentModel = self::getContainer()->get('mautic.company_segments.model.company_segment');
        assert($companySegmentModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel);

        $companySegment = new CompanySegment();
        $companySegment->setName($name);

        $companySegmentModel->saveEntity($companySegment);

        return $companySegment;
    }

    public function addSegmentToCompany(Company $company, CompanySegment $segment): void
    {
        $companySegmentModel = self::getContainer()->get('mautic.company_segments.model.company_segment');
        assert($companySegmentModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel);
        $segmentId = $segment->getId();
        $this->assertNotNull($segmentId);
        $companySegmentModel->addCompany($company, [$segmentId]);
    }

    public function removeSegmentFromCompany(Company $company, CompanySegment $segment): void
    {
        $companySegmentModel = self::getContainer()->get('mautic.company_segments.model.company_segment');
        assert($companySegmentModel instanceof \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel);
        $segmentId = $segment->getId();
        $this->assertNotNull($segmentId);
        $companySegmentModel->removeCompany($company, [$segmentId]);
    }

    public function addTagToCompany(Company $company, CompanyTags $tag): Company
    {
        $tagsToAdd        = [$tag];
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTagsModel->updateCompanyTags($company, $tagsToAdd, []);

        return $company;
    }

    public function removeTagFromCompany(Company $company, CompanyTags $tag): Company
    {
        $tagsToRemove     = [$tag];
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTagsModel->updateCompanyTags($company, [], $tagsToRemove);

        return $company;
    }

    private function createCompanyTag(string $name): CompanyTags
    {
        $companyTagsModel = self::getContainer()->get('mautic.companytag.model.companytag');
        assert($companyTagsModel instanceof \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel);
        $companyTag = new CompanyTags();
        $companyTag->setTag($name);
        $companyTagsModel->saveEntity($companyTag);

        return $companyTag;
    }
}
