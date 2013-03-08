<?php

class PHPG_Exception extends Exception {}

class PHPG {
	private $connection_alias;
	private $type_transform_mappings = array(
		// * "Null" means do not perform any transformation on the data.
		// * The function corresponding to the string specified with the
		// text "_transform_" prepended to it will be called. Ex: "binary"
		// will call the function _transform_binary().

		# Advanced Data-Types
		'hstore' => 'hstore', # hstore data-type
		'json' => 'json', # json data-type
		'xml' => 'xml', # xml data-type

		# Binary
		'bit'		=> 'binary', # bit data-type
		'bytea'		=> 'binary', # bytea data-type
		'varbit'	=> 'binary', # varbit data-type

		# Boolean
		'bool'	=> 'boolean', # boolean data-type

		# Date
		'date'			=> 'datetime', # date data-type
		'timestamp'		=> 'datetime', # timestamp / timestamp without time zone data-type
		'timestamptz'	=> 'datetime', # tmestamp with time zone data-type

		# Float
		'float4'	=> 'float', # ?? data-type
		'float8'	=> 'float', # ?? data-type
		'money'		=> 'float', # money data-type
		'numeric'	=> 'float', # numeric data-type

		# Geometric
		'box'		=> 'geo_box', # boxz data-type
		'circle'	=> 'geo_circle', # cicle data-type
		'lseg'		=> 'geo_lseg', # lseg data-type
		'line'		=> 'geo_lseg', # line data-type. same syntax as lseg, only creates an infinite line pointing in a the direction defined by x1/y1 and x2/y2 vals.
		'path'		=> 'geo_path', # path data-type
		'point'		=> 'geo_point', # point data-type
		'polygon'	=> 'geo_polygon', # polygon data-type

		# Integer
		'int2'	=> 'integer', # smallint / smallserial data-type
		'int4'	=> 'integer', # integer / serial data-type
		'int8'	=> 'integer', # bigint / bigserial data-type

		# Network
		'cidr' => Null, # cidr data-type
		'inet' => Null, # inet data-type
		'macaddr' => Null, # macaddr data-type

		# Other
		'uuid' => Null, # uuid data-type

		# Range
		'daterange'	=> Null, # daterange data-type. TODO. Selecting: '[2013-01-01,2013-09-09]'::daterange Returns: [2013-01-01,2013-09-10). Select using: WHERE '2013-02-02'::date <@ '[2013-01-01,2013-09-09]'::daterange
		'int4range'	=> Null, # int4range data-type. TODO. Selecting: '[3,5]'::int4range Returns: [3,6). Select using: WHERE 3 <@ '[3,5]'::int4range
		'int8range'	=> Null, # int8range data-type. TODO. Selecting: '[3,5]'::int4range Returns: [3,6). Select using: WHERE 3 <@ '[3,5]'::int4range
		'interval'	=> Null, # interval field. TODO. Selecting: '1 month'::interval Returns: 1 mon. Select using: SELECT timestamp '2013-01-01' + val Returns: 2013-02-01 00:00:00
		'numrange'	=> Null, # numrange data-type. TODO. Selecting '[1.00,2.50]'::numrange Returns: [1.00,2.50]. Select using: WHERE 1.6 <@ val

		# String
		'bpchar'	=> 'string', # character data-type
		'text'		=> 'string', # text / character varying (only if character varying field doesn't have a character limit) data-type
		'varchar'	=> 'string', # character varying data-type (only is character varying field has a character limit.)

		# Text-Search
		'tsquery' => Null, # tsquery data-type
		'tsrange' => Null, # tsrange data-type
		'tsvector' => Null, # tsvector data-type

		# Time
		'time'		=> 'time', # time / time time without time zone data-type
		'timetz'	=> 'time', # time with time zone data-type
	);

