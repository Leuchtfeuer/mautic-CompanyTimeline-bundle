<?php
return [
    'name'        => 'Leuchtfeuer Digital Marketing GmbH',
    'description' => 'Add a detailed history tab for Companies',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'integrations' => [
            'mautic.integration.companytimeline' => [
                'class' => \MauticPlugin\CompanyTimelineBundle\Integration\CompanyTimelineIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.companytimeline.configuration' => [
                'class' => \MauticPlugin\CompanyTimelineBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.companytimeline.config' => [
                'class' => \MauticPlugin\CompanyTimelineBundle\Integration\Config::class,
                'tags'  => [
                    'mautic.integrations.helper',
                ],
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
    'routes'      => [
        'main' => [
            'mautic_companytimeline_action' => [
                'path'         => '/companies/timeline/{companyId}/{page}',
                'controller'   => 'MauticPlugin\CompanyTimelineBundle\Controller\CompanyTimelineController::indexAction',
                'requirements' => [
                    'companyId' => '\d+',
                ],
            ],
            'mautic_company_action' => [
                'path'       => '/companies/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\CompanyTimelineBundle\Controller\CompanyController::executeAction',
            ],
        ],
    ],
];
