<?php

namespace App\Db\Drivers;

/**
 * Command represents a SQL statement to be executed against a database.
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 * @author    Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 * @author    Tomasz Kur <t.kur@yetiforce.com>
 */
trait SchemaTrait
{
	/**
	 * @var array list of ALL table names in the database
	 */
	private $_tableNames = [];

	/**
	 * @var array list of loaded table metadata (table name => metadata type => metadata).
	 */
	private $_tableMetadata = [];

	/**
	 * Refreshes the schema.
	 * This method cleans up all cached table schemas so that they can be re-created later
	 * to reflect the database schema change.
	 */
	public function refresh()
	{
		$this->_tableNames = [];
		$this->_tableMetadata = [];
		\App\Cache::clear();
	}

	/**
	 * Refreshes the particular table schema.
	 * This method cleans up cached table schema so that it can be re-created later
	 * to reflect the database schema change.
	 *
	 * @param string $name table name.
	 *
	 * @since 2.0.6
	 */
	public function refreshTableSchema($name)
	{
		\App\Cache::delete('tableSchema', $name);
		$this->_tableNames = [];
	}

	/**
	 * Creates a new savepoint.
	 *
	 * @param string $name the savepoint name
	 */
	public function createSavepoint($name)
	{
		$this->db->pdo->exec("SAVEPOINT $name");
	}

	/**
	 * Releases an existing savepoint.
	 *
	 * @param string $name the savepoint name
	 */
	public function releaseSavepoint($name)
	{
		$this->db->pdo->exec("RELEASE SAVEPOINT $name");
	}

	/**
	 * Rolls back to a previously created savepoint.
	 *
	 * @param string $name the savepoint name
	 */
	public function rollBackSavepoint($name)
	{
		$this->db->pdo->exec("ROLLBACK TO SAVEPOINT $name");
	}

	/**
	 * Sets the isolation level of the current transaction.
	 *
	 * @param string $level The transaction isolation level to use for this transaction.
	 *                      This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]], [[Transaction::REPEATABLE_READ]]
	 *                      and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific syntax to be used
	 *                      after `SET TRANSACTION ISOLATION LEVEL`.
	 *
	 * @see http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
	 */
	public function setTransactionIsolationLevel($level)
	{
		$this->db->pdo->exec("SET TRANSACTION ISOLATION LEVEL $level");
	}

	/**
	 * Returns the actual name of a given table name.
	 * This method will strip off curly brackets from the given table name
	 * and replace the percentage character '%' with [[Connection::tablePrefix]].
	 *
	 * @param string $name the table name to be converted
	 *
	 * @return string the real name of the given table name
	 */
	public function getRawTableName($name)
	{
		if (strpos($name, '{{') !== false) {
			$name = preg_replace('/\\{\\{(.*?)\\}\\}/', '\1', $name);
			return str_replace('%', $this->db->tablePrefix, $name);
		}
		return str_replace('#__', $this->db->tablePrefix, $name);
	}

	/**
	 * Returns all table names in the database.
	 *
	 * @param string $schema  the schema of the tables. Defaults to empty string, meaning the current or default schema name.
	 *                        If not empty, the returned table names will be prefixed with the schema name.
	 * @param bool   $refresh whether to fetch the latest available table names. If this is false,
	 *                        table names fetched previously (if available) will be returned.
	 *
	 * @return string[] all table names in the database.
	 */
	public function getTableNames($schema = '', $refresh = false)
	{
		if (!isset($this->_tableNames[$schema]) || $refresh) {
			$this->_tableNames[$schema] = $this->findTableNames($schema);
		}
		return $this->_tableNames[$schema];
	}

	/**
	 * Returns the metadata of the given type for the given table.
	 * If there's no metadata in the cache, this method will call
	 * a `'loadTable' . ucfirst($type)` named method with the table name to obtain the metadata.
	 *
	 * @param string $name    table name. The table name may contain schema name if any. Do not quote the table name.
	 * @param string $type    metadata type.
	 * @param bool   $refresh whether to reload the table metadata even if it is found in the cache.
	 *
	 * @return mixed metadata.
	 *
	 * @since 2.0.13
	 */
	protected function getTableMetadata($name, $type, $refresh)
	{
		$cacheKey = "$type|$name";
		if (\App\Cache::has('tableSchema', $cacheKey) && !$refresh) {
			return \App\Cache::get('tableSchema', $cacheKey);
		}
		$rawName = $this->getRawTableName($name);
		if (!isset($this->_tableMetadata[$rawName][$type]) || $refresh) {
			$this->_tableMetadata[$rawName][$type] = $this->{'loadTable' . ucfirst($type)}($rawName);
			\App\Cache::save('tableSchema', $cacheKey, $this->_tableMetadata[$rawName][$type], \App\Cache::LONG);
		}
		return $this->_tableMetadata[$rawName][$type];
	}

	/**
	 * Sets the metadata of the given type for the given table.
	 *
	 * @param string $name table name.
	 * @param string $type metadata type.
	 * @param mixed  $data metadata.
	 *
	 * @since 2.0.13
	 */
	protected function setTableMetadata($name, $type, $data)
	{
		$this->_tableMetadata[$this->getRawTableName($name)][$type] = $data;
	}
}
