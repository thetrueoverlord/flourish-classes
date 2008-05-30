<?php
/**
 * Provides file manipulation functionality for {@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMFile
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-05-28]
 */
class fORMFile
{	
	/**
	 * Defines how columns can inherit uploaded files
	 * 
	 * @var array
	 */
	static private $column_inheritence = array();
	
	/**
	 * Methods to be called on fUpload before the file is uploaded
	 * 
	 * @var array
	 */
	static private $fupload_callbacks = array();
	
	/**
	 * Columns that can be filled by file uploads
	 * 
	 * @var array
	 */
	static private $file_upload_columns = array();
	
	/**
	 * Methods to be called on the fImage instance
	 * 
	 * @var array
	 */
	static private $fimage_callbacks = array();
	
	/**
	 * Validation messages thrown during the upload process
	 * 
	 * @var array
	 */
	static private $validation_messages = array();
	
	
	/**
	 * Sets a column to be a date created column
	 * 
	 * @param  mixed             $class      The class name or instance of the class
	 * @param  string            $column     The column to set as a file upload column
	 * @param  fDirectory|string $directory  The directory to upload to
	 * @return void
	 */
	static public function configureFileUploadColumn($class, $column, $directory)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a file upload column.');	
		}
		
		if (!is_object($directory)) {
			$directory = new fDirectory($directory);	
		}
		
		if (!$directory->isWritable()) {
			fCore::toss('fEnvironmentException', 'The file upload directory, ' . $directory->getPath() . ', is not writable');	
		}
		
		$hook     = 'replace::upload' . fInflection::camelize($column, TRUE) . '()';
		$callback = array('fORMFile', 'uploadFile');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$file_upload_columns[$class])) {
			self::$file_upload_columns[$class] = array();	
		}
		
		self::$file_upload_columns[$class][$column] = $directory;
	}
	
	
	/**
	 * Sets a column to be a date updated column
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date updated column
	 * @return void
	 */
	static public function configureDateUpdatedColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a date updated column.');	
		}
		
		$hook     = 'post-begin::store()';
		$callback = array('fORMColumn', 'setDateUpdated');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$date_updated_columns[$class])) {
			self::$date_updated_columns[$class] = array();	
		}
		
		self::$date_updated_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as an email address
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureEmailColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as an email column.');	
		}
		
		$cameled_column = fInflection::camelize($column, TRUE);
		
		$hook     = 'replace::format' . $cameled_column . '()';
		$callback = array('fORMColumn', 'formatEmailColumn');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$email_validation_set[$class])) {
			$hook     = 'post::validate()';
			$callback = array('fORMColumn', 'validateEmailColumns');
			fORM::registerHookCallback($class, $hook, $callback);
			self::$email_validation_set[$class] = TRUE;
		}
		
		if (empty(self::$email_columns[$class])) {
			self::$email_columns[$class] = array();	
		}
		
		self::$email_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as a link
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureLinkColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a link column.');	
		}
		
		$cameled_column = fInflection::camelize($column, TRUE);
		
		$hook     = 'replace::format' . $cameled_column . '()';
		$callback = array('fORMColumn', 'formatLinkColumn');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$link_validation_set[$class])) {
			$hook     = 'post::validate()';
			$callback = array('fORMColumn', 'validateLinkColumns');
			fORM::registerHookCallback($class, $hook, $callback);
			self::$link_validation_set[$class] = TRUE;
		}
		
		if (empty(self::$link_columns[$class])) {
			self::$link_columns[$class] = array();	
		}
		
		self::$link_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a random string column
	 * 
	 * @param  mixed   $class   The class name or instance of the class
	 * @param  string  $column  The column to set as a random column
	 * @param  string  $type    The type of random string, must be one of: 'alphanumeric', 'alpha', 'numeric', 'hexadecimal'
	 * @param  integer $length  The length of the random string
	 * @return void
	 */
	static public function configureRandomColumn($class, $column, $type, $length)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {                                                                                                                       
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a random string column.');	
		}
		
		$valid_types = array('alphanumeric', 'alpha', 'numeric', 'hexadecimal');
		if (!in_array($type, $valid_types)) {
			fCore::toss('fProgrammerException', 'The type, ' . $type . ', must be one of ' . join(', ', $valid_types) . '.');	
		}
		
		if (!is_numeric($length) || $length < 1) {
			fCore::toss('fProgrammerException', 'The length specified, ' . $length . ', needs to be an integer greater than zero.');	
		}
		
		$hook     = 'pre::validate()';
		$callback = array('fORMColumn', 'setRandomStrings');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$random_columns[$class])) {
			self::$random_columns[$class] = array();	
		}
		
		self::$random_columns[$class][$column] = array('type' => $type, 'length' => (int) $length);
	}
	
	
	/**
	 * Formats an email column into an HTML link
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The formatted email address
	 */
	static public function formatEmailColumn($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		if (empty($values[$column])) {
			return $values[$column];
		}
		
		if (sizeof($parameters) > 1) {
			fCore::toss('fProgrammerException', 'The method ' . $method_name . ' accepts at most one parameter');	
		}	
		
		$formatting = (!empty($parameters[0])) ? $parameters[0] : NULL;
		$css_class  = ($formatting) ? ' class="' . $formatting . '"' : '';
		return '<a href="mailto:' . $values[$column] . '"' . $css_class . '>' . $values[$column] . '</a>';
	}
	
	
	/**
	 * Formats a link column into an HTML link
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The formatted link
	 */
	static public function formatLinkColumn($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		if (empty($values[$column])) {
			return $values[$column];
		}	
		
		if (sizeof($parameters) > 1) {
			fCore::toss('fProgrammerException', 'The method ' . $method_name . ' accepts at most one parameter');	
		}
		
		$value = $values[$column];
		
		// Fix domains that don't have the protocol to start
		if (preg_match('#^([a-z0-9\\-]+\.)+[a-z]{2,}(/|$)#i', $value)) {
			$value = 'http://' . $value;
		}
		
		$formatting = (!empty($parameters[0])) ? $parameters[0] : NULL;
		$css_class  = ($formatting) ? ' class="' . $formatting . '"' : '';
		return '<a href="' . $value . '"' . $css_class . '>' . $value . '</a>';
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was created (for new records)
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setDateCreated($class, &$values, &$old_values, &$related_records, $debug)
	{
		if ($class->exists()) {
			return;	
		}
		
		$class = fORM::getClassName($class);
		
		foreach (self::$date_created_columns[$class] as $column => $enabled) {
			$old_values[$column] = $values[$column];
			$values[$column] = date('Y-m-d H:i:s');		
		}
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was updated
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setDateUpdated($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class = fORM::getClassName($class);
		
		foreach (self::$date_updated_columns[$class] as $column => $enabled) {
			$old_values[$column] = $values[$column];
			$values[$column] = date('Y-m-d H:i:s');		
		}
	}
	
	
	/**
	 * Sets the appropriate column values to a random string if the object is new
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setRandomStrings($class, &$values, &$old_values, &$related_records, $debug)
	{
		if ($class->exists()) {
			return;	
		}
		$table = fORM::tablize($class);
		
		$class = fORM::getClassName($class);
		
		foreach (self::$random_columns[$class] as $column => $settings) {
			$old_values[$column] = $values[$column];
			
			// Check to see if this is a unique column
			$unique_keys      = fORMSchema::getInstance()->getKeys($table, 'unique');
			$is_unique_column = FALSE;
			foreach ($unique_keys as $unique_key) {
				if ($unique_key == array($column)) {
					$is_unique_column = TRUE;
					do {
						$values[$column] = fCryptography::generateRandomString($settings['length'], $settings['type']);
						
						// See if this is unique
						$sql = "SELECT " . $column . " FROM " . $table . " WHERE " . $column . " = '" . fORMDatabase::getInstance()->escapeString($values[$column]) . "'";
					
					} while (fORMDatabase::getInstance()->query($sql)->getReturnedRows());
				}
			}
			
			// If is is not a unique column, just generate a value
			if (!$is_unique_column) {
				$values[$column] = fCryptography::generateRandomString($settings['length'], $settings['type']);	
			}
		}
	}
	
	
	/**
	 * Validates all email columns
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The name of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateEmailColumns($class, &$values, &$old_values, &$related_records, $debug, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		if (empty(self::$email_columns[$class])) {
			return;
		}	
		
		foreach (self::$email_columns[$class] as $column => $enabled) {
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#i', $values[$column])) {
				$validation_messages[] = fORM::getColumnName($class_name, $column) . ': Please enter an email address in the form name@example.com';
			}	
		}
	}
	
	
	/**
	 * Validates all link columns
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The name of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateLinkColumns($class, &$values, &$old_values, &$related_records, $debug, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		if (empty(self::$link_columns[$class])) {
			return;
		}	
		
		foreach (self::$link_columns[$class] as $column => $enabled) {
			if (!preg_match('#^(http(s)?://|/|([a-z0-9\\-]+\.)+[a-z]{2,})#i', $values[$column])) {
				$validation_messages[] = fORM::getColumnName($class, $column) . ': Please enter a link in the form http://www.example.com';
			}	
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMColumn
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */