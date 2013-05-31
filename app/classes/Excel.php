<?php

/*
 * $table is non-associative array of non-associtive arrays (rows) containing data or MySQL resource
 * $columns is non-associative array of associtive arrays (columns):
 * 	format: cell data format (number, ut, defaults to text)
 * 	header: header of column
 * 	pre: prefix of every data in given column
 * 	post: postfix of every data in given column
 * 	funct: PHP aggregate function (min, max, avg, sum), which is calculated on all data in column, or string
 * (ONLY for binary)
 * 	align: align of text in cell (right, center, defaults to left)
 * 	halign: align of header in cell (right, center, defaults to left)
 * 	falign: align of footer in cell (right, center, defaults to left)
 * 	hback: background of header cell
 * 	fback: background of footer cell
 */

class Excel {
	const
		FILE_ENCODING = 'encoding',
		ENCODING_BINARY = 'bin', // default
		ENCODING_UTF8 = 'utf8',
		ENCODING_UTF16 = 'utf16',

		EXPORT_FILENAME = 'filename',
		TABLE_SOURCE = 'table',
		TABLE_COLUMNS = 'columns',
		COLUMN_FORMAT = 'format',
		COLUMN_HEADER = 'header',
		COLUMN_PREFIX = 'pre',
		COLUMN_POSTFIX = 'post',
		COLUMN_FOOTER_FUNCTION = 'func',
		COLUMN_BIN_ALIGN = 'align',
		COLUMN_BIN_HEADER_ALIGN = 'halign',
		COLUMN_BIN_FOOTER_ALIGN = 'falign',
		COLUMN_BIN_HEADER_BACKGROUND = 'hback',
		COLUMN_BIN_FOOTER_BACKGROUND = 'fback',

		FORMAT_TEXT = 'text',
		FORMAT_INTEGER = 'int',
		FORMAT_FLOAT = 'float',
		FORMAT_UT = 'ut';

	public $id;
	public $container;

	private $encoding = self::ENCODING_BINARY;
	private $filename = 'document';
	private $columns = array();
	private $data;
	private $resource;
	private $resColumns = array();


