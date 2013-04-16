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
  /***********************************\
	*                                   *
	*     HSTORE: PHP => POSTGRESQL     *
	*                                   *
	\***********************************/
	public static function hstoreFromPhp($php_array, $hstore_array = False) {
		if($hstore_array) {
			// Converts a PHP array of Associative Arrays to a PostgreSQL
			// Hstore Array. PostgreSQL Data Type: "hstore[]"
			$pg_hstore = array();
			foreach($php_array as $php_hstore) {
				$pg_hstore[] = self::_hstoreFromPhpHelper($php_hstore);
			}

			// Convert the PHP Array of Hstore Strings to a single
			// PostgreSQL Hstore Array.
			$pg_hstore = self::arrayFromPhp($pg_hstore);
		} else {
			// Converts a single one-dimensional PHP Associative Array
			// to a PostgreSQL Hstore. PostgreSQL Data Type: "hstore"
			$pg_hstore = self::_hstoreFromPhpHelper($php_array);
		}
		return $pg_hstore;
	}

	private static function _hstoreFromPhpHelper(array $php_hstore) {
        	$pg_hstore = array();

        	foreach ($php_hstore as $key => $val) {
			$search = array('\\', "'", '"');
			$replace = array('\\\\', "''", '\"');

			$key = str_replace($search, $replace, $key);
			$val = $val === NULL
				? 'NULL'
				: '"' . str_replace($search, $replace, $val) . '"';

			$pg_hstore[] = sprintf('"%s"=>%s', $key, $val);
		}

		return sprintf("%s", implode(',', $pg_hstore));
	}

	/***********************************\
	*                                   *
	*     HSTORE: POSTGRESQL => PHP     *
	*                                   *
	\***********************************/
	public static function hstoreToPhp($string) {
		// If first and last characters are "{" and "}", then we know we're
		// working with an array of Hstores, rather than a single Hstore.
		if(substr($string, 0, 1) == '{' && substr($string, -1, 1) == '}') {
			$array = self::arrayToPhp($string, 'hstore');
			$hstore_array = array();
			foreach($array as $hstore_string) {
				$hstore_array[] = self::_hstoreToPhpHelper($hstore_string);
			}
		} else {
			$hstore_array = self::_hstoreToPhpHelper($string);
		}
		return $hstore_array;
	}

	private static function _hstoreToPhpHelper($string) {
		if(!$string || !preg_match_all('/"(.+)(?<!\\\)"=>(NULL|""|".+(?<!\\\)"),?/U', $string, $match, PREG_SET_ORDER)) {
			return array();
		}
		$array = array();

		foreach ($match as $set) {
			list(, $k, $v) = $set;
			$v = $v === 'NULL'
				? NULL
				: substr($v, 1, -1);

			$search = array('\"', '\\\\');
			$replace = array('"', '\\');

			$k = str_replace($search, $replace, $k);
			if ($v !== NULL)
			$v = str_replace($search, $replace, $v);

			$array[$k] = $v;
		}
		return $array;
	}

	/**********************************\
	*                                  *
	*     ARRAY: POSTGRESQL => PHP     *
	*                                  *
	\**********************************/
	public static function arrayToPhp($string, $pg_data_type) {
		if(substr($pg_data_type, -2) != '[]') {
			// PostgreSQL arrays are signified by
			$pg_data_type .= '[]';
		}

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

	/**********************************\
	*                                  *
	*     ARRAY: PHP => POSTGRESQL     *
	*                                  *
	\**********************************/
	public static function arrayFromPhp($array) {
		$return = '';
		foreach($array as $array_value) {
			if($return) {
				$return .= ',';
			}
			$array_value = str_replace("\\", "\\\\", $array_value);
			$array_value = str_replace("\"", "\\\"", $array_value);
			$return .= '"' . $array_value . '"';
		}
		return '{' . $return . '}';
	}
}
