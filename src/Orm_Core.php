<?php
require_once("Orm_Settings.php");
require_once("Orm_Class.php");
require_once("drivers/Orm_DbDriverFactory.php");
/**
 * A basic orm class which uses PDO to provide simple and easy object relation mapping.
 * Note that every used object should have a variable $id and all vars should be public.
 *
 * @author j.smit <j.smit@sgoen.nl>
 */
class Orm_Core
{
	/**
	 * @var PDO $pdo Holds the PDO object used for database interaction.
	 */
	protected $pdo;
	
	/**
	 * @var array() $transactionData Temporary holds all queries stored for a transaction.
	 */
	protected $transactionData;
	
	public function __construct()
	{
		$this->settings = Orm_Settings::$settings;
		$this->pdo = Orm_DbDriverFactory::getDriver(Orm_Settings::$settings['db-type'])->getPDO();
		$this->inTransaction = false;
		$this->transactionData = null;
	}
	
	/**
	 * Gets the data from the given table.
	 *
	 * @throws Exception
	 * @param  string    $table The table from which data should be given
	 * @param  string    $where Customize output by entering extra serialized SQL statements
	 * @param  array()   $vars  Contains the vars that should be replaced with the placeholders in the $where query
	 * @return array()   $result
	 */
	public function get($table, $where = null, $vars = array())
	{
		if(!class_exists($table))
		{
			Orm_Class::loadClassForTable($table, $this->pdo);
		}

		$query     = Orm_Settings::$settings['query-select'];
		$query     = preg_replace("/%TABLE%/", $table, $query);
		$query     = ($where != null) ? preg_replace("/%WHERE%/", $where, $query) : preg_replace("/%WHERE%/", "", $query);
		$statement = $this->pdo->prepare($query);
		
		$statement->execute($vars);
		
		$result = $statement->fetchAll(PDO::FETCH_CLASS, $table);

		return $result;
	}
	
	/**
	 * Returns a single object
	 * 
	 * @throws Exception
	 * @param  string    $table The table from which data should be given
	 * @param  string    $where Customize output by entering extra serialized SQL statements
	 * @param  array()   $vars  Contains the vars that should be replaced with the placeholders in the $where query
	 * @return           $item  The retrieved item
	 */
	public function getUnique($table, $where, $vars)
	{
		$items = $this->get($table, $where, $vars);
		
		if(count($items) != 1)
		{
			throw new Exception("Orm_Core: Unable to get unique object.");
		}
		
		return $items[0];
	}
	
	/**
	 * Saves or updates a given object based on it's id.
	 *
	 * @throws Exception
	 * @param  $object   The object to be saved.
	 */
	public function save($object)
	{
		$tableName = get_class($object);
		$vars      = $this->_getVariables($object);
		$query     = "";
		
		if(key_exists('id', $vars) && is_numeric($vars['id']))
		{
			if(count($this->get($tableName, "WHERE id = :id", array("id" => $vars['id']))) == 0)
			{
				throw new Exception("Orm_Engine: Object can't be updated."); 	
			}
			
			$updates = ""; 
			foreach($vars as $key => $value)
			{
				if($key != 'id')
				{
					$updates = "$updates $key=:$key,";
				}
			}

			// remove the last comma
			$updates = substr($updates, 0, -1);
			
			$query = Orm_Settings::$settings['query-update'];
			$query = preg_replace("/%TABLE%/", $tableName, $query);
			$query = preg_replace("/%UPDATES%/", $updates, $query);
			$query = preg_replace("/%WHERE%/", "id = :id", $query);
		}
		else
		{
			$fields = "";
			$values = "";

			foreach($vars as $key => $value)
			{
				if($key != 'id')
				{
					$fields = "$fields $key,";
					$values = "$values :$key,";
				}
			}
			
			// remove the last comma
			$fields = substr($fields, 0, -1);
			$values = substr($values, 0, -1);
			
			$query = Orm_Settings::$settings['query-insert'];
			$query = preg_replace("/%TABLE%/", $tableName, $query);
			$query = preg_replace("/%FIELDS%/", $fields, $query);
			$query = preg_replace("/%VALUES%/", $values, $query);

			// unset id
			unset($vars['id']);
		}
		
		$this->_processStatement($query, $vars);
	}
	
	/**
	 * Removes the given object
	 *
	 * @param Object $object The object to remove from the database
	 */
	public function remove($object)
	{
		$tableName = get_class($object);
		$vars      = $this->_getVariables($object);
		$query     = Orm_Settings::$settings['query-delete'];
		$query     = preg_replace("/%TABLE%/", $tableName, $query);
		$query     = preg_replace("/%WHERE%/", "id = :id", $query);

		$this->_processStatement($query, array('id' => $vars['id']));
	}
	
	/**
	 * Loads a standard class for all the database tables.
	 */
	public function loadTableClasses()
	{
		$query = $this->pdo->prepare("show tables");
		$query->execute(array());
		$fields = $query->fetchAll();
		
		foreach($fields as $field)
		{
			Orm_Class::loadClassForTable($field[0], $this->pdo);
		}
	}
	
	/**
	 * Processes a query wether it should be executed immediately or stored in a transaction.
	 *
	 * @param string $query The database query to be processed
	 */
	protected function _processStatement($query, $vars)
	{
		$statement = $this->pdo->prepare($query);
		$statement->execute($vars);
	}

	/**
	 * Returns all the objects variables as an assosiative array.
	 *
	 * @param  $object
	 * @return array() $result Array containing the variables and their values as key-value pairs.
	 */
	protected function _getVariables($class)
	{
		$reflect    = new ReflectionClass($class);
		$properties = $reflect->getProperties();
		$result     = array();

		foreach($properties as $property)
		{
			$property->setAccessible(true);
			$result[$property->getName()] = $property->getValue($class);
		}

		return $result;
	}
}
?>