	public function __construct($id, $container) {
		if ($container instanceof App) {
			$this->id = $id;
			$this->container = $container;
			App::lg("Vytvoren exporter pro Excel '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor exporteru pro Excel ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
	}


	public function config($options = array()) {
		if (!is_array($options)) throw new Exception("Konfigurator exporteru pro Excel ocekava pole s konfiguraci.");

		if (!empty($options[self::FILE_ENCODING])) $this->encoding = strtolower($options[self::FILE_ENCODING]);
		if (!empty($options[self::EXPORT_FILENAME])) $this->filename = $options[self::EXPORT_FILENAME];
		if (!empty($options[self::TABLE_SOURCE])) {
			if (($type = gettype($options[self::TABLE_SOURCE])) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}
		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];
		App::lg('Nactena konfigurace', $this);
	}

	public function go() {
		if (isset($this->resource) && $row = mysql_fetch_assoc($this->resource)) {
			$this->resColumns = array_keys($row);
			$this->data = array(array_values($row));
		} elseif (empty($this->data)) $this->data = array(array());

		if (($i = count($this->columns)) > ($j = count($this->data[0]))) array_splice($this->columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $this->columns[$i] = array();

		if (DEBUG) App::lg('Tabulka pripravena pro export', $this);
		else $this->sendHead();

		$this->printTable();

		if (!DEBUG) {
//			if ($this->container) $this->container->stop = TRUE;
			die;
		}

		App::lg('Tabulka vyexportovana', $this);
	}


	private function sendHead() {
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Disposition: attachment; filename=$this->filename.xls");

		if ($this->encoding === self::ENCODING_BINARY) {
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Transfer-Encoding: binary");
		} elseif ($this->encoding === self::ENCODING_UTF16) {
			header("Content-Type: application/vnd.ms-excel; charset=utf-16");
		} else {
			header("Content-Type: application/vnd.ms-excel; charset=utf-8");
		}
	}


	private function printTable() {
		$columns = $this->prepareColumns();

		$this->printTableHeader($columns);

		$rowNum = 0;
		if (isset($this->data)) {
			for ($j = count($this->data); $rowNum < $j; ++$rowNum) {
				$this->printOneRow($this->data[$rowNum], $rowNum + 1, $columns);
			}
		}
		if (isset($this->resource)) {
			while ($row = mysql_fetch_row($this->resource)) {
				$this->printOneRow($row, ++$rowNum, $columns);
			}
		}

		$this->printTableFooter($rowNum, $columns);
	}


	private function prepareColumns() {
		for ($columns = array(), $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			$col = &$columns[$i];

			$col = (array) $this->columns[$i] + array(
				self::COLUMN_FORMAT => self::FORMAT_TEXT,
				self::COLUMN_HEADER => isset($this->resColumns[$i]) ? $this->resColumns[$i] : 'Sloupec ' . ($i + 1),
				self::COLUMN_PREFIX => '',
				self::COLUMN_POSTFIX => '',
				self::COLUMN_FOOTER_FUNCTION => ''
			);
			
			$func = $col[self::COLUMN_FOOTER_FUNCTION] = strtolower(strval($col[self::COLUMN_FOOTER_FUNCTION]));
			
			if ($this->encoding === self::ENCODING_BINARY) {
				$col += array(
					self::COLUMN_BIN_ALIGN => 'left',
					self::COLUMN_BIN_HEADER_ALIGN => 'left',
					self::COLUMN_BIN_FOOTER_ALIGN => 'left',
					self::COLUMN_BIN_HEADER_BACKGROUND => '#aaaaff',
					self::COLUMN_BIN_FOOTER_BACKGROUND => '#aaaaff'
				);
			}

			$col['_prePostLen'] = strlen($col[self::COLUMN_PREFIX] . $col[self::COLUMN_POSTFIX]);
			$num = $col['_num'] = $col[self::COLUMN_FORMAT] === self::FORMAT_INTEGER
				|| $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT;
			$avg = $num || $col[self::COLUMN_FORMAT] === self::FORMAT_UT;
			if (!$num && $func === 'sum') $col[self::COLUMN_FOOTER_FUNCTION] = '';
			if (!$avg && $func === 'avg') $col[self::COLUMN_FOOTER_FUNCTION] = '';
		} unset($col);

		return $columns;
	}


	private function printTableHeader(&$columns) {
		if ($this->encoding === self::ENCODING_BINARY) echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
		elseif ($this->encoding === self::ENCODING_UTF16) echo "\xFF\xFE" . mb_convert_encoding("<table>\n", 'UTF-16LE', 'UTF-8');
		else echo "<table>\n";

		for ($header = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			if ($this->encoding === self::ENCODING_BINARY) $header .= $this->getString($columns[$i][self::COLUMN_HEADER], 0, $i);
			else $header .= ($i ? '<td>' : '<tr><td>') . $columns[$i][self::COLUMN_HEADER] . ($i === $j - 1 ? "</td></tr>\n" : '</td>');
		}

		echo $this->encoding === self::ENCODING_UTF16 ? mb_convert_encoding($header, 'UTF-16LE', 'UTF-8') : $header;
	}


	private function printOneRow($row, $rowNum, &$columns) {
		for ($rowText = '', $i = 0, $j = count($row); $i < $j; ++$i) {
			$col = &$columns[$i];

			switch($col[self::COLUMN_FORMAT]) {
				case self::FORMAT_INTEGER:
					$value = $var = is_int($row[$i]) ? $row[$i] : intval(strval($row[$i]));
					break;
				case self::FORMAT_FLOAT:
					$value = $var = is_float($row[$i]) ? $row[$i] : floatval(strval($row[$i]));
					break;
				case self::FORMAT_UT:
					$var = date('%d.%m.%Y %H:%i:%s', $value = $row[$i]);
					break;
				default:
					$value = $var = strval($row[$i]);
			}

			switch($col[self::COLUMN_FOOTER_FUNCTION]) {
				case 'min':
					if (!isset($col['_calc']) || ($col['_num'] ? $value < $col['_calc'] : strcasecmp($value, $col['_calc']) < 0)) {
						$col['_calc'] = $value;
					}
					break;
				case 'max':
					if (!isset($col['_calc']) || ($col['_num'] ? $value > $col['_calc'] : strcasecmp($value, $col['_calc']) > 0)) {
						$col['_calc'] = $value;
					}
					break;
				case 'avg':
				case 'sum':
					if (!isset($col['_calc'])) $col['_calc'] = $value;
					else $col['_calc'] += $value;
			}

			$num = FALSE;
			if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $var . $col[self::COLUMN_POSTFIX];
			else $num = $col['_num'];

			if ($this->encoding === self::ENCODING_BINARY) {
				$rowText .= $num ? $this->getNumber($var, $rowNum, $i) : $this->getString($var, $rowNum, $i);
			} else {
				$rowText .= ($i ? '<td>' : '<tr><td>') . $var . ($i === $j - 1 ? "</td></tr>\n" : '</td>');
			}
		} unset($col);

		echo $this->encoding === self::ENCODING_UTF16 ? mb_convert_encoding($rowText, 'UTF-16LE', 'UTF-8') : $rowText;
	}


	private function printTableFooter($rowNum, &$columns) {
		$labels = $results = '';

		for ($i = 0, $j = count($columns); $i < $j; ++$i) {
			$col = &$columns[$i];
			$result = NULL;
			$label = '';

			switch ($col[self::COLUMN_FOOTER_FUNCTION]) {
				case 'min':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Minimum';
					}
					break;
				case 'max':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Maximum';
					}
					break;
				case 'avg':
					if (isset($col['_calc'])) {
						$result = $rowNum ? $col['_calc'] / $rowNum : 0 ;
						$label = 'Prumer';
					}
					break;
				case 'sum':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Suma';
					}
			}

			$num = FALSE;

			if ($result !== NULL) {
				switch($col[self::COLUMN_FORMAT]) {
					case self::FORMAT_INTEGER:
						if ($col[self::COLUMN_FOOTER_FUNCTION] === 'avg') {
							$var = is_float($result) ? $result : floatval(strval($result));
						} else $var = is_int($result) ? $result : intval(strval($result));
						break;
					case self::FORMAT_FLOAT:
						$var = is_float($result) ? $result : floatval(strval($result));
						break;
					case self::FORMAT_UT:
						$var = date('%d.%m.%Y %H:%i:%s', $result);
						break;
					default:
						$var = strval($result);
				}

				if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $result . $col[self::COLUMN_POSTFIX];
				else $num = $col['_num'];
			} else $var = '';

			if ($this->encoding === self::ENCODING_BINARY) {
				$results .= $num ? $this->getNumber($var, $rowNum + 1, $i) : $this->getString($var, $rowNum + 1, $i);
				$labels .= $this->getString($label, $rowNum + 2, $i);
			} else {
				$results .= ($i ? '<td>' : '<tr><td>') . $var . ($i === $j - 1 ? "</td></tr>\n" : '</td>');
				$labels .= ($i ? '<td>' : '<tr><td>') . $label . ($i === $j - 1 ? "</td></tr>\n" : '</td>');
			}
		} unset($col);

		echo $this->encoding === self::ENCODING_UTF16 ? mb_convert_encoding($results . $labels, 'UTF-16LE', 'UTF-8') : $results . $labels;

		if ($this->encoding === self::ENCODING_BINARY) echo pack("ss", 0x0A, 0x00);
		elseif ($this->encoding === self::ENCODING_UTF16) echo mb_convert_encoding("<table>\n", 'UTF-16LE', 'UTF-8');
		else echo "</table>\n";
	}


	private function getString($var, $row, $col) {
		$L = strlen($var);
		return pack("ssssss", 0x204, 8 + $L, $row, $col, 0x0, $L) . $var;
	}


	private function getNumber($var, $row, $col) {
		return pack("sssss", 0x203, 14, $row, $col, 0x0) . pack("d", $var);
	}
}

