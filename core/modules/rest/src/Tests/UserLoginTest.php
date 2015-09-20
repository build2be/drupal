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
class UserLoginTest extends RESTTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('basic_auth', 'hal', 'rest');

  /**
   * Test user session life cycle.
   */
  public function testLogin() {
    $this->defaultAuth = array('basic_auth');

    $this->enableService('user_login', 'POST');

    $permissions[] = 'restful post user_login';
    $account = $this->drupalCreateUser($permissions);

    $name = $account->getUsername();
    $pass = $account->pass_raw;

    $basic_auth = ['Authorization: Basic ' . base64_encode("$name:$pass")];

    $payload = array();
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'No op found. Use: status, login, logout.');

    $payload = $this->getPayload('garbage');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Unsupported op garbage.');

    $payload = $this->getPayload('status');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(200, 'You are logged in.');

    $payload = $this->getPayload('logout');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(200, 'You are logged out.', $basic_auth);

    $payload = $this->getPayload('login');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Missing credentials.');

    $payload = $this->getPayload('login', $name);
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Missing credentials.pass.');

    $payload = $this->getPayload('login', NULL, $pass);
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Missing credentials.name.');

    $payload = $this->getPayload('login', $name, 'garbage');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Sorry, unrecognized username or password.');

    $payload = $this->getPayload('login', 'garbage', $pass);
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(400, 'Sorry, unrecognized username or password.');

    $payload = $this->getPayload('login', $name, $pass);
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(200, "You are logged in as $name");

    $payload = $this->getPayload('status');
    $this->httpRequest('user_login', 'POST', json_encode($payload), $this->defaultMimeType, $basic_auth);
    $this->assertResponseAndText(200, 'You are logged in.');
  }

  protected function assertResponseAndText($code, $text) {
    $this->assertResponse($code);
    $this->assertText($text);
  }

  /**
   * Helper function to build the payload.
   *
   * @param string $op
   * @param string|null $user
   * @param string|null $pass
   * @return array
   *
   * @see UserLoginResource.php
   */
  private function getPayload( $op, $name = NULL, $pass = NULL) {
    $result = array('op' => $op);

    if ($op == 'login') {
      $result['credentials'] = array();
      if (isset($name)) {
        $result['credentials']['name'] = $name;
      }
      if (isset($pass)) {
        $result['credentials']['pass'] = $pass;
      }
    }
    return $result;
  }
}
