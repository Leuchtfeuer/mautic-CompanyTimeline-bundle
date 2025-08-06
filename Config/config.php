<?php

declare(strict_types=1);

return [
    'name'        => 'Leuchtfeuer Digital Marketing GmbH',
    'description' => 'Add a detailed history tab for Companies',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'integrations' => [
            'mautic.integration.leuchtfeuercompanytimeline' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\LeuchtfeuerCompanyTimelineIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanytimeline.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanytimeline.config' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Integration\Config::class,
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
                'controller'   => 'MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Controller\CompanyTimelineController::indexAction',
                'requirements' => [
                    'companyId' => '\d+',
                ],
            ],
            'mautic_company_action' => [
                'path'       => '/companies/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTimelineBundle\Controller\CompanyController::executeAction',
            ],
        ],
    ],
];
