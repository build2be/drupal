<?php

/**
 * @file
 * Contains \Drupal\datetime\Plugin\views\argument\FullDate.
 */

namespace Drupal\datetime\Plugin\views\argument;

/**
 * Argument handler for a day.
 *
 * @ViewsArgument("datetime_full_date")
 */
class FullDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Ymd';

}
