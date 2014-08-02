<?php

/**
 * Author: Joshua D. Burns <josh@messageinaction.com>
 * Web Site: http://www.youlikeprogramming.com
 *           http://www.messageinaction.com
 * Git Hub: http://github.com/JDBurnZ/PHPG
 *
 * A stand-alone version of the PHPG library which enables the conversion of
 * hstore and array data-types without the need of completely re-factoring your
 * code.
 *
 * LICENSE:
 *
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <http://unlicense.org/>
 * */

class PHPG_Utils {
	// HSTORE: PHP Associative Array to POSTGRESQL Hstore
	public static function hstoreFromPhp($php_array, $hstore_array = False, $auto_escape = False) {
		// Determine whether the PHP Array passed is an array of Associative
		// Arrays, or a single-dimensional Associative Array.
		$array_of_hstores = False;

		// DOESNT WORK RIGHT, need to remove.
		if($hstore_array === True) {
			// Only properly detects an array of hstores if the array's
			// offset starts at zero, and each entry from there is
			// sequentially in order.

			// If auto-detection of an array of hstores fails, one will
			// have to pass the optional second parameter $hstore_array,
			// with a value of True to force treatment as an array of
			// hstores.
			$expected_offset = 0;
			foreach($php_array as $key => $val) {
				if($expected_offset++ !== $key) {
					$array_of_hstores = True;
					break;
				}
			}
		}

		if($hstore_array || ($hstore_array === Null && $array_of_hstores)) { // Array of Associative Arrays.
			// Iterate over each Associative Array within the PHP Array,
			// converting each entry into a PostgreSQL Hstore String and
			// appending that string to our PHP Array.
			$pg_hstore = array();
			foreach($php_array as $php_hstore) {
				$pg_hstore[] = self::_hstoreFromPhpHelper($php_hstore, $auto_escape);
			}

			// Convert our PHP Array of Hstore Strings to a single
			// PostgreSQL Hstore Array String, which is fit for sending to
			// PostgreSQL.
			$pg_hstore = self::pgArrayFromPhp($pg_hstore, $auto_escape);
		} else { // Associative Array.
			// Convert a single-dimensional PHP Associative Array to a
			// PostgreSQL Hstore String, fit for sending to PostgreSQL.
			$pg_hstore = self::_hstoreFromPhpHelper($php_array, $auto_escape);
		}

		// Return the PostgreSQL Hstore String.
		return $pg_hstore;
	}

	// Helper method for hstoreFromPhp().
	private static function _hstoreFromPhpHelper($php_hstore, $auto_escape = False) {
		$pg_hstore = array();

		foreach($php_hstore as $key => $val) {
			$search = array('\\', '"');
			$replace = array('\\\\', '\"');

			$key = str_replace($search, $replace, $key);
			if($auto_escape) {
				$key = pg_escape_string($key);
			}
			if($val === NULL) {
				$val = 'NULL';
			} else {
				$val = '"' . str_replace($search, $replace, $val) . '"';
				if($auto_escape) {
					$val = pg_escape_string($val);
				}
			}

			$pg_hstore[] = sprintf('"%s"=>%s', $key, $val);
		}

		return implode(',', $pg_hstore);
	}

	// HSTORE: POSTGRESQL Hstore to PHP Associative Array
	public static function hstoreToPhp($string) {
		// If first and last characters are "{" and "}", then we know we're
		// working with an array of Hstores, rather than a single Hstore.
		if(substr($string, 0, 1) == '{' && substr($string, -1, 1) == '}') {
			// Convert the hstore string to a PHP Array of hstore strings.
			$array = self::arrayToPhp($string, 'hstore');

			// Iterate over each element in the array, converting each
			// hstore string into an Associative PHP Array, appending it
			// to our final hstore array.
			$hstore_array = array();
			foreach($array as $hstore_string) {
				$hstore_array[] = self::_hstoreToPhpHelper($hstore_string);
			}
		} else {
			// Convert the hstore string into an Associative PHP Array.
			$hstore_array = self::_hstoreToPhpHelper($string);
		}
		// Return our hstore array.
		return $hstore_array;
	}

