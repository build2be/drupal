<?php

$cmd = __FILE__;
$_SERVER['HTTP_HOST'] = 'default';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_SOFTWARE'] = NULL;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_USER_AGENT'] = 'console';

define('DRUPAL_ROOT', getcwd());

include_once DRUPAL_ROOT . '/core/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

/**
 * Run this like:
 * drush @drupal.d8 php-script core/lib/Drupal/Core/Gettext/testGettext.php
 */
use Drupal\Core\Gettext\BatchStreamManager;
use Drupal\Core\Gettext\POHeader;
use Drupal\Core\Gettext\POItem;
use Drupal\Core\Gettext\PODbWriter;
use Drupal\Core\Gettext\PODbReader;
use Drupal\Core\Gettext\PoFileReader;
use Drupal\Core\Gettext\PoFileWriter;
use Drupal\Core\Gettext\POMemoryWriter;

function logLine($string, $type = '-') {
  echo str_repeat($type, 50) . "\n";
  echo str_repeat(" ", 0) . $string . "\n";
  if ($type != '-') {
    echo str_repeat('-', 50) . "\n";
  }
}

/**
 * Creates a test PO stucture
 *
 * TODO: the object structure is for .po so we miss the .pot format
 *
 * @param type $langcode
 * @return array of objects
 */
function gettext_struct($langcode = 'nl') {
  $src = array(
    array(
      'source' => 'home',
      'translation' => 'thuis',
      'plural' => 0,
      'context' => '',
    ),
    array(
      'source' => 'delete',
      'translation' => 'verwijderen',
      'plural' => 0,
      'context' => '',
    ),
    array(
      'source' => array('1 day', '@count days'),
      'translation' => array('1 dag', '@count dagen'),
      'plural' => 1,
      'context' => '',
    ),
  );
  $items = array();
  foreach ($src as $values) {
    $item = new POItem();
    $item->fromArray($values);
    $items[] = $item;
  }
  $result = array(
    'langcode' => $langcode,
    'items' => $items,
  );

  return $result;
}

function testWriter() {
  logLine(__FUNCTION__);
  $po = gettext_struct();
  $langcode = $po['langcode'];
  $items = $po['items'];

  $writer = new PoFileWriter();

  $writer->setLangcode($langcode);
  $writer->setHeader(new POHeader($langcode));
  $writer->setURI(getPublicUri(__FUNCTION__, $langcode));
  $writer->open();
  foreach($items as $item) {
    $writer->writeItem($item);
  }
  $writer->close();
}

function testBatchStreamManager() {
  logLine(__FUNCTION__, '=');
  $uri = 'public://infinite-text-file.txt.po';
  echo file_get_contents($uri);

  echo "Creating new BatchStreamManager()\n";
  $bsm = new BatchStreamManager();
  echo "  state:\n";
  print_r($bsm->getBatchState());

  echo "Opening stream $uri\n";
// $uri, $mode, $options, &$opened_url
  $bsm->open($uri, 'r', 0, $full_path);
  echo "  state:\n";
  print_r($bsm->getBatchState());

  $my_s = $bsm->getStream();
  echo "Reading 10 bytes\n";
  $bytes = $my_s->stream_read(10);
  echo "  Bytes read: '$bytes'\n";

  echo "  state:\n";
  print_r($bsm->getBatchState());

  echo "Moving to position 50\n";
  $state = $bsm->getBatchState();
  $state['position'] = 50;
  $bsm->setBatchState($state);
  $my_s = $bsm->getStream();

  echo "  state:\n";
  print_r($bsm->getBatchState());

  echo "Reading 15 bytes\n";
  $bytes = $my_s->stream_read(15);
  echo "  Bytes read: '$bytes'\n";

  echo "  state:\n";
  print_r($bsm->getBatchState());
}

function getReadStream($uri) {
  logLine(__FUNCTION__, '=');
  $s = new GettextFileInterface($uri);
  return $s;
}

