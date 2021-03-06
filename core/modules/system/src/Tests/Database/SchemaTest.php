<?php

/**
 * @file
 * Contains Drupal\system\Tests\Database\SchemaTest.
 */

namespace Drupal\system\Tests\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests table creation and modification via the schema API.
 *
 * @group Database
 */
class SchemaTest extends KernelTestBase {

  /**
   * A global counter for table and field creation.
   */
  var $counter;

  /**
   * Tests database interactions.
   */
  function testSchema() {
    // Try creating a table.
    $table_specification = array(
      'description' => 'Schema table description.',
      'fields' => array(
        'id'  => array(
          'type' => 'int',
          'default' => NULL,
        ),
        'test_field'  => array(
          'type' => 'int',
          'not null' => TRUE,
          'description' => 'Schema column description.',
        ),
        'test_field_string'  => array(
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => "'\"funky default'\"",
          'description' => 'Schema column description for string.',
        ),
      ),
    );
    db_create_table('test_table', $table_specification);

    // Assert that the table exists.
    $this->assertTrue(db_table_exists('test_table'), 'The table exists.');

    // Assert that the table comment has been set.
    $this->checkSchemaComment($table_specification['description'], 'test_table');

    // Assert that the column comment has been set.
    $this->checkSchemaComment($table_specification['fields']['test_field']['description'], 'test_table', 'test_field');

    // An insert without a value for the column 'test_table' should fail.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Add a default value to the column.
    db_field_set_default('test_table', 'test_field', 0);
    // The insert should now succeed.
    $this->assertTrue($this->tryInsert(), 'Insert with a default succeeded.');

    // Remove the default.
    db_field_set_no_default('test_table', 'test_field');
    // The insert should fail again.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Test for fake index and test for the boolean result of indexExists().
    $index_exists = Database::getConnection()->schema()->indexExists('test_table', 'test_field');
    $this->assertIdentical($index_exists, FALSE, 'Fake index does not exists');
    // Add index.
    db_add_index('test_table', 'test_field', array('test_field'));
    // Test for created index and test for the boolean result of indexExists().
    $index_exists = Database::getConnection()->schema()->indexExists('test_table', 'test_field');
    $this->assertIdentical($index_exists, TRUE, 'Index created.');

    // Rename the table.
    db_rename_table('test_table', 'test_table2');

    // Index should be renamed.
    $index_exists = Database::getConnection()->schema()->indexExists('test_table2', 'test_field');
    $this->assertTrue($index_exists, 'Index was renamed.');

    // Copy the schema of the table.
    db_copy_table_schema('test_table2', 'test_table3');

    // Index should be copied.
    $index_exists = Database::getConnection()->schema()->indexExists('test_table3', 'test_field');
    $this->assertTrue($index_exists, 'Index was copied.');

    // Data should still exist on the old table but not on the new one.
    $count = db_select('test_table2')->countQuery()->execute()->fetchField();
    $this->assertEqual($count, 1, 'The old table still has its content.');
    $count = db_select('test_table3')->countQuery()->execute()->fetchField();
    $this->assertEqual($count, 0, 'The new table has no content.');

    // Ensure that the proper exceptions are thrown for db_copy_table_schema().
    $fail = FALSE;
    try {
      db_copy_table_schema('test_table4', 'test_table5');
    }
    catch (SchemaObjectDoesNotExistException $e) {
      $fail = TRUE;
    }
    $this->assertTrue($fail, 'Ensure that db_copy_table_schema() throws an exception when the source table does not exist.');

    $fail = FALSE;
    try {
      db_copy_table_schema('test_table2', 'test_table3');
    }
    catch (SchemaObjectExistsException $e) {
      $fail = TRUE;
    }
    $this->assertTrue($fail, 'Ensure that db_copy_table_schema() throws an exception when the destination table already exists.');

    // We need the default so that we can insert after the rename.
    db_field_set_default('test_table2', 'test_field', 0);
    $this->assertFalse($this->tryInsert(), 'Insert into the old table failed.');
    $this->assertTrue($this->tryInsert('test_table2'), 'Insert into the new table succeeded.');

    // We should have successfully inserted exactly two rows.
    $count = db_query('SELECT COUNT(*) FROM {test_table2}')->fetchField();
    $this->assertEqual($count, 2, 'Two fields were successfully inserted.');

    // Try to drop the table.
    db_drop_table('test_table2');
    $this->assertFalse(db_table_exists('test_table2'), 'The dropped table does not exist.');

    // Recreate the table.
    db_create_table('test_table', $table_specification);
    db_field_set_default('test_table', 'test_field', 0);
    db_add_field('test_table', 'test_serial', array('type' => 'int', 'not null' => TRUE, 'default' => 0, 'description' => 'Added column description.'));

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Added column description.', 'test_table', 'test_serial');

    // Change the new field to a serial column.
    db_change_field('test_table', 'test_serial', 'test_serial', array('type' => 'serial', 'not null' => TRUE, 'description' => 'Changed column description.'), array('primary key' => array('test_serial')));

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Changed column description.', 'test_table', 'test_serial');

    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max1 = db_query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max2 = db_query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($max2 > $max1, 'The serial is monotone.');

    $count = db_query('SELECT COUNT(*) FROM {test_table}')->fetchField();
    $this->assertEqual($count, 2, 'There were two rows.');

    // Use database specific data type and ensure that table is created.
    $table_specification = array(
      'description' => 'Schema table description.',
      'fields' => array(
        'timestamp'  => array(
          'mysql_type' => 'timestamp',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'datetime',
          'not null' => FALSE,
          'default' => NULL,
        ),
      ),
    );
    try {
      db_create_table('test_timestamp', $table_specification);
    }
    catch (\Exception $e) {}
    $this->assertTrue(db_table_exists('test_timestamp'), 'Table with database specific datatype was created.');
  }

