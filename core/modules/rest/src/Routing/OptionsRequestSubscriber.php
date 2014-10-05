<?php

/**
 * @file
 * Contains \Drupal\rest\Routing\OptionsRequestListener
 */

namespace Drupal\rest\Routing;

use Drupal\Core\Access\AccessManager;
use Drupal\Core\Session\AccountInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles OPTIONS requests.
 */
class OptionsRequestSubscriber implements EventSubscriberInterface {

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManager
   */
  protected $accessManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The http methods that are available to check.
   *
   * @var string[]
   */
  protected $availableMethods = array(
    'GET',
    'POST',
    'PUT',
    'DELETE',
    'HEAD',
    'PATCH',
  );

  /**
   * Constructs an options request subscriber.
   *
   * @param \Drupal\Core\Access\AccessManager $access_manager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   */
  public function __construct(AccessManager $access_manager, AccountInterface $account) {
    $this->accessManager = $access_manager;
    $this->account = $account;
  }

  /**
   * Handles OPTIONS requests.
   */
  public function onKernelRequest(GetResponseEvent $event) {
    $request = $event->getRequest();
    if ($request->getMethod() == 'OPTIONS') {
      $allowed_methods = implode(' ', $this->getAllowedMethods($request));
      $response = new Response(NULL, 200, array('Allow' => $allowed_methods));
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  /**
   * Check which methods are allowed for the current request.
   */
  protected function getAllowedMethods(Request $request) {
    $allow = array();
    $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    if (isset($route)) {
      foreach ($this->availableMethods as $method) {
        $request->setMethod($method);
        if ($this->accessManager->check($route, $request, $this->account)) {
          $allow[] = $method;
        }
      }
    }
    return $allow;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return array(KernelEvents::REQUEST => array(array('onKernelRequest', -10000)));
  }

}