function testPoReader() {
  logLine(__FUNCTION__, '=');

  $uri = 'public://test.po.txt';

  logLine("Reading : $uri", '=');
  logLine("File contents first 500 bytes");
  $contents = file_get_contents($uri);
  echo substr($contents, 0, 500) . "\n";

  logLine("Using PoFileReader");
  $reader = new PoFileReader($uri);
  echo $reader->getHeader();

  $i = 0;
  while (($item = $reader->readItem()) && $i++ < 4) {
    printItem($item, $i);
  }
}

function testHeader() {
  logLine(__FUNCTION__, '=');
  $h = new POHeader();

  echo "----------------\n";
  $h->setFromString('');
  echo "empty header\n";
  echo $h;

  echo "----------------\n";
  $h->setFromString('"Project-Id-Version: ' . __FILE__ . '\n"');
  echo "-- one item -- \n";
  echo $h;
}

function testFileToDb() {
  logLine(__FUNCTION__, '=');
  $uri = 'public://test.po.txt';
  logLine("Reading : $uri");
  logLine("File contents first 500 bytes");
  $contents = file_get_contents($uri);
  echo substr($contents, 0, 500) . "\n";

  logLine("POFileReader");
  $reader = new PoFileReader();
  $reader->setURI($uri);
  printItem($reader->getHeader());

  $langcode = 'ca';
  logLine("PODbWriter");
  $writer = new PODbWriter();
  $writer->setLangcode($langcode);

  $i = 0;
  while (($item = $reader->readItem()) && $i < 4) {
    printItem($item, $i++);
    $writer->writeItem($item);
  }
}

function testDbDump() {
  logLine(__FUNCTION__, '=');
  $reader = new PODbReader('en');
  echo $reader->getHeader() . "\n";

  $i = 0;
  while (($item = $reader->readItem()) && $i < 4) {
    printItem($item, $i++);
  }

  echo "Saving state to simulate a batch\n";
  $state = $reader->getState();

  echo "Create a new PODbReader so simulate a batch\n";
  $reader = new PODbReader('en');

  // Set the state
  $reader->setState($state);
  $i = 0;
  while (($item = $reader->readItem()) && $i < 4) {
    printItem($item, $i++);
  }
}

function printItem($item, $context = 0) {
  logLine(__FUNCTION__, '=');
  if ($item) {
    logLine("$context : $item->lid");
    print_r($item);
  }
}

function readItem($reader, $context = 0) {
  $item = $reader->readItem();
  if ($item) {
    printItem($item);
  }
}

function dumpState($state) {
  logLine(__FUNCTION__, '=');
  print_r($state);
}

function testPOFileReader() {
  logLine(__FUNCTION__, '=');
  $uri = 'public://nl-nl.po';

  $reader = new PoFileReader($uri);
  dumpState($reader->getState());
  echo $reader->getHeader() . "\n";
  dumpState($reader->getState());
  $i = 0;
  while (($item = readItem($reader)) && $i < 4) {
    printItem($item, $i++);
  }
  dumpState($reader->getState());
}

function getLanguages($langcode = NULL) {
  logLine(__FUNCTION__, '=');
  if (!is_null($langcode)) {
    return array($langcode);
  }
  return array(
    'nl',
    'ar',
    'ca',
    'en', // does not exists on d.o (should it be?)
    'NOP', // does really not exists on d.o
  );
}

function getRemoteUris($langcode = NULL) {
  logLine(__FUNCTION__, '=');
  $langcodes = getLanguages($langcode);
  $uris = array();
  foreach ($langcodes as $langcode) {
    $uri = "http://ftp.drupal.org/files/translations/7.x/drupal/drupal-7.11.$langcode.po";
    $uris[$langcode] = $uri;
  }
  return $uris;
}

function getPublicUri($name, $langcode) {
  $result = "public://$name-$langcode.po";
  logLine($result);
  return $result;
}

function getRemoteUri($langcode) {
  $uris = getRemoteUris($langcode);
  return $uris[$langcode];
}