  /**
   * Tests inserting data into an existing table.
   *
   * @param $table
   *   The database table to insert data into.
   *
   * @return
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  function tryInsert($table = 'test_table') {
    try {
      db_insert($table)
        ->fields(array('id' => mt_rand(10, 20)))
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Checks that a table or column comment matches a given description.
   *
   * @param $description
   *   The asserted description.
   * @param $table
   *   The table to test.
   * @param $column
   *   Optional column to test.
   */
  function checkSchemaComment($description, $table, $column = NULL) {
    if (method_exists(Database::getConnection()->schema(), 'getComment')) {
      $comment = Database::getConnection()->schema()->getComment($table, $column);
      $this->assertEqual($comment, $description, 'The comment matches the schema description.');
    }
  }

  /**
   * Tests creating unsigned columns and data integrity thereof.
   */
  function testUnsignedColumns() {
    // First create the table with just a serial column.
    $table_name = 'unsigned_table';
    $table_spec = array(
      'fields' => array('serial_column' => array('type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE)),
      'primary key' => array('serial_column'),
    );
    db_create_table($table_name, $table_spec);

    // Now set up columns for the other types.
    $types = array('int', 'float', 'numeric');
    foreach ($types as $type) {
      $column_spec = array('type' => $type, 'unsigned'=> TRUE);
      if ($type == 'numeric') {
        $column_spec += array('precision' => 10, 'scale' => 0);
      }
      $column_name = $type . '_column';
      $table_spec['fields'][$column_name] = $column_spec;
      db_add_field($table_name, $column_name, $column_spec);
    }

    // Finally, check each column and try to insert invalid values into them.
    foreach ($table_spec['fields'] as $column_name => $column_spec) {
      $this->assertTrue(db_field_exists($table_name, $column_name), format_string('Unsigned @type column was created.', array('@type' => $column_spec['type'])));
      $this->assertFalse($this->tryUnsignedInsert($table_name, $column_name), format_string('Unsigned @type column rejected a negative value.', array('@type' => $column_spec['type'])));
    }
  }

  /**
   * Tries to insert a negative value into columns defined as unsigned.
   *
   * @param $table_name
   *   The table to insert.
   * @param $column_name
   *   The column to insert.
   *
   * @return
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  function tryUnsignedInsert($table_name, $column_name) {
    try {
      db_insert($table_name)
        ->fields(array($column_name => -1))
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Tests adding columns to an existing table.
   */
  function testSchemaAddField() {
    // Test varchar types.
    foreach (array(1, 32, 128, 256, 512) as $length) {
      $base_field_spec = array(
        'type' => 'varchar',
        'length' => $length,
      );
      $variations = array(
        array('not null' => FALSE),
        array('not null' => FALSE, 'default' => '7'),
        array('not null' => FALSE, 'default' => substr('"thing"', 0, $length)),
        array('not null' => FALSE, 'default' => substr("\"'hing", 0, $length)),
        array('not null' => TRUE, 'initial' => 'd'),
        array('not null' => TRUE, 'initial' => 'd', 'default' => '7'),
      );

      foreach ($variations as $variation) {
        $field_spec = $variation + $base_field_spec;
        $this->assertFieldAdditionRemoval($field_spec);
      }
    }

    // Test int and float types.
    foreach (array('int', 'float') as $type) {
      foreach (array('tiny', 'small', 'medium', 'normal', 'big') as $size) {
        $base_field_spec = array(
          'type' => $type,
          'size' => $size,
        );
        $variations = array(
          array('not null' => FALSE),
          array('not null' => FALSE, 'default' => 7),
          array('not null' => TRUE, 'initial' => 1),
          array('not null' => TRUE, 'initial' => 1, 'default' => 7),
        );

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $this->assertFieldAdditionRemoval($field_spec);
        }
      }
    }

    // Test numeric types.
    foreach (array(1, 5, 10, 40, 65) as $precision) {
      foreach (array(0, 2, 10, 30) as $scale) {
        if ($precision <= $scale) {
          // Precision must be smaller then scale.
          continue;
        }

        $base_field_spec = array(
          'type' => 'numeric',
          'scale' => $scale,
          'precision' => $precision,
        );
        $variations = array(
          array('not null' => FALSE),
          array('not null' => FALSE, 'default' => 7),
          array('not null' => TRUE, 'initial' => 1),
          array('not null' => TRUE, 'initial' => 1, 'default' => 7),
        );

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $this->assertFieldAdditionRemoval($field_spec);
        }
      }
    }
  }