	// Helper method for hstoreToPhp().
	private static function _hstoreToPhpHelper($string) {
		$string_length = strlen($string);

		// TODO: Check if array and act appropriately.
		$return = array();
		$prev = Null;
		$quoted = False;
		$parsing = 'key';
		$key = '';
		$part = Null;
		$skip = 0;
		for($i=0; $i < $string_length; $i++) {
			$character = $string[$i];
		//foreach($string as $character) {
			if($skip) { // Sometimes we need to ignore the next character or two.
				$skip--;
				continue;
			}
			if($prev === Null && $character === '"') { // First character, look for starting double-quotes
				$quoted = True;
				$prev = $character;
				continue; // We're through with this iteration of the loop.
			} else {
				if($quoted) {
					if($character === '\\') {
						$prev = $character;
						continue;
					}
					if($character === '"' && $prev !== '\\') {
						$prev = Null;
						$quoted = False;
						if($parsing === 'key') { // Finished parsing quoted key.
							$parsing = 'value';
							$key = $part; // Store the key for reference later.
							$skip = 2; // Skip trailing "=" and ">"
						} else { // Finished parsing quoted value.
							$parsing = 'key';
							$return[$key] = $part; // Store the key/value combination.
							$key = '';
							$skip = 2; // Skip trailing "," and space.
						}
						$part = Null;
						continue; // We're through with this iteration of the loop.
					}
				} else {
					if($part === Null && $character === ',') { // If previous value was quoted, will encounter rogue comma.
						$skip = 1; // Skip trailing space
						continue;
					}
					if($parsing === 'key' && $character === '=') { // End of key, denoted by "=>".
						$prev = Null;
						$quoted = False;
						$parsing = 'value';
						$key = $part; // Store the key for reference later.
						$skip = 1; // Skip trailing ">".
						$part = Null;
						continue; // We're through with this iteration of the loop.
					} else if($parsing === 'value' && $character === ',') { // End of value, demoted by ",".
						$prev = Null;
						$quoted = False;
						$parsing = 'key';
						$return[$key] = $part; // Store the key/value combination.
						$key = '';
						$part = Null;
						$skip = 1; // Skip trailing space
						continue; // We're through with this iteration of the loop.
					}
				}
				if($part === Null) {
					$part = '';
				}
				$prev = $part;
				$part .= $character;
			}
		}
		if($prev !== Null || $part !== Null) {
			$return[$key] = $part;
		}
		return $return;
	}

	// ARRAY: POSTGRESQL Array to PHP Array
	public static function arrayToPhp($string, $pg_data_type) {
		// If the string passed is Null, we cant to preserve its value.
		if($string === null) {
			return Null;
		}
		// Ensure string starts with "{". Otherwise, PostgreSQL will freak
		// out.
		if($string[0] !== '{') {
			$string = '{' . $string;
		}
		// Likewise, ensure string ends with "}".
		if(substr($string, -1) !== '}') {
			$string .= '}';
		}

		// Ensure the data-type passed ends with "[]". This informs
		// PostgreSQL the data-type passed is to be parsed as an array.
		if(substr($pg_data_type, -2) !== '[]') {
			$pg_data_type .= '[]';
		}

		// TODO: Shouldn't $string be encapsulated in pg_escape_string()?
		$grab_array_values = pg_query("SELECT UNNEST('" . pg_escape_string($string) . "'::" . $pg_data_type . ") AS value");
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

	// ARRAY: PHP Array to POSTGRESQL Array
	public static function arrayFromPhp($array, $auto_escape = False) {
		$return = '';
		foreach($array as $array_value) {
			if($return) {
				$return .= ',';
			}

			// If a Null value is encountered, append it without the
			// encapsulation of quotes.
			if($array_value === Null) {
				$return .= 'NULL';
				continue;
			}

			// Escape any back-slashes or double-quotes which may be
			// present within this array entry's value.
			$array_value = str_replace("\\", "\\\\", $array_value);
			$array_value = str_replace("\"", "\\\"", $array_value);

			if($auto_escape) {
				$array_value = pg_escape_string($array_value);
			}

			$return .= '"' . $array_value . '"';
		}
		return '{' . $return . '}';
	}

	// RECORD: PHP Array to POSTGRESQL Record
	public static function recordFromPhp($array, $data_type = 'string', $auto_escape = True) {
		// Valid $data_type: string, integer, numeric, decimal.
		if($data_type == 'integer') {
			// Filter out any entries which are not strictly numeric
			// characters, being characters 0 through 9.
			$array = array_filter($array, 'ctype_digit');
		} else if($data_type == 'numeric') {
			// Filter out any entries which do not contain numeric values.
			// Numeric values include:
			// - Integer: 0-9
			// - Decimal: 0.91
			// - Hexadecimal: 0xf4c3b00c
			// - Octal: 0777
			// - Exponential: +0123.45e6
			// - Binary: 0b10100111001
			$array = array_filter($array, 'is_numeric');
		} else if($data_type == 'decimal') {
			// Filter out any entries which are not strictly decimal
			// values such as 1.2 or 123.456789.
			$array = preg_filter('/^\d+\.\d+$/', $array);
		}
		if($auto_escape) {
			$array = array_map($array, 'pg_escape_string');
		}
		if(empty($array)) {
			return '';
		}
		return "'" . implode("','", $array) . "'";
	}

	public static function dateToPhp($date_string, $format = False) {
		$timestamp = self::pgDateToPhpTimestamp($date_string);
		if($format === False) {
			$format = 'n/j/Y';
		}
		return date($format, $timestamp);
	}

	public static function dateToPhpTimestamp($date_string) {
		return strtotime($date_string);
	}

	public static function dateFromPhpString($datetime) {
		return date('Y-m-d H:i O', strtotime($datetime));
	}

	public static function dateFromPhpEpoch($timestamp) {
		return date('Y-m-d H:i O', $timestamp);
	}
}