function testRemotePOPumper() {
  logLine(__FUNCTION__, '=');
  $uris = getRemoteUris();
  foreach ($uris as $langcode => $uri) {
    logLine("langcode: $langcode");
    $reader = new PoFileReader($uri);
    $writer = new PODbWriter($langcode);
    $writer->writeItems($reader, 10);
  }
}

function testPOFileWriter() {
  logLine(__FUNCTION__, '=');
  $src = "http://ftp.drupal.org/files/translations/7.x/drupal/drupal-7.11.ar.po";

  $reader = new PoFileReader();
  $reader->setURI($src);
  $reader->open();
  $header = $reader->getHeader();

  $dst = 'public://drupal-7.11.ar.po';
  zapUri($dst);
  $writer = new PoFileWriter();
  $writer->setURI($dst);
  $writer->setHeader($header);
  $writer->open();

  $i = 0;
  while (($item = $reader->readItem()) && $i < 4) {
    printItem($item, $i);
    $i++;
    $writer->writeItem($item);
    dumpState($writer->getState());
  }

  $writer->close();
}

function testDbToFile() {
  logLine(__FUNCTION__, '=');
  $langcode = 'ca';
  $reader = new PODbReader();
  $reader->setLangcode($langcode);

  $dst = 'public://drupal-7.11.dummy.po';
  $header = $reader->getHeader();

  $writer = new PoFileWriter();
  $writer->setURI($dst);
  $writer->setHeader($header);
  $writer->open();

  $i = 0;
  while (($item = $reader->readItem()) && $i < 4) {
    printItem($item, $i);
    $i++;
    $writer->writeItem($item);
    dumpState($writer->getState());
  }

  $writer->writeItems($reader, 10);

  $writer->close();
}

function testBatchSimulation() {
  logLine(__FUNCTION__, '=');

  // Grab first langcode
  $uris = getRemoteUris();
  $langcode = key($uris);
  $src = current($uris);

  logLine("Opening $langcode : $src");
  $reader = new PoFileReader();
  $reader->setURI($src);
  $reader->open();

  $header = $reader->getHeader();
  logLine($header);

  $dst = getPublicUri(__FUNCTION__, $langcode);

  zapUri($dst);
  logLine("Writing $langcode : $dst");
  $writer = new PoFileWriter();
  $writer->setURI($dst);
  $writer->setHeader($header);
  $writer->open();

  logLine('Written header only', '=');
  echo file_get_contents($dst);

  processN($writer, $reader, 2);

  dumpFileContents($dst);

  $state = $reader->getState();
  dumpState($state);

  logLine('Replacing reader', '=');
  $reader = new PoFileReader($src);

  logLine('setting state back');
  $reader->setState($state);
  dumpState($state);

  processN($writer, $reader, 3);
  $reader->setState($state);
  dumpState($state);

  dumpFileContents($dst);
}

function testDBReaderState() {
  logLine(__FUNCTION__);
  $langcode = 'nl';
  $reader = new PODbReader();
  $reader->setLangcode($langcode);

  logLine("Init PODbReader", '=');
  $state = $reader->getState();
  dumpState($state);

  $header = $reader->getHeader();

  $uri = getPublicUri(__FUNCTION__, $langcode);
  zapUri($uri);
  $writer = new PoFileWriter($uri, $header);
  $writer->setHeader($header);
  $writer->setURI($uri);
  $writer->open();

  processN($writer, $reader, 4);

  logLine("Read some", '=');
  $state = $reader->getState();
  dumpState($state);

  $reader = new PODbReader($langcode);
  $reader->setState($state);
  processN($writer, $reader, 4);
  $state = $reader->getState();
  dumpState($state);

  logLine("File contents from $uri", '=');
  echo file_get_contents($uri);
}

function zapUri($uri) {
  logLine("Truncate $uri", '=');
  ftruncate(fopen($uri, 'w'));
}

function processN($writer, $reader, $count = 10) {
  if ($count == -1) {
    logLine("processing items: __ALL__");
  }
  else {
    logLine("processing items: $count");
  }
  $writer->writeItems($reader, $count);
}

