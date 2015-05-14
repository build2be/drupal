<?php

/**
 * @file
 * Contains Drupal\rest\test\AuthTest.
 */

namespace Drupal\rest\Tests;

use Drupal\rest\Tests\RESTTestBase;

/**
 * Tests REST user login.
 *
 * @group rest
 */
class UserTest extends RESTTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('basic_auth', 'hal', 'rest');

  /**
   * Tests login, status, logout.
   */
  public function testLogin() {
    $this->enableService('user_login', 'POST');

    $account = $this->drupalCreateUser();

    $payload = array(
      'op' => 'login',
      'credentials' => array(
        'name' => $account->getUsername(),
        'pass' => $account->pass_raw,
      ),
    );

    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType);
    $this->assertResponse('200', 'Successfully logged into Drupal.');

    $payload = array(
      'op' => 'logout',
    );

    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType);
    $this->assertResponse('200', 'Successfully logged out from Drupal.');

  }
}
