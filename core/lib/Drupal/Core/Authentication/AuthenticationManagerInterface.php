<?php

/**
 * @file
 * Contains \Drupal\Core\Authentication\AuthenticationManagerInterface.
 */

namespace Drupal\Core\Authentication;

/**
 * Interface for Authentication Managers.
 */
interface AuthenticationManagerInterface extends AuthenticationProviderInterface, AuthenticationProviderFilterInterface, AuthenticationProviderChallengeInterface {

  /**
   * Adds a provider to the array of registered providers.
   *
   * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $provider
   *   The provider object.
   * @param string $id
   *   Identifier of the provider.
   * @param int $priority
   *   The providers priority.
   */
  public function addProvider(AuthenticationProviderInterface $provider, $id, $priority = 0);

  /**
   * List of provider keys.
   *
   * @return array
   *   The list of provider keys.
   */
  public function getProviderKeys();

}
