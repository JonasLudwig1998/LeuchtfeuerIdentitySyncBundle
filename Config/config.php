<?php

return [
    'name' => 'Identity-Sync',
    'description' => 'Allow sync of Mautic lead identity from external systems (e.g. CMS login) through a control-pixel with query-parameter.',
    'version' => '2.1.0',
    'author' => 'Leuchtfeuer',
    'routes' => [
        'public' => [
            'identity_control' => [
                'path' => '/mcontrol.gif',
                'controller' => 'MauticPlugin\LeuchtfeuerIdentitySyncBundle\Controller\PublicController::identityControlImageAction',
            ],
        ],
    ],
    'services' => [
/*        'controllers' => [
            'leuchtfeueridentitysync.controller.public' => [
                'class' => \MauticPlugin\LeuchtfeuerIdentitySyncBundle\Controller\PublicController::class,
                'arguments' => [
                    'leuchtfeueridentitysync.config',
                    'leuchtfeueridentitysync.utility.data_provider',
                    'mautic.tracker.contact',
                    'mautic.tracker.device',
                    'mautic.helper.cookie',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'monolog.logger.mautic',
                ],
            ],
        ],*/
        'other' => [
            'leuchtfeueridentitysync.config' => [
                'class' => \MauticPlugin\LeuchtfeuerIdentitySyncBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
/*            'leuchtfeueridentitysync.utility.data_provider' => [
                'class' => \MauticPlugin\LeuchtfeuerIdentitySyncBundle\Utility\DataProviderUtility::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],*/
        ],
        'integrations' => [
            'mautic.integration.leuchtfeueridentitysync' => [
                'class' => \MauticPlugin\LeuchtfeuerIdentitySyncBundle\Integration\LeuchtfeuerIdentitySyncIntegration::class,
                'tags' => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'leuchtfeueridentitysync.integration.configuration' => [
                'class' => \MauticPlugin\LeuchtfeuerIdentitySyncBundle\Integration\Support\ConfigSupport::class,
                'tags' => [
                    'mautic.config_integration',
                ],
            ],
        ],
    ],
];