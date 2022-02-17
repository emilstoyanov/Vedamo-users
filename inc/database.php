<?php

defined('_APPEXEC') or die;

class cDB {

  private static $dbsettings = null; // table settings
  private static $dblink = null;
  public static $prefix = '';
  private static $state = '';
  private static $schema = null;
  private static $ivlen = null;
  private static $logfile = null;

  public static function init()
  {
    if (cCore::getCfg('logsql')) {
      $fname = date("Ymd") . "_log.txt";
      self::$logfile = fopen(cCore::getCfg('logdir') . $fname, 'a');
    }
    self::clearState();
    self::connect();
    self::$ivlen = openssl_cipher_iv_length(cCore::getCfg('cipher'));
    self::$prefix = cCore::getCfg('dbprefix');
    self::loadSettings();
  }

  private static function loadSettings()
  {
    self::$dbsettings = (object) [];
    $sql = sprintf('SELECT name,value FROM %ssettings', self::$prefix);
    $result = self::$dblink->query($sql, MYSQLI_USE_RESULT);
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        self::$dbsettings->{$row['name']} = $row['value'];
      }
      $result->free();
//      self::setState('true', $sql);
      self::getTables();
    } else {
      self::setState('false', '', 'DB load settings: ' . self::$dblink->errno . ' ' . self::$dblink->error);
    }
  }

  public static function getSettings($name = false)
  {
    return (!$name) ? self::$dbsettings : ((isset(self::$dbsettings->$name)) ? self::$dbsettings->$name : null);
  }

  public static function saveSettings($data)
  {
    if (empty($data)) {
      self::setState('false', '', 'DB saveSettings: empty data');
      return false;
    }
    $types = '';
    $values = [];
    $rows = '';
    $i = 0;
    $sql = 'REPLACE INTO `' . self::$prefix . 'settings` (name,value) VALUES ';
    foreach ($data as $name => $value) {
      if ($i > 0) {
        $rows .= ',';
      }
      $rows .= '(?,?)';
      $values[] = $name;
      $values[] = $value;
      $types .= 'ss';
      $i++;
    }
    $sql .= $rows;
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' . self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      if (self::$dblink->affected_rows === 0) {
        self::log('WARNING: No records replaced!', $sqlstr);
      }
      self::setState('true', $sqlstr, '', '', $stmt->affected_rows);
      self::$dblink->commit();
      $stmt->close();
      return true;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' . $stmt->error);
      $stmt->close();
      return false;
    }
  }

  public static function escapeString($str) {
    return self::$dblink->real_escape_string($str);
  }

  public static function beginTransaction() {
    self::$dblink->autocommit(false);
  }

  public static function commitTransaction() {
    self::$dblink->commit();
  }

  public static function rollbackTransaction() {
    self::$dblink->rollback();
  }

  public static function getPrefix()
  {
    return self::$prefix;
  }

  public static function log($message, $sql = '') {
    if (cCore::getCfg('logsql')) {
      $s = '[' . date("Y-m-d H:i:s") . ']: ' . $message . cCore::getCfg('eol');
      if ($sql != '') {
        $s .= '     SQL: ' . $sql . cCore::getCfg('eol');
      }
      fwrite(self::$logfile, $s);
    }
  }

  public static function connect()
  {
    self::$dblink = new mysqli(cCore::getCfg('dbhost'), cCore::getCfg('dbuser'), cCore::getCfg('dbpass'), cCore::getCfg('dbname'));
    if (self::$dblink->connect_error) {
      cCore::terminate('Could not connect to Database!');
    } else {
      if (!self::$dblink->set_charset(cCore::getCfg('dbcharset'))) {
        cCore::terminate('Could not set Charset!');
      }
    }
    self::setState('true');
  }

  public static function clearState() { self::$state = (object) [ 'success' => 'false', 'sql' => '', 'error' => '', 'iid' => '', 'rows' => '', 'wrong_col' => '', 'date' => '' ]; }

  public static function setState($success, $sql = '', $error = '', $iid = '', $rows = '', $date = '')
  {
    self::$state->success = $success;
    self::$state->sql = addslashes($sql);
    if ($iid !== '') { self::$state->iid = $iid; }; // we set it ONLY when contains a value, there is cases when we need to keep old insert ID
    self::$state->rows = $rows;
    self::$state->date = $date;
    if (($success == 'false') && ($error != '')) {
      self::$state->error = $error;
      $colname = ( self::$state->wrong_col != '' ? self::$state->wrong_col : '');
      if (cCore::getCfg('logsql')) {
        self::log($error . ' ' . $colname  , $sql);
      }
    }
  }

  public static function getIID() {
    return self::$state->iid;
  }

  public static function getAffectedRows() {
    return self::$state->rows;
  }

  /** Sends RAW query to database. WARNING!!! NEVER USE THIS FUNCTION WITH DATA FROM cCore::$input.
   *  @param $sql (string) The sql string.
   *  @return (mixed) FALSE on failure, TRUE on successful queries without result set (INSERT,UPDATE,etc.),
   *  array on successful queries with a result set (SELECT,etc.)
   */
  public static function rawSQL($sql)
  {
    $result = self::$dblink->query($sql, MYSQLI_USE_RESULT);
    // MySQL error
    if ($result === false) {
      self::setState('false', $sql, 'DB rawSQL: ' . self::$dblink->errno . ' ' . self::$dblink->error);
      return false;
    }
    // Successful query without result set (INSERT,UPDATE,etc.)
    if ($result === true) {
      self::setState('true', $sql, '', self::$dblink->insert_id, self::$dblink->affected_rows);
      return true;
    }
    // Successful query with a result set (SELECT,etc.)
    $data = [];
    while ($row = $result->fetch_assoc()) {
      $data[] = $row;
    }
    self::setState('true', $sql, '', '', $result->num_rows);
    $result->free();
    return $data;
  }

  private static function getTables()
  {
    self::$schema = (object) [];
    $sql = sprintf('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM `information_schema`.`COLUMNS` WHERE TABLE_SCHEMA="%s" ORDER BY TABLE_NAME,ORDINAL_POSITION', cCore::getCfg('dbname'));
    if ($result = self::$dblink->query($sql, MYSQLI_USE_RESULT)) {
      while ($row = $result->fetch_assoc()) {
        if (!isset(self::$schema->{$row['TABLE_NAME']})) {
          self::$schema->{$row['TABLE_NAME']} = (object) [];
        }
        self::$schema->{$row['TABLE_NAME']}->{$row['COLUMN_NAME']} = $row['DATA_TYPE'];
      }
      $result->close();
      $sql = 'SELECT name FROM `' . self::$prefix . 'settings`';
      $tbl = self::$prefix . 'settings';
      if ($result = self::$dblink->query($sql, MYSQLI_USE_RESULT)) {
        while ($row = $result->fetch_assoc()) {
          self::$schema->$tbl->{$row['name']} = 'blob';
        }
        $result->close();
//        self::setState('true', $sql);
      } else {
        self::setState('false', '', 'DB settings table: ' . self::$dblink->errno . ' ' . self::$dblink->error);
      }
    } else {
      self::setState('false', '', 'DB getTables: ' . self::$dblink->errno . ' ' .self::$dblink->error);
    }
  }

  /** Verifies that each column in the array $columns exist in the table $name
   *  @param $name (string) table name
   *  @param $columns (string) column names, comma separated
   *  @param $prefix (string) table prefix
   *  @return (boolean) if at least one check failed - false, otherwise returns true
   */
  private static function checkTable($name, $columns, $prefix)
  {
    if ($columns == '') {
      return true;
    } // if no column names, nothing to check
    $tbl = $prefix . $name;
    foreach (explode(',', $columns) as $col) {
      if (!isset(self::$schema->$tbl->$col)) {
        self::$state->wrong_col = ' -> ' . $col;
        return false;
      }
    }
    return true;
  }

  /** Checks all joins for the select statement with checkTable()
   *  @param $joins (array) $see cCore::Select()
   *  @param $prefix (string) table prefix
   *  @return (boolean) if at least one check failed - false, otherwise returns true
   */
  private static function checkJoins($joins, $prefix)
  {
    if (empty($joins)) {
      return true;
    } // empty array, nothing to check
    foreach ($joins as $table => $data) {
      if (!self::checkTable($table, $data['id'] . ',' . $data['sel'], $prefix)) {
        return false;
      }
    }
    return true;
  }

  /* returns the type if given field $col (in given table $name), formatted for PREPARE STATEMENTS */

  private static function getFieldType($name, $col, $pref)
  {
    $tbl = $pref . $name;
    $t = self::$schema->$tbl->$col;
    if (strpos('date;datetime;text;varchar;mediumtext;tinytext', $t) !== false) {
      return 's';
    }
    if (strpos('int;timestamp;tinyint;smallint;mediumint', $t) !== false) {
      return 'i';
    }
    if (strpos('decimal', $t) !== false) {
      return 'd';
    }
    if (strpos('blob', $t) !== false) {
      return 's';
    }
  }

  private static function Encode($value)
  {
    $iv = openssl_random_pseudo_bytes(self::$ivlen);
    return base64_encode($iv . openssl_encrypt($value, cCore::getCfg('cipher'), cCore::getCfg('key'), 0, $iv));
  }

  private static function Decode($value)
  {
    $tmp = base64_decode($value);
    $iv = substr($tmp, 0, self::$ivlen);
    $text = substr($tmp, self::$ivlen);
    return openssl_decrypt($text, cCore::getCfg('cipher'), cCore::getCfg('key'), 0, $iv);
  }

  /** Returns a JSON encoded string from database STATE
   *  @param $data (array) contains data who we want to sent to frontend
   *  @param $field_patterns (array) array with original field names in $data
   *  @param $field_replacements (array) array with modified field names for the frontend
   *  @return (string) JSON encoded string from database STATE, formated for frontend as follows:
   *  {
   *    success: true / false 
   *    sql: in debug mode - last executed statement, otherwise empty string
   *    data: contains param $data 
   *    error: error code and message - hidden when the backend is not in debug mode
   *    insertid: last inserted id from MySQL
   *    rows: affected rows from last SQL statement 
   *  }
   */
  public static function formatOutput(&$data = [], $field_patterns = [], $field_replacements = []) {
    $sql = cCore::getCfg('debug') ? self::$state->sql : '';
    if (!empty($data)) {
      $encoded = cCore::getCfg('encoded');
      if ($encoded != '') {
        $textcols = [];
        foreach (self::$state->data[0] as $name => $value) {
          if (strpos($encoded, ';' . $name . ';') !== false) {
            $textcols[] = $name;
          }
        }
        // walk in data array to decode text cols, if any exists
        if (!empty($textcols)) {
          foreach ($data as &$row) {
            foreach ($textcols as $col) {
              $row[$col] = self::Decode($row[$col]);
            }
          }
        }
      }
    }
    $data = json_encode($data, JSON_NUMERIC_CHECK);
    if ((!empty($field_patterns)) && (!empty($field_replacements))) { $data = preg_replace($field_patterns, $field_replacements, $data); }
    return ('{"success": ' . self::$state->success . ', "sql": "' . $sql . '", "data":' . $data . ', "error": "' . self::$state->error . '", "insertid": "' . self::$state->iid . '", "rows": "' . self::$state->rows . '", "date": "' . self::$state->date . '" }');
  }

  private static function createSQL($sql, $values) {
    foreach ($values as $val) {
      $sql = preg_replace('/\?/', $val, $sql, 1);
    }
    return $sql;
  }

	/** Query database
	 * @param $sql (string) SQL statement, formated for mysqli->stmt->prepare()
	 * @param $types (string) parameter types
	 * @param $values (array) parameter values
	 * @param bool $datareturn (bool) set to true if the statement must return data (SELECT)
	 * @return array|bool (mixed) Array with selected data (or true if $datareturn == false ) on success, FALSE on failure.
	 */
  public static function Query($sql, $types, $values, $datareturn = false) {
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Query, Statement Prepare' . self::$dblink->errno . ' ' .self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
	  if (count($values) > 0) {
		  $params = [& $types];
		  for ($i = 0; $i < count($values); $i++) {
			  $params[] = &$values[$i];
		  }
		  call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
	  }

    if ($stmt->execute()) {
      $result = true; 
      $data = [];
      $res = $stmt->get_result();
      self::setState('true', $sqlstr, '', $stmt->insert_id, $stmt->affected_rows );
      if ($datareturn) {
        if ($res) {
          if ($res->num_rows == 0) {self::log('WARNING: Results are expected, but statement returns 0 rows!', $sqlstr);}
        }
        while ($row = $res->fetch_array(MYSQLI_ASSOC)) {$data[] = $row;}
        $result = $data;
      }
      $stmt->close();
      if ($res) { $res->free(); }
      return $result;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' .$stmt->error);
      $stmt->close();
      return false;
    }
  }

  private static function collectColumns($columns = '', $where = [], $order = '')
  {
    $wh = (!empty($where)) ? implode(',', array_keys($where)) : '';
    $colnames = ($columns != '') ? $columns : '';
    $colnames .= ($order != '') ? (($colnames != '') ? ',' . $order : $order) : '';
    $colnames .= ($wh != '') ? (($colnames != '') ? ',' . $wh : $wh) : '';
    return $colnames;
  }

  private static function createSELECT($table, $columns, $pref)
  {
    if ($columns == '') {
      return '`' . $pref . $table . '`.*';
    }
    $i = 0;
    $select = '';
    foreach (explode(',', $columns) as $col) {
      if ($i > 0) {
        $select .= ', ';
      }
      $select .= '`' . $pref . $table . '`.' . $col;
      $i++;
    }
    return $select;
  }

  private static function appendJoins($table, &$select, &$from, &$where, $joins)
  {
    foreach ($joins as $tblname => $data) {
      $fields = explode(',', $data['sel']);
      foreach ($fields as $value) {
        $select .= ', `' . self::$prefix . $tblname . '`.' . $value . ' as ' . $tblname . $value;
      }
      $from .= ', `' . self::$prefix . $tblname . '`';
      $where .= ' AND `' . self::$prefix . $tblname . '`.' . $data['id'] . '=`' . self::$prefix . $table . '`.' . $data['id'];
    }
  }

  /** Creates SELECT statement from given parameters.
   *  @param $table (string) Table name.
   *  @param $columns (string) Comma separated column names. If not set, the result will contain all table columns.
   *  @param $joins (array) Array of type: [ "table1" => [ id: "ID_NAME", sel: "COL1,COL2" ], ....... ] The function
   *  will JOIN "table1" by id field "ID_NAME", and will select columns COL1,COL2.
   *  @param $where (array) an associative array [ "col1"=>val1, "col2=>val2" ]. The function will create WHERE clause as:
   *  WHERE col1=val1 AND col2=val2
   *  @param $order (string) Comma separated column names for the ORDER clause.
   *  @param $useprefix (boolean) True if you want to use table prefix in select (some tables have no prefix).
   *  @param $direction (string) Order direction 'ASC' or 'DESC' 
   *  @param $limit (int) Query LIMIT
   *  @return (mixed) Array with selected data on success, FALSE on failure.
   *  NOTE: $direction AND $limit has efect only if there is at least one column in $order
   */
  public static function Select($table, $columns = '', $joins = [], $where = [], $order = '', $useprefix = true, $direction = 'ASC', $limit = 0)
  {
    $pref = ($useprefix) ? self::$prefix : '';
    if (!self::checkTable($table, self::collectColumns($columns, $where, $order), $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    if (!self::checkJoins($joins, $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    // all is checked, creating the statement
    $select = 'SELECT ' . self::createSELECT($table, $columns, $pref);
    $from = 'FROM `' . $pref . $table . '`';
    $sqlwhere = 'WHERE 1=1';
    if (!empty($joins)) {
      self::appendJoins($table, $select, $from, $sqlwhere, $joins);
    }
    // WHERE clause, if is set
    $types = '';
    $values = [];
    if (!empty($where)) {
      foreach ($where as $name => $value) {
        $sqlwhere .= ' AND `' . $pref . $table . '`.' . $name . '=?';
        $values[] = $value;
        $types .= self::getFieldType($table, $name, $pref);
      }
    }
    // ORDER BY CLAUSE, IF IS SET
    $sqlorder = ' ';
    if ($order != '') {
      $sqlorder = ' ORDER BY ';
      $i = 0;
      $cols = explode(';', $order);
      foreach ($cols as $col) {
        if ($i > 0) {
          $sqlorder .= ', ';
        }
        $sqlorder .= '`' . $pref . $table . '`.' . $col;
        $i++;
      }
      $sqlorder .= ' '.$direction;
    }
    // LIMIT query if needed
    $sqlorder .= ($limit > 0) ? ' LIMIT '.$limit : '';
    // Complete SQL statement
    $sql = $select . ' ' . $from . ' ' . $sqlwhere . $sqlorder;
    // SQL string with values, to set it in self::$state
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' .self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    if (!empty($where)) {
      $params = [&$types];
      for ($i = 0; $i < count($values); $i++) {
        $params[] = &$values[$i];
      }
      call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    }
    if ($stmt->execute()) {
      $data = [];
      $res = $stmt->get_result();
      if ($res->num_rows == 0) {
        self::log('WARNING: Results are expected, but statement returns 0 rows!', $sqlstr);
      }
      while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
        $data[] = $row;
      }
      self::setState('true', $sqlstr, '', '', $res->num_rows);
      $stmt->close();
      $res->free();
      return $data;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno. ' '. $stmt->error);
      $stmt->close();
      return false;
    }
  }

  /** Inserts one row into table
   * @param $table (string) table name
   * @param $data (array) an associative array with data to be inserted
   * @param $useprefix (boolean) True if you want to use table prefix in select (some tables may have no prefix).
   * @param $keepoldIID (boolean) True if we need to keep iid of master table in state ( we save data to detail table, but for the frontend we need the master ID)
   * @param $insertdate (int) If we need to add insertdate - for example to action_log table
   * @param $createsqlstring (boolean) Set to false in cases when the sqlstring becomes too long
   * @return (boolean) true on success, false on failure
   */
  public static function Insert($table, $data, $useprefix = true, $keepoldIID = false, $insertdate = '', $createsqlstring = true) {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($data)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    // checking all input field names, if they not exist in $table
    if (!self::checkTable($table, implode(',', array_keys($data)), $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    return self::addRecord($table, $data, $pref, $keepoldIID, $insertdate, $createsqlstring);
  }

  private static function addRecord($table, $data, $pref, $keepoldIID, $insertdate, $createsqlstring) {
    // append insertdate to $data if is passed
    if ($insertdate != '') {
      $data['insertdate'] = $insertdate;
    }
    // create the SQL string
    $i = 0;
    $types = '';
    $values = [];
    $sql_names = '( ';
    $sql_values = '( ';
    foreach ($data as $name => $value) {
      if ($i > 0) {
        $sql_names .= ',';
        $sql_values .= ',';
      }
      $sql_names .= $name;
      $sql_values .= '?';
      $types .= self::getFieldType($table, $name, $pref);
      $values[] = $value;
      $i++;
    }
    // Complete SQL statement
    $sql = 'INSERT INTO `' . $pref . $table . '` ' . $sql_names . ' ) values ' . $sql_values . ' )';
    // SQL string with values, to set it in self::$state
    $sqlstr = ($createsqlstring) ? self::createSQL($sql, $values) : '';
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' .self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      if (self::$dblink->affected_rows === 0) {
        self::log('WARNING: No records inserted!', $sqlstr);
      }
      self::setState('true', $sqlstr, '', ($keepoldIID ? '' : $stmt->insert_id), $stmt->affected_rows, $insertdate);
      return true;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' .$stmt->error);
      return false;
    }
  }

  /** Updates one row into table
   * @param $table (string) table name
   * @param $data (array) an associative array with data to be inserted
   * @param $where (array) an associative array [ "col1"=>val1, "col2=>val2" ]. The function will create WHERE clause as:
   * WHERE col1=val1 AND col2=val2
   * @param $useprefix (boolean) True if you want to use table prefix in select (some tables have no prefix).
   * @return (boolean) true on success, false on failure
   */
  public static function Edit($table, $data, $where, $useprefix = true, $editdate = '') {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($data) || empty($where)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    // checking all input field names, if they not exist in $table
    if (!self::checkTable($table, implode(',', array_keys(array_merge($data, $where))), $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    return self::editRecord($table, $data, $where, $pref, $editdate);
  }

  private static function editRecord($table, $data, $where, $pref, $editdate) {
    // append editdate to $data if is passed
    if ($editdate != '') {
      $data['editdate'] = $editdate;
    }
    // create the SQL string
    $i = 0;
    $types = '';
    $values = [];
    $sql = 'UPDATE `' . $pref . $table . '` SET ';
    foreach ($data as $name => $value) {
      if ($i > 0) {
        $sql .= ',';
      }
      $sql .= $name . '=?';
      $values[] = $value;
      $types .= self::getFieldType($table, $name, $pref);
      $i++;
    }
    // add where clause to $sql
    $i = 0;
    $sql .= ' WHERE ';
    foreach ($where as $name => $value) {
      if ($i > 0) {
        $sql .= ' AND ';
      }
      $sql .= $name . '=?';
      $values[] = $value;
      $types .= self::getFieldType($table, $name, $pref);
      $i++;
    }
    // SQL string with values, to set it in $this->state
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' . self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      if ($stmt->affected_rows === 0) {
        self::log('WARNING: No records afected by UPDATE!', $sqlstr);
      }
      self::setState('true', $sqlstr, '', '', $stmt->affected_rows);
      return true;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' .$stmt->error);
      return false;
    }
  }

  public static function Cancel($table, $where, $useprefix = true)
  {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($where)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    // checking all input field names, if they not exist in $table
    if (!self::checkTable($table, implode(',', array_keys($where)), $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    // create the SQL string
    // СУПТО: we fill deldate field with currect timestamp
    $deldate = time();
    $sql = 'UPDATE `' . $pref . $table . '` SET hidden=1, deldate=' . $deldate . ' WHERE ';
    $i = 0;
    $types = '';
    $values = [];
    foreach ($where as $name => $value) {
      if ($i > 0) {
        $sql .= ' AND ';
      }
      $sql .= $name . '=?';
      $values[] = $value;
      $types .= self::getFieldType($table, $name, $pref);
      $i++;
    }
    // SQL string with values, to set it in $this->state
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' .self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      if ($stmt->affected_rows === 0) {
        self::log('WARNING: No records afected by CANCEL!', $sqlstr);
      }
      self::setState('true', $sqlstr, '', '', $stmt->affected_rows, $deldate);
      $stmt->close();
      return true;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' . $stmt->error);
      $stmt->close();
      return false;
    }
  }

  public static function Delete($table, $where, $useprefix = true) {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($where)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    // checking all input field names, if they not exist in $table
    if (!self::checkTable($table, implode(',', array_keys($where)), $pref)) {
      self::setState('false', '', 'internal: 000002');
      return false;
    }
    // create the SQL string
    $sql = 'DELETE FROM `' . $pref . $table . '` WHERE ';
    $i = 0;
    $types = '';
    $values = [];
    foreach ($where as $name => $value) {
      if ($i > 0) {
        $sql .= ' AND ';
      }
      $sql .= $name . '=?';
      $values[] = $value;
      $types .= self::getFieldType($table, $name, $pref);
      $i++;
    }
    // SQL string with values, to set it in $this->state
    $sqlstr = self::createSQL($sql, $values);
    // Prepare statement
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' . self::$dblink->error);
      return false;
    }
    // execute prepared statement with values
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      if ($stmt->affected_rows === 0) {
        self::log('WARNING: No records afected by DELETE!', $sqlstr);
      }
      self::setState('true', $sqlstr, '', '', $stmt->affected_rows);
      $stmt->close();
      return true;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' . $stmt->error);
      $stmt->close();
      return false;
    }
  }

  private static function procWhere($where)
  {
    if (empty($where)) {
      $w = '0=0';
    } else {
      $i = 0;
      $w = '';
      foreach ($where as $name => $value) {
        if ($i > 0) {
          $w .= ' AND ';
        }
        $w .= $name . '=' . $value;
        $i++;
      }
    }
    return $w;
  }

  private static function callProc($sql, $types, $values)
  {
    $sqlstr = self::createSQL($sql, $values);
    $stmt = self::$dblink->prepare($sql);
    if ($stmt === false) {
      self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno, self::$dblink->error);
      return false;
    }
    $params = [&$types];
    for ($i = 0; $i < count($values); $i++) {
      $params[] = &$values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
    if ($stmt->execute()) {
      $data = [];
      $res = $stmt->get_result();
      while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
        $data[] = $row;
      }
      self::setState('true', $sqlstr, '', '', $res->num_rows);
      $stmt->close();
      $res->free();
      return $data;
    } else {
      self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' . $stmt->error);
      $stmt->close();
      return false;
    }
  }

  public static function getMax($table, $column, $where, $userid, $firmid)
  {
    $sql = 'CALL `get_next_and_reserve`(?,?,?,?,?,?,?)';
    $types = 'ssssiii';
    $res = 0;
    $params = [self::$prefix, $table, $column, self::procWhere($where), $userid, $firmid, $res];
    return self::callProc($sql, $types, $params);
  }

  public static function unReserve($table, $column, $where, $userid, $firmid, $val)
  {
    $sql = 'CALL `unreserve`(?,?,?,?,?,?,?,?)';
    $types = 'ssssiiii';
    $res = 0;
    $params = [self::$prefix, $table, $column, self::procWhere($where), $userid, $firmid, $val, $res];
    return self::callProc($sql, $types, $params);
  }

  public static function reReserve($table, $column, $where, $userid, $firmid, $oldval, $newval)
  {
    $sql = 'CALL `check_and_reserve`(?,?,?,?,?,?,?,?,?)';
    $types = 'ssssiiiii';
    $res = 0;
    $params = [self::$prefix, $table, $column, self::procWhere($where), $userid, $firmid, $oldval, $newval, $res];
    return self::callProc($sql, $types, $params);
  }

  /**
   * Adds data into multiple master->detail tables
   * @param $data (array) of type:
   *  [ 
   *    name=>table name,
   *    id  =>id column,
   *    data=> [
   *      [0] => [
   *        column=>value,
   *        column=>value,
   *        ..........,
   *        detail=> same structure as master
   *      ],
   *      [1] => [......]
   *      ]
   *    ]
   * ]
   * @param $apend (array) column names, if we want to append data from master to detail table
   * @param $useprefix (boolean) True if you want to use table prefix in select (some tables have no prefix).
   * @return (boolean) true on success, false on failure
   */
  public static function addDoc($data, $append, $useprefix = true, $insertdate = '') {
    $pref = ($useprefix) ? self::$prefix : '';
    // BEGIN TRANSACTION
    self::$dblink->autocommit(false);
    if (self::addDocument($data, $append, true, $pref, $insertdate) === false) {
      // FAILURE - ROLLBACK
      self::$dblink->rollback();
      return false;
    }
    // SUCCESS - COMMIT
    self::$dblink->commit();
    return true;
  }

  public static function addDocument($master, $append = [], $masterdok = false, $pref, $insertdate) {
    $masterID = null; // ID of the inserted record in master table - we need it for the frontend
    $table = $master['name'];
    $idname = $master['id'];

    foreach ($master['data'] as $data) {
      // if the row is master we adding insertdate to $data
      if ($masterdok) { 
        $data['insertdate'] = $insertdate;
      }

      $detail = null; // contains detail table data if any
      // save detail data to local variable $detail for recursive call
      if (isset($data['detail'])) {
        $detail = $data['detail'];
        unset($data['detail']);
      }
      // if we have columns for appending from master to detail
      if (isset($master['append']) && ($master['append'] !== '')) {
        foreach ($detail['data'] as &$det) {
          foreach (explode(',', $master['append']) as $name) {
            $det[$name] = $data[$name];
          }
        }
        unset($master['append']);
      }

      // if we have a list of comumns in $master->remove, we unset them from $master->data ($data var in this foreach)
      if (isset($master['remove'])) {
        foreach (explode(',', $master['remove']) as $name) {
          unset($data[$name]);
        }
      }

      // if $append array is not empty, we adding all $name=>value from this array to $master->data ($data var in this foreach)
      // below we will append to this array new inserted ID to master table, to be used in next recursive call of this function
      if (!empty($append)) {
        foreach ($append as $name => $value) {
          $data[$name] = $value;
        }
      }

      // add $data to master table
      if (self::addRecord($table, $data, $pref, false, '', true)) {
        if ($masterdok) {
          $masterID = self::$state->iid;
        }
        if ($detail) {
          $appendfordetail = $append;
          $appendfordetail[$idname] = self::$state->iid;
          if (!self::addDocument($detail, $appendfordetail, false, $pref, '')) { // recursive call with $detail who becomes master
            return false;
          }
        }
      } else {
        return false;
      }
    }

    if ($masterdok) {
      self::$state->iid = $masterID;
      self::$state->date = $insertdate;
    }
    return true;
  }

  public static function editDoc($data, $append, $useprefix = true, $editdate = '')
  {
    $pref = ($useprefix) ? self::$prefix : '';
    // BEGIN TRANSACTION
    self::$dblink->autocommit(false);
    if (self::editDocument($data, $append, true, $pref, $editdate) === false) {
      // FAILURE - ROLLBACK
      self::$dblink->rollback();
      return false;
    }
    // SUCCESS - COMMIT
    self::$dblink->commit();
    return true;
  }

  private static function editDocument($master, $append = [], $masterdok = false, $pref, $editdate)
  {
    $masterID = null; // ID of the inserted record in master table - we need it for the frontend
    $table = $master['name'];
    $idname = $master['id'];

    foreach ($master['data'] as $data) {

      // if the row is master we adding editdate to $data
      if ($masterdok) { 
        $data['editdate'] = $editdate;
      }

      $detail = null; // contains detail table data if any
      // save detail data to local variable $detail for recursive call
      if (isset($data['detail'])) {
        $detail = $data['detail'];
        unset($data['detail']);
      }
      // if we have columns for appending from master to detail
      if (isset($master['append']) && ($master['append'] !== '')) {
        foreach ($detail['data'] as &$det) {
          foreach (explode(',', $master['append']) as $name) {
            $det[$name] = $data[$name];
          }
        }
        unset($master['append']);
      }
      // save detail 'type' (insert or edit)
      $type = $data['type'];
      // if we have a list of comumns in $master->remove, we unset them from $master->data ($data var in this foreach)
      if (isset($master['remove'])) {
        foreach (explode(',', $master['remove']) as $name) {
          unset($data[$name]);
        }
      }
      // if $append array is not empty, we adding all $name=>value from this array to $master->data ($data var in this foreach)
      // below we will append to this array new inserted ID to master table, to be used in next recursive call of this function
      if (!empty($append)) {
        foreach ($append as $name => $value) {
          $data[$name] = $value;
        }
      }

      // add or edit $data to master table
      if ($type == 'insert') {
        if (self::addRecord($table, $data, $pref, false, '', true)) {
          if ($detail) {
            $appendfordetail = $append;
            $appendfordetail[$idname] = self::$state->iid;
            if (!self::editDocument($detail, $appendfordetail, false, $pref, '')) { // recursive call with $detail
              return false;
            }
          }
        } else {
          return false;
        }
      } else {
        $mid = $data[$idname];
        unset($data[$idname]);
        $where = array($idname => $mid);
        if (self::editRecord($table, $data, $where, $pref, '')) {
          if ($detail) {
            $appendfordetail = $append;
            $appendfordetail[$idname] = $mid;
            if (!self::editDocument($detail, $appendfordetail, false, $pref, '')) { // recursive call with $detail
              return false;
            }
          }
        } else {
          return false;
        }
      }
    }
    if ($masterdok) {
      self::$state->iid = $masterID;
      self::$state->date = $editdate;
    }
    return true;
  }

  public static function cancelDoc($tables, $mastertable, $where, $useprefix = true) {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($where)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    $deldate = time();
    // BEGIN TRANSACTION
    self::$dblink->autocommit(false);
    foreach ($tables as $table) {
      $sql = 'UPDATE `' . $pref . $table . '` SET hidden=1 ';
      if ($table == $mastertable) { $sql.= ', deldate = ' . $deldate; }
      // add where clause to $sql
      $i = 0;
      $types = '';
      $values = [];
      $sql .= ' WHERE ';
      foreach ($where as $name => $value) {
        if ($i > 0) {
          $sql .= ' AND ';
        }
        $sql .= $name . '=?';
        $values[] = $value;
        $types .= self::getFieldType($table, $name, $pref);
        $i++;
      }
      // SQL string with values, to set it in $this->state
      $sqlstr = self::createSQL($sql, $values);
      // Prepare statement
      $stmt = self::$dblink->prepare($sql);
      if ($stmt === false) {
        self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' . self::$dblink->error);
        return false;
      }
      // execute prepared statement with values
      $params = [&$types];
      for ($i = 0; $i < count($values); $i++) {
        $params[] = &$values[$i];
      }
      call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
      if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
          self::log('WARNING: No records afected by UPDATE!', $sqlstr);
        }
        self::setState('true', $sqlstr, '', '', $stmt->affected_rows, $deldate);
      } else {
        self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' .$stmt->error);
        $stmt->close();
        self::$dblink->rollback();
        return false;
      }
    }
    // SUCCESS - COMMIT
    self::$dblink->commit();
    return true;
  }

  public static function activateDoc($tables, $mastertable, $where, $useprefix = true) {
    $pref = ($useprefix) ? self::$prefix : '';
    // check input
    if (empty($where)) {
      self::setState('false', '', 'internal: 000003');
      return false;
    }
    $editdate = time();
    // BEGIN TRANSACTION
    self::$dblink->autocommit(false);
    foreach ($tables as $table) {
      $sql = 'UPDATE `' . $pref . $table . '` SET hidden=0 ';
      if ($table == $mastertable) { $sql.= ', deldate=NULL, editdate = ' . $editdate; }
      // add where clause to $sql
      $i = 0;
      $types = '';
      $values = [];
      $sql .= ' WHERE ';
      foreach ($where as $name => $value) {
        if ($i > 0) {
          $sql .= ' AND ';
        }
        $sql .= $name . '=?';
        $values[] = $value;
        $types .= self::getFieldType($table, $name, $pref);
        $i++;
      }
      // SQL string with values, to set it in $this->state
      $sqlstr = self::createSQL($sql, $values);
      // Prepare statement
      $stmt = self::$dblink->prepare($sql);
      if ($stmt === false) {
        self::setState('false', $sqlstr, 'Statement Prepare: ' . self::$dblink->errno . ' ' . self::$dblink->error);
        return false;
      }
      // execute prepared statement with values
      $params = [&$types];
      for ($i = 0; $i < count($values); $i++) {
        $params[] = &$values[$i];
      }
      call_user_func_array(array($stmt, 'bind_param'), $params); // TODO: check the result
      if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
          self::log('WARNING: No records afected by UPDATE!', $sqlstr);
        }
        self::setState('true', $sqlstr, '', '', $stmt->affected_rows, $editdate);
      } else {
        self::setState('false', $sqlstr, 'Statement Execute: ' . $stmt->errno . ' ' .$stmt->error);
        $stmt->close();
        self::$dblink->rollback();
        return false;
      }
    }
    // SUCCESS - COMMIT
    self::$dblink->commit();
    return true;
  }

}