	/**
	 * $connection_params (string or array): 
	 * $force_new_connection (boolean): If False (default), will return an existing connection (if one is present) based on the connection parameters passed. If True, will always return a new connection, regardless if one currently exists with the parameters passed.
	 */
	public function __construct($connection_alias, $connection_params = False) {
		// Ensure PHPG key exists in GLOBALS variable.
		if(!array_key_exists('PHPG', $GLOBALS)) {
			$GLOBALS['PHPG'] = array();
		}

		// Retrieve existing connection by connection alias.
		if($connection_params === False) {
			if(array_key_exists($connection_alias, $GLOBALS['PHPG'])) {
				$this->connection_alias = $connection_alias;
				return; // Connection found, no reason to continue.
			} else {
				throw new PHPG_Exception('Connection alias specified "' . $connection_alias . '" does not exists');
			}
		}

		// Grab data type of connection params passed.
		$connection_datatype = gettype($connection_params);

		if($connection_datatype == 'string') { // String
			$connection_string = $connection_params;
		} else if($connection_datatype == 'array') { // Array
			$connection_string = array();

			if(isset($connection_params['host'])) {
				$connection_string[] = "host='" . $this->escape($connection_params['host']) . "'";
			}
			if(isset($connection_params['port'])) {
				$connection_string[] = "port='" . $this->escape($connection_params['port']) . "'";
			}
			if(isset($connection_params['dbname'])) {
				$connection_string[] = "dbname='" . $this->escape($connection_params['dbname']) . "'";
			}
			if(isset($connection_params['user'])) {
				$connection_string[] = "user='" . $this->escape($connection_params['user']) . "'";
			}
			if(isset($connection_params['password'])) {
				$connection_string[] = "password='" . $this->escape($connection_params['password']) . "'";
			}
			if(isset($connection_params['options'])) {
				$connection_string[] = "options='" . $this->escape($connection_params['options']) . "'";
			}
			$connection_string = implode(' ', $connection_string);
		} else { // Invalid data type
			throw new PHPG_Exception('Expecting connection_params to be string or array, encountered "' . $connection_datatype . '"');
		}

		// Establish connection if it doesn't currently exist.
		if(!array_key_exists($connection_alias, $GLOBALS['PHPG'])) { // Connection alias doesn't exist
			$GLOBALS['PHPG'][$connection_alias] = array(
				'queries' => array(),
				'connection' => $this->_connect($connection_string)
			);
		} else { // Connection alias already exists
			throw new Exception('Cannot create new connection, connection alias "' . $connection_alias . '" already exists');
		}

		// Set the object's alias for this connection.
		$this->connection_alias = $connection_alias;
	}

	private function _connect($connection_string) {
		// Establish connection
		$connection = pg_connect($connection_string, PGSQL_CONNECT_FORCE_NEW);

		// Allows for commit and rollback operations
		pg_query($connection, 'BEGIN;');

		// Return connection
		return $connection;
	}