function dumpFileContents($uri) {
  logLine("Written: $uri", '=');
  echo file_get_contents($uri);
}

function newPOFileReader($uri, $langcode = NULL) {
  logLine("Reading from $uri using langcode: '$langcode'");
  $reader = new PoFileReader();
  $reader->setURI($uri);
  $reader->setLangcode($langcode);
  $reader->open();
  return $reader;
}

function remoteToPublic($langcode) {
  logLine(__FUNCTION__, '=');
  $uri = getRemoteUri($langcode);

  logLine("Reading from $uri");
  $reader = newPoFileReader($uri, $langcode);
  $header = $reader->getHeader();

  $uri = getPublicUri($langcode, $langcode);
  zapUri($uri);
  logLine("Writing to $uri");
  $writer = new PoFileWriter();
  $writer->setURI($uri);
  $writer->setHeader($header);
  $writer->open();

  processN($writer, $reader, -1);

  return $uri;
}

function publicToDb($langcode) {
  $uri = getPublicUri($langcode, $langcode);

  logLine("Reading from $uri using langcode: '$langcode'");
  $reader = new PoFileReader();
  $reader->setURI($uri);
  $reader->setLangcode($langcode);
  $reader->open();

  logLine("Writing to DB");
  $writer = new PODbWriter();
  $writer->setLangcode($langcode);
  $writer->setHeader($reader->getHeader());

  $locale_plurals = variable_get('locale_translation_plurals', array());
  print_r(array("Should have $langcode" => $locale_plurals));

  $options = $writer->getOptions();
  print_r($writer->getOptions());
  //$options['overwrite_options']['not_customized'] = TRUE;
  $writer->setOptions($options);
  print_r($writer->getOptions());
  print_r($options);
  processN($writer, $reader, -1);

  print_r($writer->getReport());
}

function publicToMemory($langcode) {
  $uri = getPublicUri($langcode, $langcode);

  logLine("Reading from $uri using langcode: '$langcode'");
  $reader = new PoFileReader();
  $reader->setURI($uri);
  $reader->open();

  logLine("Writing to Memory");
  $writer = new POMemoryWriter();
  $writer->setLangcode($langcode);
  $writer->setHeader($reader->getHeader());

  $locale_plurals = variable_get('locale_translation_plurals', array());
  print_r(array("Should have $langcode" => $locale_plurals));

  processN($writer, $reader, 5);

  var_dump($writer);
}

function pumpAround($langcode) {
  $uri = remoteToPublic($langcode);

  $reader = newPoFileReader($uri, $langcode);

  logLine("Writing to DB");
  $writer = new PODbWriter();
  $writer->setLangcode($langcode);

  processN($writer, $reader, -1);


  logLine("Reading from DB");
  $reader = new PODbReader();
  $reader->setLangcode($langcode);
  $reader->setOptions(array());

  var_dump($reader->getOptions());

  $header = $reader->getHeader();

  $uri = getPublicUri(__FUNCTION__ . '-db', $langcode);
  zapUri($uri);
  logLine("Writing to $uri");
  $writer = new PoFileWriter();
  $writer->setURI($uri);
  $writer->setHeader($header);
  $writer->open();
  processN($writer, $reader, -1);
}

function runAll($langcode = 'nl') {
  testWriter();
  testDBReaderState();
  testBatchStreamManager();
  testPoReader();
  testHeader();
  testFileToDb();
  testDbDump();
  testPOFileReader();
  testPOFileWriter();
  testDbToFile();
  testRemotePOPumper();
  testBatchSimulation();
  publicToMemory($langcode);
  remoteToPublic($langcode);
  publicToDb($langcode);

  pumpAround('ar');
  pumpAround('ca');
  pumpAround('nl');
}

$langcode = 'nl';
//runAll();

function testT() {
  $result = t('May', array(), array('langcode' => 'hr'));
}

//testT();

remoteToPublic('af');
