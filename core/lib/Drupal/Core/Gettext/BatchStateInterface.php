<?php

namespace Drupal\Core\Gettext;

/**
 * Add state to an object to manage continue after a previous batch call.
 *
 * The class implementing this must make sure to pass all state.
 * It's constructor must be empty.
 *
 * Example:
 * TODO: add example(s)
 */
interface BatchStateInterface {

  /**
   * Returns the current state used for resetting state later on.
   *
   * The state is used to reconstruct the state of the object by calling
   * setState().
   *
   * The Class implemeting this interface must have an empty constructor.
   *
   * @return array
   *   key/value pairs of which one must be __CLASS__
   */
  function getState();

  /**
   * Sets the object ready to roll.
   *
   * After calling setState it is assumed the object is ready to do it's work.
   */
  function setState(array $state);
}
