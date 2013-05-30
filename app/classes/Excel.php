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
		ENCODING_BINARY = 'bin',
		ENCODING_UTF8 = 'utf8', // default
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

	private $encoding = self::ENCODING_UTF8;
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
			if ($type = gettype($this->data) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}
		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];
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

		$this->printTableHeader();
		$this->printTableBody();

		if (!DEBUG) die();
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


	private function printTableHeader() {
		if ($this->encoding === self::ENCODING_BINARY) echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
		elseif ($this->encoding === self::ENCODING_UTF16) echo "\xFF\xFE" . mb_convert_encoding("<table>\n", 'UTF-16LE') . "\n";
		else echo "<table>\n";

		for ($header = '', $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			if ($this->encoding === self::ENCODING_BINARY) $header .= $this->getString($this->columns[$i][self::COLUMN_HEADER], $i, 0);
			else $header .= ($i ? '<td>' : '<tr><td>') . $this->columns[$i][self::COLUMN_HEADER] . ($i === $j - 1 ? "</td></tr>\n" : '</td>');
		}

		echo $this->encoding === self::ENCODING_UTF16 ? mb_convert_encoding($header, 'UTF-16LE') : $header;
	}


	private function printTableBody() {
		for ($columns = array(), $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			$columns[$i] = (array) $this->columns[$i] + array(
				self::COLUMN_FORMAT => self::FORMAT_TEXT,
				self::COLUMN_HEADER => isset($this->resColumns[$i]) ? $this->resColumns[$i] : 'Sloupec ' . ($i + 1),
				self::COLUMN_PREFIX => '',
				self::COLUMN_POSTFIX => '',
				self::COLUMN_FOOTER_FUNCTION => ''
			);
			$func = $columns[$i][self::COLUMN_FOOTER_FUNCTION] = strtolower(strval($columns[$i][self::COLUMN_FOOTER_FUNCTION]));
			if ($this->encoding === self::ENCODING_BINARY) {
				$columns[$i] += array(
					self::COLUMN_BIN_ALIGN => 'left',
					self::COLUMN_BIN_HEADER_ALIGN => 'left',
					self::COLUMN_BIN_FOOTER_ALIGN => 'left',
					self::COLUMN_BIN_HEADER_BACKGROUND => '#aaaaff',
					self::COLUMN_BIN_FOOTER_BACKGROUND => '#aaaaff'
				);
			}

			$num = $columns[$i]['_num'] = $columns[$i][self::COLUMN_FORMAT] === self::FORMAT_INTEGER
				|| $columns[$i][self::COLUMN_FORMAT] === self::FORMAT_FLOAT;
			$avg = $num || $columns[$i][self::COLUMN_FORMAT] === self::FORMAT_UT;
			if (!$num && $func === 'sum') $columns[$i][self::COLUMN_FOOTER_FUNCTION] = '';
			if (!$avg && $func === 'avg') $columns[$i][self::COLUMN_FOOTER_FUNCTION] = '';
		}

		if (isset($this->data)) {
			for ($i = 0, $j = count($this->data); $i < $j; ++$i) {
				for ($row = '', $k = 0, $l = count($this->data[$i]); $k < $l; ++$k) {
					switch($columns[$i][self::COLUMN_FORMAT]) {
						case self::FORMAT_INTEGER:
							$value = $var = is_int($this->data[$i][$k]) ? $this->data[$i][$k] : intval(strval($this->data[$i][$k]));
							break;
						case self::FORMAT_FLOAT:
							$value = $var = is_float($this->data[$i][$k]) ? $this->data[$i][$k] : floatval(strval($this->data[$i][$k]));
							break;
						case self::FORMAT_UT:
							$var = date('%d.%m.%Y %H:%i:%s', $value = $this->data[$i][$k]);
							break;
						default:
							$value = $var = strval($this->data[$i][$k]);
					}

					switch($columns[$i][self::COLUMN_FOOTER_FUNCTION]) {
						case 'min':
							if (!isset($columns[$i]['_calc']) || $value < $columns[$i]['_calc']) $columns[$i]['_calc'] = $value;
							break;
						case 'max':
							if (!isset($columns[$i]['_calc']) || $value > $columns[$i]['_calc']) $columns[$i]['_calc'] = $value;
							break;
						case 'avg':
						case 'sum':
							if (!isset($columns[$i]['_calc'])) $columns[$i]['_calc'] = $value;
							else $columns[$i]['_calc'] += $value;
							break;
						case 'min':
							if (!isset($columns[$i]['_temp'][0]) || $value < $columns[$i]['_temp'][0]) $columns[$i]['_temp'][0] = $value;
					}


					if ($this->encoding === self::ENCODING_BINARY) {
						$row .= $num ? $this->getNumber($var, $i, $k) : $this->getString($var, $i, $k);
					} else {
						$row .= ($k ? '<td>' : '<tr><td>') . $this->columns[$i][self::COLUMN_HEADER] . ($k === $l - 1 ? "</td></tr>\n" : '</td>');
					}
				}

				echo $this->encoding === self::ENCODING_UTF16 ? mb_convert_encoding($row, 'UTF-16LE') : $row;
			}

		}
	}


	private function printTableFooter() {
		if ($encoding === self::ENCODING_BINARY) echo pack("ss", 0x0A, 0x00);
		elseif ($encoding === self::ENCODING_UTF16) echo mb_convert_encoding("<table>\n", 'UTF-16LE');
		else echo "</table>\n";
	}


	private function getString($var, $column, $row) {
		return $var;
	}


	private function getNumber($var, $column, $row) {
		return $var;
	}
}

/*
 *     $result=mysql_query("select * from tbl_name");
    function xlsBOF()
    {
    echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
    return;
    }
    function xlsEOF()
    {
    echo pack("ss", 0x0A, 0x00);
    return;
    }
    function xlsWriteNumber($Row, $Col, $Value)
    {
    echo pack("sssss", 0x203, 14, $Row, $Col, 0x0);
    echo pack("d", $Value);
    return;
    }
    function xlsWriteLabel($Row, $Col, $Value )
    {
    $L = strlen($Value);
    echo pack("ssssss", 0x204, 8 + $L, $Row, $Col, 0x0, $L);
    echo $Value;
    return;
    }

    xlsBOF();

    xlsWriteLabel(0,0,"Heading1");
    xlsWriteLabel(0,1,"Heading2");
    xlsWriteLabel(0,2,"Heading3");
    $xlsRow = 1;
    while($row=mysql_fetch_array($result))
    {
    xlsWriteNumber($xlsRow,0,$row['field1']);
    xlsWriteLabel($xlsRow,1,$row['field2']);
    xlsWriteLabel($xlsRow,2,$row['field3']);
    $xlsRow++;
    }
    xlsEOF();
 */
