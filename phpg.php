<?php

class PHPG_Exception extends Exception {}

class PHPG {
	private $connection_alias;
	private $iterators = array();
	private $type_transform_mappings = array(
		'bit' => 'binary',
		'bool' => 'boolean',
		'box' => 'geo_box',
		'bpchar' => Null,
		'bytea' => 'binary',
		'cidr' => Null,
		'circle' => 'geo_circle',
		'date' => 'datetime',
		'daterange' => Null,
		'float4' => 'float',
		'float8' => 'float',
		'hstore' => 'hstore',
		'inet' => Null,
		'int2' => 'integer',
		'int4' => 'integer',
		'int4range' => Null,
		'int8' => 'integer',
		'int8range' => Null,
		'interval' => Null,
		'json' => 'json',
		'lseg' => 'geo_lseg',
		'macaddr' => Null,
		'money' => 'float',
		'numeric' => 'float',
		'numrange' => Null,
		'path' => 'geo_path',
		'point' => 'geo_point',
		'polygon' => 'geo_polygon',
		'text' => Null,
		'time' => 'time',
		'timestamp' => 'datetime',
		'timestamptz' => 'datetime',
		'timetz' => 'time',
		'tsquery' => Null,
		'tsrange' => Null,
		'tsvector' => Null,
		'uuid' => Null,
		'varbit' => Null,
		'varchar' => Null,
		'xml' => 'xml',
	);

	/**
	 * $connection_params (string or array): 
	 * $force_new_connection (boolean): If False (default), will return an existing connection (if one is present) based on the connection parameters passed. If True, will always return a new connection, regardless if one currently exists with the parameters passed.
	 */
	public function __construct($connection_alias, $connection_params = False) {
		$connection_datatype = gettype($connection_params);
		
		// Ensure PHPG key exists in GLOBALS variable.
		if(!array_key_exists('PHPG', $GLOBALS)) {
			$GLOBALS['PHPG'] = array();
		}

		if($connection_params === False) { // Retrieve existing connection by connection alias.
			if(array_key_exists($connection_alias, $GLOBALS['PHPG'])) {
				$this->connection_alias = $connection_alias;
			}
			return; // Connection found, no reason to continue.
		} else if($connection_datatype == 'string') { // String
			$connection_string = $connection_params;
		} else if($connection_datatype == 'array') { // Array
			$connection_string = array();
			if(isset($connection_params['host'])) {
				$connection_string[] = "host='" . @pg_escape_string($connection_params['host']) . "'";
			}
			if(isset($connection_params['port'])) {
				$connection_string[] = "port='" . @pg_escape_string($connection_params['port']) . "'";
			}
			if(isset($connection_params['dbname'])) {
				$connection_string[] = "dbname='" . @pg_escape_string($connection_params['dbname']) . "'";
			}
			if(isset($connection_params['user'])) {
				$connection_string[] = "user='" . @pg_escape_string($connection_params['user']) . "'";
			}
			if(isset($connection_params['password'])) {
				$connection_string[] = "password='" . @pg_escape_string($connection_params['password']) . "'";
			}
			if(isset($connection_params['options'])) {
				$connection_string[] = "options='" . @pg_escape_string($connection_params['options']) . "'";
			}
			$connection_string = implode(' ', $connection_string);
		} else { // Invalid data type
			throw new PHPG_Exception('Invalid conection params. Expecting string or array, encountered "' . $connection_datatype . '"');
		}

		// Establish connection if it doesn't currently exist.
		// If you attempt to create two connections with the same alias, the second connection is ignored, and will continue to use the first alias's connection.
		if(!array_key_exists($connection_alias, $GLOBALS['PHPG'])) {
			$GLOBALS['PHPG'][$connection_alias] = array(
				'queries' => array(),
				'connection' => $this->connect($connection_string)
			);
		}

		// Set the object's alias for this connection.
		$this->connection_alias = $connection_alias;
	}

