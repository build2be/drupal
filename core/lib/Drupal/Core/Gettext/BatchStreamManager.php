<?php

namespace Drupal\Core\Gettext;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;

class BatchStreamManager {

  private $_addition_state = array();
  private $_stream;

  /*
   * @see stream_open
   */

  public function open($uri, $mode, $options, &$opened_url) {
    $s = file_stream_wrapper_get_instance_by_uri($uri);
    if ($s) {
      $s->stream_open($uri, 'r', $options, $opened_url);
      $this->_stream = $s;
      $state = array(
        'uri' => $uri,
        'mode' => $mode,
        'options' => $options,
        'opened_url' => $opened_url,
      );
      $state += $this->_addition_state;
      $this->_addition_state = $state;
    }
  }

  public function getStream() {
    return $this->_stream;
  }

  public function getBatchState() {
    $state = $this->_addition_state;
    if ($this->getStream()) {
      $state['position'] = $this->getStream()->stream_tell();
      $state['uri'] = $this->getStream()->getURI();
    }
    return $state;
  }

  public function setBatchState(array $state = array()) {
    $current_state = $this->getBatchState();
    $state += $current_state;
    $this->_setBatchState($state);
  }

  private function _setBatchState($state) {
    if ($this->getStream()) {
      $this->getStream()->stream_close();
      $this->open($state['uri'], $state['mode'], $state['options'], $opened_url);
      $this->getStream()->stream_seek($state['position'], SEEK_SET);
    }
    return $this->getBatchState();
  }

}
