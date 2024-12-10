<?php

namespace MauticPlugin\LeuchtfeuerIdentitySyncBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\FormController as CommonFormController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CookieHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\TrackingPixelHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\LeadBundle\Tracker\DeviceTracker;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends CommonFormController
{
    protected LeadRepository $leadRepository;
    protected Request $request;
    /** @var array<string, string> */
    protected array $publiclyUpdatableFieldValues = [];

    public function __construct(
        protected EntityManager $entityManager,
        protected ContactTracker $contactTracker,
        protected DeviceTracker $deviceTracker,
        protected CookieHelper $cookieHelper,
        RequestStack $requestStack,
        FormFactoryInterface $formFactory,
        FormFieldHelper $fieldHelper,
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        CorePermissions $security
    ) {
        parent::__construct(
            $formFactory,
            $fieldHelper,
            $doctrine,
            $factory,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security
        );
        $this->request        = $requestStack->getCurrentRequest();
    }

    /**
     * @throws \Exception
     */
    public function identityControlImageAction(Request $request): Response
    {
        $get   = $request->query->all();
        $post  = $request->request->all();
        $query = \array_merge($get, $post);

        // end response if no query params are given
        if (empty($query)) {
            return $this->createPixelResponse($request);
        }

        // check if at least one query param-field is a unique-identifier and publicly-updatable
        /** @var LeadModel $leadModel */
        $leadModel            = $this->getModel('lead');
        $this->leadRepository = $leadModel->getRepository();

        $result = $leadModel->checkForDuplicateContact($query, true, true);
        /** @var Lead $leadFromQuery */
        $leadFromQuery                      = $result[0];
        $this->publiclyUpdatableFieldValues = $result[1];
        $uniqueLeadIdentifiers              = $this->getUniqueIdentifierFieldNames();

        $isAtLeastOneUniqueIdentifierPubliclyUpdatable = function () use ($uniqueLeadIdentifiers): bool {
            $publiclyUpdatableFieldNames = array_keys($this->publiclyUpdatableFieldValues);

            return count(array_intersect($publiclyUpdatableFieldNames, $uniqueLeadIdentifiers)) > 0;
        };

        // end response if not at least one unique publicly-updatable field exists
        if (!$isAtLeastOneUniqueIdentifierPubliclyUpdatable()) {
            return $this->createPixelResponse($request);
        }

        // check if cookie-lead exists
        $leadFromCookie = $request->cookies->get('mtc_id', null);

        if (null !== $leadFromCookie) {
            /** @var Lead $leadFromCookie */
            $leadFromCookie = $leadModel->getEntity($leadFromCookie);
        }

        // no cookie-lead is available
        if (empty($leadFromCookie)) {
            // check if a query-lead exists as contact already, if not creating a new one
            if ($leadFromQuery->getId() > 0) {
                $this->contactTracker->setTrackedContact($leadFromQuery);
            }

            // create lead with values from query param, set cookie and end response
            $lead = $this->contactTracker->getContact(); // this call does not set the given query-params, we've to manually add them
            $this->updateLeadWithQueryParams($lead, $query);

            return $this->createPixelResponse($request);
        }

        // check if unique field-values matching the cookie-lead
        $uniqueIdentifiersFromQueryLeadMatchingLead = function (Lead $lead) use ($leadFromQuery, $uniqueLeadIdentifiers, $query): bool {
            $result = true;
            foreach ($uniqueLeadIdentifiers as $uniqueLeadIdentifier) {
                if (array_key_exists($uniqueLeadIdentifier, $query)) {
                    $fieldGetterName = 'get'.$uniqueLeadIdentifier; // the CustomFieldEntityTrait handles the correct method-name to get/set the field (also when using underscores)
                    if ($lead->$fieldGetterName() !== $leadFromQuery->$fieldGetterName()) {
                        $result = false;
                        break;
                    }
                }
            }

            return $result;
        };
        if ($uniqueIdentifiersFromQueryLeadMatchingLead($leadFromCookie)) {
            // we call ContactTracker->getContact() here to update the last-activity
            $this->contactTracker->setTrackedContact($leadFromCookie);
            $this->contactTracker->getContact();

            // update publicly-updatable fields of cookie-lead with query param values and end response
            $this->updateLeadWithQueryParams($leadFromCookie, $query);

            return $this->createPixelResponse($request);
        }

        // exchange cookie with ID from query-lead and end response
        if ($leadFromQuery->getId() > 0) {
            $this->cookieHelper->setCookie('mtc_id', $leadFromQuery->getId(), null);
            // create a device for the lead here which sets the device-tracking cookies
            $this->deviceTracker->createDeviceFromUserAgent($leadFromQuery, $request->server->get('HTTP_USER_AGENT'));

            return $this->createPixelResponse($request);
        }

        // check if the unique-identifiers of the cookie-lead are empty
        $uniqueIdentifiersFromCookieLeadAreEmpty = function (Lead $lead) use ($uniqueLeadIdentifiers): bool {
            $result = false;
            foreach ($uniqueLeadIdentifiers as $uniqueLeadIdentifier) {
                $fieldGetterName = 'get'.$uniqueLeadIdentifier; // the CustomFieldEntityTrait handles the correct method-name to get/set the field (also when using underscores)
                if (empty($lead->$fieldGetterName())) {
                    $result = true;
                    break;
                }
            }

            return $result;
        };
        if ($uniqueIdentifiersFromCookieLeadAreEmpty($leadFromCookie)) {
            // update publicly-updatable fields of cookie-lead with query param values and end response
            $this->updateLeadWithQueryParams($leadFromCookie, $query);

            return $this->createPixelResponse($request);
        }

        // create new lead with values from query, set cookie and end response
        $this->leadRepository->saveEntity($leadFromQuery);
        $this->cookieHelper->setCookie('mtc_id', $leadFromQuery->getId(), null);

        // manually log last active for new created lead
        if (!defined('MAUTIC_LEAD_LASTACTIVE_LOGGED')) {
            $this->leadRepository->updateLastActive($leadFromQuery->getId());
            define('MAUTIC_LEAD_LASTACTIVE_LOGGED', 1);
        }

        // create a device for the lead here which sets the device-tracking cookies
        $this->deviceTracker->createDeviceFromUserAgent($leadFromQuery, $request->server->get('HTTP_USER_AGENT'));

        return $this->createPixelResponse($request);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    protected function updateLeadWithQueryParams(Lead $lead, array $query): void
    {
        $leadUpdated = false;

        foreach ($this->publiclyUpdatableFieldValues as $leadField => $value) {
            // update lead with values from query
            $fieldSetterName = 'set'.$leadField; // the CustomFieldEntityTrait handles the correct method-name to get/set the field (also when using underscores)
            $lead->$fieldSetterName($query[$leadField]);
            $leadUpdated = true;
        }

        if ($leadUpdated) {
            $this->leadRepository->saveEntity($lead);
        }
    }

    protected function createPixelResponse(Request $request): Response
    {
        return TrackingPixelHelper::getResponse($this->request);
    }

    /**
     * it's not easy to extend the LeadFieldRepository, so we use this controller method instead.
     *
     * @return mixed[]
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getUniqueIdentifierFieldNames(string $object = 'lead'): ?array
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();

        $result = $qb->select('f.alias, f.is_unique_identifer as is_unique, f.type, f.object')
            ->from(MAUTIC_TABLE_PREFIX.'lead_fields', 'f')
            ->where($qb->expr()->and(
                $qb->expr()->eq('object', ':object'),
                $qb->expr()->eq('is_unique_identifer', 1),
            ))
            ->setParameter('object', $object)
            ->orderBy('f.field_order', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        if (empty($result)) {
            return null;
        }

        $fieldNames = [];
        foreach ($result as $item) {
            $fieldNames[] = $item['alias'];
        }

        return $fieldNames;
    }
}