	private function connect($connection_string) {
		$connection = pg_connect($connection_string, PGSQL_CONNECT_FORCE_NEW);

		// Allows for commit and rollback operations
		pg_query($connection, 'BEGIN;');

		return $connection;
	}

	public function execute($query_alias, $query) {
		$query_alias = $this->sanitize_alias($query_alias, False);

		$resource = @pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], $query);

		if($resource === False) {
			throw new PHPG_Exception('An error was encountered with your query: ' . pg_last_error($GLOBALS['PHPG'][$this->connection_alias]['connection']));
		}

		// If an update or delete is performed, in most cases data will not
		// be returned. In these cases, we don't care about specifying an
		// alias.
		if($query_alias) {
			$GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias] = array(
				'resource' => $resource,
				'current' => False,
				'row-count' => Null,
				'rows-affected' => Null
			);
		}
		return True;
	}

	public function rollback() {
		// TODO: Add error checking
		pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "ROLLBACK;");
		pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "BEGIN;");
		return True;
	}

	public function commit() {
		// TODO: Add error checking
		pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "COMMIT;");
		pg_query($GLOBALS['PHPG'][$this->connection_alias]['connection'], "BEGIN;");
		return True;
	}

	public function fetchone($query_alias) {
		// TODO: Add error checking
		$query_alias = $this->sanitize_alias($query_alias);
		return $this->iter($query_alias);
	}

	public function iter($query_alias) {
		$query_alias = $this->sanitize_alias($query_alias);

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

		// Iterate over each field, converting data to PHP native data types
		// where applicable.

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

	public function reset($alias) {
		return $this->seek($alias, 0);
	}

	public function seek($alias, $row_num) {
		$alias = $this->sanitize_alias($alias);
		return pg_result_seek($GLOBALS['PHPG'][$this->connection_alias]['queries'][$query_alias]['connection'], $row_num);
	}

	private function sanitize_alias($alias, $alias_exists = True) {
		$alias = strtolower((string) $alias);
		$alias = pg_escape_string($alias);

		return $alias;
	}

	public function escape($string) {
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

	private function _transform_integer($value, $data_type) {
		if(gettype($value) == 'array') {
			$value = array_map('intval', $value);
		} else {
			$value = intval($value);
		}
		return $value;
	}

	private function _transform_float($value, $data_type) {
		if(gettype($value) == 'array') {
			$value = array_map('floatval', $value);
		} else {
			$value = floatval($value);
		}
		return $value;
	}

	private function _transform_boolean($value, $data_type) {
		if(gettype($value) == 'array') {
			$value = array_map('boolval', $value);
		} else {
			$value = boolval($value);
		}
		return $value;
	}

	private function _transform_binary($value, $data_type) {
		return pg_unescape_bytea($value);
	}

	private function _transform_time($value, $data_type) {
		// TODO: What should we do with time? Does PHP have a native Time object?
		// Example: "time" 15:11:12.370488 (H = 2-digit hour, i = 2-digit minute, s = 2-digit second, u = 6-digit micro-second)
			// $value = DateTime::createFromFormat('H:i:s.u', $timezone);
		// Example: "timetz" 15:10:55.802597-05 (H = 2-digit hour, i = 2-digit minute, s = 2-digit second, u = 6-digit micro-second, P = 4-digit plus sign timezone)
			// $value = DateTime::createFromFormat('H:i:s.uP', $timezone);
		return $value;
	}

	private function _transform_datetime($value, $data_type) {
		// Create a DateTime object out of string passed. Time zone is auto-
		// maritcally calculated from time-zone defined at end of string.
		// Eg: "-05".
		$value = new DateTime($value);

		return $value;
	}

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
	
	private function _transform_geo_point($value, $data_type) {
		$value = str_replace('(', '', $value);
		$value = str_replace(')', '', $value);
		$value = explode(',', $value);
		return array(
			'x' => $value[0],
			'y' => $value[1]
		);
	}
	
	private function _transform_geo_polygon($value, $data_type) {
		$value = str_replace('', '', $value);
		$value = str_replace(']', '', $value);
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
}
