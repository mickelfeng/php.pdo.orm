<?php
require_once("Orm_DbDriverMysql.php");
require_once("Orm_DbDriverSqlite.php");
/**
 * Returns a DbDriver Object based on the 'db-type' in Settings.
 * 
 * @author J.Smit <j.smit@sgoen.nl>
 */
class Orm_DbDriverFactory
{
	public static function getDriver($driver)
	{
		switch($driver)
		{
			case 'mysql':
				return Orm_DbDriverMysql::getInstance();
			case 'sqlite':
				return Orm_DbDriverSqlite::getInstance();
			default:
				throw new Exception("Orm: Driver not found.");
		}
	}
}
?>
