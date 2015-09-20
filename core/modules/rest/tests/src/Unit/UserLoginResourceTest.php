<?php

/**
 * @file
 * Contains \Drupal\Tests\rest\Unit\UserLoginResourceTest.
 */

namespace Drupal\Tests\rest\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\rest\Plugin\rest\resource\UserLoginResource;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Login Resource.
 * @TODO Test flood control after https://www.drupal.org/node/2431357 has landed.
 * @group rest
 */
class UserLoginResourceTest extends UnitTestCase {

  protected $testClass;
  protected $flood;
  protected $logger;
  protected $reflection;
  protected $config;
  protected $testClassMock;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $user_auth_service = $this->getMock('Drupal\user\UserAuthInterface');
    $user_auth_service->expects($this->any())
      ->method('authenticate')
      ->will($this->returnValue(FALSE));

    $container = new ContainerBuilder();
    $container->set('user.auth', $user_auth_service);
    \Drupal::setContainer($container);

    $this->flood = $this->getMock('\Drupal\Core\Flood\FloodInterface');

    $this->config = $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();

    $immutableConfig = $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();

    $this->config->expects($this->any())
      ->method('get')
      ->will($this->returnValue($immutableConfig));

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->testClass = new UserLoginResource([], 'plugin_id', '', [], $this->logger, $this->config,  $this->flood);

    $this->testClassMock = $this->getMockBuilder('\Drupal\rest\Plugin\rest\resource\UserLoginResource')
      ->setMethods(['restFloodControl', 'login', 'logout', 'post', 'userIsBlocked'])
      ->setConstructorArgs([[], 'plugin_id', '', [], $this->logger, $this->config,  $this->flood])
      ->getMock();

    $this->reflection = new \ReflectionClass($this->testClass);
  }

  /**
   * Gets a protected method from current class using reflection.
   *
   * @param $method
   * @return mixed
   */
  public function getProtectedMethod($method) {
    $method = $this->reflection->getMethod($method);
    $method->setAccessible(TRUE);

    return $method;
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage No op found. Use: status, login, logout.
   */
  public function testEmptyPayload() {
    $this->testClass->post([]);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Missing credentials.
   */
  public function testMissingCredentials() {
    $this->testClass->post(['op'=>'login']);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Unsupported op UnsuportedOp.
   */
  public function testUnsupportedOp() {
    $this->testClass->post(['op'=>'UnsuportedOp']);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Missing credentials.
   */
  public function testLoginMissingCredentialName() {
    $method = $this->getProtectedMethod('login');
    $method->invokeArgs($this->testClass, ['credentials' => []]);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Missing credentials.pass.
   */
  public function testLoginMissingCredentialPass() {
    $method = $this->getProtectedMethod('login');
    $method->invokeArgs($this->testClass, ['credentials' => ['name' => 'Druplicon']]);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Blocked.
   */
  public function testLoginBlockedUserByFloodControl() {
    $this->testClassMock->expects($this->once())
      ->method('restFloodControl')
      ->will($this->returnValue(TRUE));

    $method = $this->getProtectedMethod('login');
    $method->invokeArgs($this->testClassMock, ['credentials' => ['name' => 'Druplicon', 'pass' => 'SuperSecret']]);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage The user has not been activated or is blocked.
   */
  public function testLoginBlockedUser() {
    $this->testClassMock->expects($this->once())
      ->method('restFloodControl')
      ->will($this->returnValue(FALSE));

    $this->testClassMock->expects($this->once())
      ->method('userIsBlocked')
      ->will($this->returnValue(TRUE));

    $method = $this->getProtectedMethod('login');
    $method->invokeArgs($this->testClassMock, ['credentials' => ['name' => 'Druplicon', 'pass' => 'SuperSecret']]);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @expectedExceptionMessage Sorry, unrecognized username or password.
   */
  public function testLoginUnrecognizedUsernameOrPassword() {
    $this->testClassMock->expects($this->once())
      ->method('restFloodControl')
      ->will($this->returnValue(FALSE));

    $this->testClassMock->expects($this->once())
      ->method('userIsBlocked')
      ->will($this->returnValue(FALSE));

    $method = $this->getProtectedMethod('login');
    $method->invokeArgs($this->testClassMock, ['credentials' => ['name' => 'Druplicon', 'pass' => 'SuperSecret']]);
  }
}

