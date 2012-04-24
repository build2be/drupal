<?php

/**
 * @file
 * Experimental "sketchy" code for gettext parsing and generating API.
 * For a world without failures, exceptions and certainly without users ;)
 */
use Drupal\Core\Gettext\GettextFileInterface;
use Drupal\Core\Gettext\PoWriter;
use Drupal\Core\Gettext\PoReader;

/**
 * Example code to import a po file into the database.
 *
 * Import a po file into the Database.
 * The file may be local, remote (via stream wrapper) or even XML.
 * The import has a small memory footprint.
 * The import may be split up to enable batch handling.
 */
$file = new GettextFileInterface('public://source.po.txt');
gettext_import($source, $langcode);

/**
 * Imports translations from an external source into the database.
 */
function gettext_import($file, $langcode) {
  // Setup a reader to fetch po formatted data from the file.
  $source = new PoReader($file);

  // Set up a (read/)write interface to write data into the database.
  // To make the architecture more consistent this would be:
  //   $database = new GettextDatabaseInterface();
  //   $destination = new DatabaseWriter($database);
  $destination = new GettextDatabase($langcode);

  // Transfer meta data and translations from the file to the database.
  gettext_transfer($source, $destination);
}

/**
 * Example code to export translations to a po file.
 *
 * Export gettext data to a PO file.
 * The file may be local, remote (via stream wrapper) or even XML.
 * The import has a small memory footprint.
 * The import may be split up to enable batch handling.
 */
$file = new GettextFileInterface('public://destination.po.txt');
gettext_export($file, $langcode);

/**
 * Exports translations from the database to a po file.
 */
function gettext_export($file, $langcode) {
  $destination = new PoWriter($file, $langcode);

  // Set up a read(/write) interface to read data from the database.
  // To make the architecture more consistent this would be:
  //   $database = new GettextDatabaseInterface();
  //   $destination = new DatabaseReader($database);
  $source = new GettextDatabase($langcode);

  // Transfer meta data and translations from the database to the file.
  gettext_transfer($source, $destination);
}

/**
 * Transfers gettext data from source to destination.
 */
function gettext_transfer($source, $destination, $langcode) {
  // Transfer translations.
  // Use batch processing if both source and destination support it and the source
  // is large enough. If not, process in once. The threshold for batch processing
  // could dynamically and determined by source and target. But for simplicity it is fixed.
  if ($source->supportBatch() && $destination->supportBatch() && $source->size() > $threshold) {
    // Transfer data in batches.
    // Built and execute batch.
    $batch = gettext_transfer_batch_setup($source, $destination);
    batch_set($batch);
  }
  else {
    // Transfer all translations without interruption.
    // Process the translations one by one to keep the memory footprint low.
    while ($translation = $source->read()) {
      $destination->write($translation);
    }
  }
}

/**
 * Set up a batch process to transfer Gettext data.
 */
function gettext_transfer_batch_setup($source, $destination) {
  $batch = array(
    'operations' => array(
      array('gettext_transfer_batch_op', array($source, $destination)),
    ),
    'finished' => 'gettext_transfer_batch_finished',
  );
  return $batch;
}

/**
 * Batch operation transferring gettext data.
 *
 * @todo Add source filter conditions (context, language(?)) as parameter.
 */
function gettext_transfer_batch_op($source, $destination) {
  // Initialize sandbox for batch reading.
  if (empty($context['sandbox'])) {
    $context['sandbox']['transfer'] = array(
      'chunk' => min($source->chunkSize(), $destination->chunkSize()),
    );
  }

  // Execute one transfer cycle, which will process bite size 'chunks'.
  $success = gettext_transfer_execute($source, $destination, $context['sandbox']['transfer']);

  // After a number of cycles executing gettext_transfer_execute() the transfer
  // is completed.
  // See gettextapi_import_batch_op() for details.
  if ($context['sandbox']['transfer']['finished']) {
    $context['finished'] = 1;

    if ($success) {
      // Collect statistics from $context['sandbox']['transfer']
      // and tell the world about what great work we did.
    }
  }
}

/**
 * Batch wrap-up for gettext data transfer.
 */
function gettext_transfer_batch_finished($success, $results, $operations) {
  if ($success) {
    // Call post processing handler defined by the destination object.
    // Can we get the callback and arguments via $results?
    $post_process_callback = $results['post_process_callback'];
    $arguments = $results['post_process_arguments'];
    $post_process_callback($arguments);

    // We did it, Magoo! :)
  }
  else {
    // Sadly reporting where it went wrong.
  }
}

/**
 * Post processing for gettext language import into the database.
 *
 * To be called after successfull (batch) import.
 */
function gettext_post_process_import($langcode) {
  // After succesfull import we refresh all affected parts of the system.
  _locale_invalidate_js($langcode);
  cache_clear_all('locale:', 'cache', TRUE);
  menu_rebuild();
}

/**
 * Transfer gettext data from source to destination in bite size chunks.
 */
function gettext_transfer_execute($source, $destination, &$transfer) {
  // If transfer is not yet started we set the header data.
  // The header (if supported) is written before the first translation is written.
  if (!$destination->inProgress()) {
    // Transfer translation header data.
    $destination->setMetaData($source->getMetaData(), $langcode);
  }

  $chunk = $transfer['chunk'];
  // Transfer translations as long as valid data is available and
  // the bite size chunk is not yet swallowed.
  while ($source->valid() && $chunk > 0) {
    // Get one translation from source, write it to destination.
    $translation = $source->read();
    $destination->write($translation);
    $chunk--;
  }

  // Report the percentage of completion for progress reporting.
  // Ai, user interface. Why do we bother ;)
  $transfer['state']['percentage_of_completion'] = $source->poc();

  // Close connections when we are done and report the results.
  if ($source->finished()) {
    $destination->finish();
    $transfer['finished'] = TRUE;
  }

  // Report statistics including errors and error log.
  $transfer['result'] = $destination->statistics();
}