	public function execute($query_alias, $query) {
		$query_alias = $this->_sanitize_alias($query_alias, False);

		// Perform query. Using "@" to supress possible errors being printed,
		// because we're catching these ourself and throwing exceptions instead.
		$resource = @pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], $query);

		if($resource === False) {
			throw new PHPG_Exception('Error with query: ' . pg_last_error($GLOBALS['PHPG'][$this->connection_alias]['connection']));
		}

		// If an update or delete is performed, in most cases data will not be
		// returned. In these cases, we don't care about specifying an alias.
		if($query_alias) {
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias] = array(
				'resource' => $resource,
				'current' => False,
				'row-count' => Null,
				'rows-affected' => Null,
				'executed-query' => $query
			);
		}
		return True;
	}

	public function rollback() {
		$result = pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "ROLLBACK;");
		$error = pg_result_error($result);
		if($error === False) {
			pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "BEGIN;");
			return True;
		}
		throw new PHPG_Exception('Unable to commit: '. $error);
	}

	public function commit() {
		$result = pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "COMMIT;");
		$error = pg_result_error($result);
		if($error === False) {
			pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "BEGIN;");
			return True;
		}
	}

	public function fetchone($query_alias) {
		$query_alias = $this->_sanitize_alias($query_alias);
		return $this->iter($query_alias);
	}

	public function executed_query($query_alias) {
		// Return the exact query passed to PostgreSQL, based on the query alias
		// passed.
		return $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['executed-query'];
	}

	public function iter($query_alias) {
		$query_alias = $this->_sanitize_alias($query_alias);

		if($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current'] === False) {
			// Grab fields to determine if we need to do anything special.
			// Particularly, with arrays or hstores.
			$num_fields = pg_num_fields($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource']);
			$curr_field = 0;
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'] = array();

			// Iterate over the fields.
			while($curr_field < $num_fields) {
				// Grab the field name
				$field_name = pg_field_name($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource'], $curr_field);
				$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$field_name] = array();

				// Grab the field's data type
				$field_type = pg_field_type($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource'], $curr_field);

				// Check whether or not the data type is an array.
				if(substr($field_type, 0, 1) == '_') { // Is an array
					$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$field_name]['is-array'] = True;

					// Now that we know we're working with an array, let's
					// remove the underscore from the beginning of the field
					// type.
					$field_type = substr($field_type, 1);
				} else { // Not an array
					$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$field_name]['is-array'] = False;
				}

				// Add the field to the fields array.
				$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$field_name]['data-type'] = $field_type;

				// Move onto the next field
				$curr_field++;
			}

			// Grabbing the number of fields advances the cursor. Set the
			// cursor's pointer back to the beginning.
			pg_result_seek($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource'], 0);

			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current'] = 0;
		}

		// Detect end of result set, to break the while loop.
		if($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current'] >= $this->rowcount($query_alias)) {
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current'] = False;
			return False;
		}

		$result = pg_fetch_assoc($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource']);

		// Iterate over each field, converting PostgreSQL field to native PHP
		// data-types where applicable.

		foreach($result as $column => $row_data) {
			// Detect PostgreSQL NULL Values
			if(pg_field_is_null($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource'], $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current'], $column)) {
				$result[$column] = Null;
				// Nothing else needs to be done to NULL values, so
				// immediately continue onto the next field.
				continue;
			}

			// Parse PostgreSQL arrays into PHP arrays.
			if($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$column]['is-array']) {
				$result[$column] = $this->_array_from_pg($row_data, $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$column]['data-type']);
			}

			// Perform any sort of data transformation which may be needed
			// on this particular data type.
			$result[$column] = $this->_transform_data($result[$column], $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['fields'][$column]['data-type']);
		}

		$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['current']++;

		return $result;
	}

	public function free($query_alias) {
		// Inform PostgreSQL it can release the result set.
		pg_free_result($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource']);
		// Delete all associated PHP data associated with this result.
		unset($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]);
	}

	public function reset($alias) {
		// Reset the PostgreSQL cursor's internal pointer to the beginning of
		// the result set.
		return $this->seek($alias, 0);
	}

	public function seek($alias, $row_num) {
		$alias = $this->_sanitize_alias($alias);
		// Set the PostgreSQL cursor's internal pointer to the row offset
		// specified.
		return pg_result_seek($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['connection'], $row_num);
	}

	private function _sanitize_alias($alias) {
		// Perform any clean up on the aliases want.
		$alias = strtolower((string) $alias);
		return $alias;
	}

	public function escape($string) {
		// Using "@" to supress potential errors from being displayed. This
		// function likes to complain if being called when no PostgreSQL
		// connection has yet been established.
		return @pg_escape_string($string);
	}

	public function rowcount($query_alias) {
		if($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['row-count'] === Null) {
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['row-count'] = pg_num_rows($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource']);
		}
		return $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['row-count'];
	}

	public function affected_rows($query_alias) {
		if($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['rows-affected'] === Null) {
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['rows-affected'] = pg_affected_rows($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['resource']);
		}
		return $GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['rows-affected'];
	}

	private function _array_from_pg($field_value, $data_type) {
		$grab_array_values = pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "SELECT UNNEST('" . pg_escape_string($field_value) . "'::" . $data_type . "[]) AS value");

		$array_values = array();

		$pos = 0;
		while($array_value = pg_fetch_assoc($grab_array_values)) {
			// Account for Null values.
			if(pg_field_is_null($grab_array_values, $pos, 'value')) {
				$array_values[] = Null;
			} else {
				$array_values[] = $array_value['value'];
			}
			$pos++;
		}
		return $array_values;
	}

	private function _transform_data($value, $data_type) {
		// Perform any sort of data transformations needed.
		if(array_key_exists($data_type, $this->type_transform_mappings) && $this->type_transform_mappings[$data_type] !== Null) {
			$transform_method = '_transform_' . $this->type_transform_mappings[$data_type];
			$value = $this->$transform_method($value, $data_type);
		}

		return $value;
	}

	private function _transform_hstore($value, $data_type) {
		$return = array();
		$grab_hstore = pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "SELECT key, value FROM EACH('" . pg_escape_string($value) . "'::hstore)");
		while($hstore = pg_fetch_assoc($grab_hstore)) {
			$return[$hstore['key']] = $hstore['value'];
		}
		return $return;
	}

	private function _transform_json($value, $data_type) {
		$value = json_decode($value, True);

		// Ensure there were no issues parsing the JSON.
		$json_error = json_last_error();
		if($json_error === JSON_ERROR_NONE) {
			return $value;
		} else { // Error encountered, throw an exception.
			switch($json_error) {
				case JSON_ERROR_DEPTH:
					$json_error = 'Maximum stack depth exceeded';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$json_error = 'Underflow or the modes mismatch';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$json_error = 'Unexpected control character found';
					break;
				case JSON_ERROR_SYNTAX:
					$json_error = 'Syntax error, malformed JSON';
					break;
				case JSON_ERROR_UTF8:
					$json_error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
					break;
				default:
					$json_error = 'Unknown error';
					break;
			}
			throw new PHPG_Exception('Error while decoding JSON data type: ' . $json_error);
		}
		
	}

	private function _transform_xml($value, $data_type) {
		// TODO. Anyone else want to do this? I'm still not really sure what
		// the best solution is. Potentially returning a straight up PHP
		// array would be preferrable to returning a SimplyXML or
		// DOMDocument object.
		return $value;
	}

	// Transform Integer Data-Types
	private function _transform_integer($value, $data_type) {
		if(gettype($value) == 'array') {
			return array_map('intval', $value);
		}
		return intval($value);
	}

	// Transform Float Data-Types
	private function _transform_float($value, $data_type) {
		if(gettype($value) == 'array') {
			return array_map('floatval', $value);
		}
		return floatval($value);
	}

	// Transform Boolean Data-Types
	private function _transform_boolean($value, $data_type) {
		if(gettype($value) == 'array') {
			return array_map('boolval', $value);
		}
		return boolval($value);
	}

	// Transform Binary Data-Types
	private function _transform_binary($value, $data_type) {
		return pg_unescape_bytea($value);
	}
	
	// Transform String Data-Types
	private function _transform_string($value, $data_type) {
		if(gettype($value) == 'array') {
			return array_map('strval', $value);
		}
		return strval($value);
	}

	// Transform Time Data-Types
	private function _transform_time($value, $data_type) {
		// TODO: What should we do with time? Does PHP have a native Time object?
		// Example: "time" 15:11:12.370488 (H = 2-digit hour, i = 2-digit minute, s = 2-digit second, u = 6-digit micro-second)
			// $value = DateTime::createFromFormat('H:i:s.u', $timezone);
		// Example: "timetz" 15:10:55.802597-05 (H = 2-digit hour, i = 2-digit minute, s = 2-digit second, u = 6-digit micro-second, P = 4-digit plus sign timezone)
			// $value = DateTime::createFromFormat('H:i:s.uP', $timezone);
		return $value;
	}

	// Transform Date Data-Types
	private function _transform_datetime($value, $data_type) {
		// Create a DateTime object out of string passed. Time zone is auto-
		// maritcally calculated from time-zone defined at end of string.
		// Eg: "-05".
		$value = new DateTime($value);

		return $value;
	}

	// Transform Geometric Box Data-Types
	private function _transform_geo_box($value, $data_type) {
		// Example String: ((1,2),(3,4))
		$value = str_replace('(', '', $value);
		$value = str_replace(')', '', $value);
		$value = explode(',', $value);

		return array(
			'from' => array(
				'x' => $value[0],
				'y' => $value[1]
			),
			'to' => array(
				'x' => $value[2],
				'y' => $value[3]
			)
		);
	}

	// Transform Geometric Circle Data-Types
	private function _transform_geo_circle($value, $data_type) {
		// Example String: <(1,2),3>
		$value = str_replace('<', '', $value);
		$value = str_replace('>', '', $value);
		$value = str_replace('(', '', $value);
		$value = str_replace(')', '', $value);

		$value = explode(',', $value);

		return array(
			'x' => $value[0],
			'y' => $value[1],
			'radius' => $value[2]
		);
	}

	// Transform Geometric Line Segment Data-Types
	private function _transform_geo_lseg($value, $data_type) {
		// Example String: [(1,2),(3,4)]
		$value = str_replace('[', '', $value);
		$value = str_replace(']', '', $value);
		$value = str_replace('(', '', $value);
		$value = str_replace(')', '', $value);

		$value = explode(',', $value);
		
		return array(
			'from' => array(
				'x' => $value[0],
				'y' => $value[1]
			),
			'to' => array(
				'x' => $value[2],
				'y' => $value[3]
			)
		);
	}

	// Transform Geometric Path Data-Types
	private function _transform_geo_path($value, $data_type) {
		$path = array(
			'path' => array()
		);

		if(substr($value, 0, 1) == '[') {
			$path['type'] = 'open';
			$value = str_replace('[', '', $value);
			$value = str_replace(']', '', $value);
		} else {
			$path['type'] = 'closed';
		}

		$value = explode('),(', $value);
		foreach($value as $path_part) {
			$path_part = str_replace('(', '', $path_part);
			$path_part = str_replace(')', '', $path_part);
			$path_part = explode(',', $path_part);
			$path['path'][] = array(
				'x' => $path_part[0],
				'y' => $path_part[1]
			);
		}

		return $path;
	}

	// Transform Geometric Point Data-Types
	private function _transform_geo_point($value, $data_type) {
		$value = str_replace('(', '', $value);
		$value = str_replace(')', '', $value);
		$value = explode(',', $value);
		return array(
			'x' => $value[0],
			'y' => $value[1]
		);
	}

	// Transform Geometric Polygon Data-Types
	private function _transform_geo_polygon($value, $data_type) {
		$value = explode('),(', $value);

		$polygon = array();

		foreach($value as $polygon_part) {
			$polygon_part = str_replace('(', '', $polygon_part);
			$polygon_part = str_replace(')', '', $polygon_part);
			$polygon_part = explode(',', $polygon_part);
			$polygon[] = array(
				'x' => $polygon_part[0],
				'y' => $polygon_part[1]
			);
		}

		return $polygon;
	}

	/**
	 * VARIOUS METHODS FOR TRANSFORMING PHP NATIVE DATA-TYPES TO POSTGRESQL.
	 * AUTOMATICALLY ESCAPES VALUES, SO NO REASON TO PASS TO escape() METHOD.
	 */

	// PHP Array to PostgreSQL Array.
	public function transform_array($array, $data_type) {
		$transform_fn = 'transform_' . $data_type;
		$return = array();
		foreach($array as $index => $value) {
			$return[] = $this->escape($this->$transform_fn($value));
		}
		return implode('", "', $return);
	}

	// PHP Associative Array to PostgreSQL Hstore.
	public function transform_hstore($array) {
		$return = array();
		foreach($array as $key => $value) {
			$return[] = '"' . $this->escape($key) . '"=>"' . $this->escape($key) . '"';
		}
		return implode(', ', $return);
	}

	// PHP Boolean to PostgreSQL Boolean.
	public function transform_boolean($boolean) {
		return $boolean === True ? 't' : 'f';
	}

	// PHP Array to PostgreSQL JSON.
	public function transform_json($array) {
		return $this->escape(json_encode($array));
	}

	// PHP String to PostgreSQL Text.
	public function transform_string($string) {
		return $this->escape($string);
	}

	// PHP Binary to PostgreSQL ByteA.
	public function transform_binary($binary) {
		return pg_escape_bytea($GLOBALS['PHPG'][$this->connection_alias]['connection'], $binary);
	}

	// TODO: Transformation methods for:
	//	Geometric
	//	Date/Timestamp
	//	Range
	//	Text Search

	// TODO: Implement:
	//	prepare(): Method for ? style replacements in string. Automatic detection of data-types possible?
	//		Perhaps for arrays, look for presence of "_PHPG_TYPE" key for explicit data type transformations?
	//		Can detect arrays when PHP array w/ only integer keys
	//		Can detect hstores when PHP array w/ one or more string keys
	//		Booleans detected based on data type
	//		Binary detected based on data type
	//		All other non-array types shouldn't have to be transformed. They will still need to be escaped, however.
	//	Third parameter into execute method: data. Acts in same manner as 
}
