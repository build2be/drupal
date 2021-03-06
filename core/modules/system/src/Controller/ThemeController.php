<?php

/**
 * @file
 * Contains \Drupal\system\Controller\ThemeController.
 */

namespace Drupal\system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for theme handling.
 */
class ThemeController extends ControllerBase {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a new ThemeController.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   */
  public function __construct(ThemeHandlerInterface $theme_handler, RouteBuilderInterface $route_builder) {
    $this->themeHandler = $theme_handler;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('theme_handler'),
      $container->get('router.builder')
    );
  }

  /**
   * Disables a theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name and a valid token.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects back to the appearance admin page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme or token is set in the request or when
   *   the token is invalid.
   */
  public function disable(Request $request) {
    $theme = $request->get('theme');
    $config = $this->config('system.theme');

    if (isset($theme)) {
      // Get current list of themes.
      $themes = $this->themeHandler->listInfo();

      // Check if the specified theme is one recognized by the system.
      if (!empty($themes[$theme])) {
        // Do not disable the default or admin theme.
        if ($theme === $config->get('default') || $theme === $config->get('admin')) {
          drupal_set_message($this->t('%theme is the default theme and cannot be disabled.', array('%theme' => $themes[$theme]->info['name'])), 'error');
        }
        else {
          $this->themeHandler->disable(array($theme));
          drupal_set_message($this->t('The %theme theme has been disabled.', array('%theme' => $themes[$theme]->info['name'])));
        }
      }
      else {
        drupal_set_message($this->t('The %theme theme was not found.', array('%theme' => $theme)), 'error');
      }

      return $this->redirect('system.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Enables a theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name and a valid token.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects back to the appearance admin page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme or token is set in the request or when
   *   the token is invalid.
   */
  public function enable(Request $request) {
    $theme = $request->get('theme');

    if (isset($theme)) {
      if ($this->themeHandler->enable(array($theme))) {
        $themes = $this->themeHandler->listInfo();
        drupal_set_message($this->t('The %theme theme has been enabled.', array('%theme' => $themes[$theme]->info['name'])));
      }
      else {
        drupal_set_message($this->t('The %theme theme was not found.', array('%theme' => $theme)), 'error');
      }

      return $this->redirect('system.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Set the default theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects back to the appearance admin page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme is set in the request.
   */
  public function setDefaultTheme(Request $request) {
    $config = $this->config('system.theme');
    $theme = $request->query->get('theme');

    if (isset($theme)) {
      // Get current list of themes.
      $themes = $this->themeHandler->listInfo();

      // Check if the specified theme is one recognized by the system.
      // Or try to enable the theme.
      if (isset($themes[$theme]) || $this->themeHandler->enable(array($theme))) {
        $themes = $this->themeHandler->listInfo();

        // Set the default theme.
        $config->set('default', $theme)->save();

        $this->routeBuilder->setRebuildNeeded();

        // The status message depends on whether an admin theme is currently in
        // use: a value of 0 means the admin theme is set to be the default
        // theme.
        $admin_theme = $config->get('admin');
        if ($admin_theme != 0 && $admin_theme != $theme) {
          drupal_set_message($this->t('Please note that the administration theme is still set to the %admin_theme theme; consequently, the theme on this page remains unchanged. All non-administrative sections of the site, however, will show the selected %selected_theme theme by default.', array(
            '%admin_theme' => $themes[$admin_theme]->info['name'],
            '%selected_theme' => $themes[$theme]->info['name'],
          )));
        }
        else {
          drupal_set_message($this->t('%theme is now the default theme.', array('%theme' => $themes[$theme]->info['name'])));
        }
      }
      else {
        drupal_set_message($this->t('The %theme theme was not found.', array('%theme' => $theme)), 'error');
      }

      return $this->redirect('system.themes_page');

    }
    throw new AccessDeniedHttpException();
  }

}
