<?php

/**
 * @file
 * Contains \Drupal\datetime\Plugin\views\argument\YearMonthDate.
 */

namespace Drupal\datetime\Plugin\views\argument;

/**
 * Argument handler for a day.
 *
 * @ViewsArgument("datetime_year_month")
 */
class YearMonthDate extends Date {

  /**
   * {@inheritdoc}
   */
  protected $argFormat = 'Ym';

}