  /**
   * Asserts that a given field can be added and removed from a table.
   *
   * The addition test covers both defining a field of a given specification
   * when initially creating at table and extending an existing table.
   *
   * @param $field_spec
   *   The schema specification of the field.
   */
  protected function assertFieldAdditionRemoval($field_spec) {
    // Try creating the field on a new table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_spec = array(
      'fields' => array(
        'serial_column' => array('type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE),
        'test_field' => $field_spec,
      ),
      'primary key' => array('serial_column'),
    );
    db_create_table($table_name, $table_spec);
    $this->pass(format_string('Table %table created.', array('%table' => $table_name)));

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec);

    // Clean-up.
    db_drop_table($table_name);

    // Try adding a field to an existing table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_spec = array(
      'fields' => array(
        'serial_column' => array('type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE),
      ),
      'primary key' => array('serial_column'),
    );
    db_create_table($table_name, $table_spec);
    $this->pass(format_string('Table %table created.', array('%table' => $table_name)));

    // Insert some rows to the table to test the handling of initial values.
    for ($i = 0; $i < 3; $i++) {
      db_insert($table_name)
        ->useDefaults(array('serial_column'))
        ->execute();
    }

    db_add_field($table_name, 'test_field', $field_spec);
    $this->pass(format_string('Column %column created.', array('%column' => 'test_field')));

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec);

    // Clean-up.
    db_drop_field($table_name, 'test_field');
    db_drop_table($table_name);
  }

  /**
   * Asserts that a newly added field has the correct characteristics.
   */
  protected function assertFieldCharacteristics($table_name, $field_name, $field_spec) {
    // Check that the initial value has been registered.
    if (isset($field_spec['initial'])) {
      // There should be no row with a value different then $field_spec['initial'].
      $count = db_select($table_name)
        ->fields($table_name, array('serial_column'))
        ->condition($field_name, $field_spec['initial'], '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEqual($count, 0, 'Initial values filled out.');
    }

    // Check that the default value has been registered.
    if (isset($field_spec['default'])) {
      // Try inserting a row, and check the resulting value of the new column.
      $id = db_insert($table_name)
        ->useDefaults(array('serial_column'))
        ->execute();
      $field_value = db_select($table_name)
        ->fields($table_name, array($field_name))
        ->condition('serial_column', $id)
        ->execute()
        ->fetchField();
      $this->assertEqual($field_value, $field_spec['default'], 'Default value registered.');
    }

    db_drop_field($table_name, $field_name);
  }
}
