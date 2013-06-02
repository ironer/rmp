<?php

/*
 * $data is non-associative array of non-associtive arrays (rows) containing data
 * $columns is non-associative array of associtive arrays (columns):
 * 	format: cell data format (number, ut, defaults to text)
 * 	header: header of column
 * 	pre: prefix of every data in given column
 * 	post: postfix of every data in given column
 * 	funct: PHP aggregate function (min, max, avg, sum), which is calculated on all data in column
 * 	align: align of text in cell (right, center, defaults to left)
 */

class HtmlTable {
	const
		TABLE_TYPE = 'type',
		TYPE_SCREEN = 'screen', // default
		TYPE_EXCEL = 'xls',
		TYPE_OPENOFFICE = 'oo',

		EXPORT_FILENAME = 'filename',
		TABLE_SOURCE = 'source',
		TABLE_COLUMNS = 'columns',

		COLUMN_FORMAT = 'format',
		COLUMN_HEADER = 'header',
		COLUMN_PREFIX = 'pre',
		COLUMN_POSTFIX = 'post',
		COLUMN_FUNCTION = 'func',
		COLUMN_ALIGN = 'align',

		TABLE_ATTRIBUTES = 'table',
		T_HEAD_ATTRIBUTES = 'thead',
		T_BODY_TR_ODD_ATTRIBUTES = 'odd',
		T_BODY_TR_EVEN_ATTRIBUTES = 'even',
		T_FOOT_ATTRIBUTES = 'tfoot',

		FORMAT_TEXT = 'text',
		FORMAT_INTEGER = 'int',
		FORMAT_FLOAT = 'float',
		FORMAT_UT = 'ut',

		FLOAT_DECIMALS = 'decs',
		DATE_FORMAT = 'date';


	public $id;
	public $container;

	private $type = self::TYPE_SCREEN;
	private $filename = 'document';
	private $columns = array();
	private $data;
	private $resource;
	private $resColumns = array();

	private $table = ' border="1" cellpadding="3px" cellspacing="0"';
	private $thead = ' bgcolor="#88ccff"';
	private $odd = '';
	private $even = ' bgcolor="#ddeeff"';
	private $tfoot = ' bgcolor="#ffeecc"';
	private $decimals = 2;
	private $dateFormat = 'j.n.Y G:i:s';
	private $debug = FALSE;


	public function __construct($id = NULL, $container = NULL) {
		$this->debug = defined('DEBUG') ? DEBUG : FALSE;

		if ($container instanceof App) {
			$this->id = $id;
			$this->container = $container;
			if ($this->debug) App::lg("Vytvoren exporter pro Excel '$this->id'", $this);
		}
	}


	public function config($options = array()) {
		if (!is_array($options)) throw new Exception("Konfigurator exporteru pro Excel ocekava pole s konfiguraci.");

		if (!empty($options[self::TABLE_TYPE]) && !$this->debug) $this->type = strtolower($options[self::TABLE_TYPE]);
		if (!empty($options[self::EXPORT_FILENAME])) $this->filename = $options[self::EXPORT_FILENAME];

		if (!empty($options[self::TABLE_SOURCE])) {
			if (($type = gettype($options[self::TABLE_SOURCE])) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}

		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];
		if (!empty($options[self::TABLE_ATTRIBUTES])) $this->table = ' ' . $options[self::TABLE_ATTRIBUTES];

		if (!empty($options[self::T_HEAD_ATTRIBUTES])) $this->thead = ' ' . $options[self::T_HEAD_ATTRIBUTES];

		if (!empty($options[self::T_BODY_TR_ODD_ATTRIBUTES])) $this->odd = ' ' . $options[self::T_BODY_TR_ODD_ATTRIBUTES];
		if (!empty($options[self::T_BODY_TR_EVEN_ATTRIBUTES])) $this->even = ' ' . $options[self::T_BODY_TR_EVEN_ATTRIBUTES];

		if (!empty($options[self::T_FOOT_ATTRIBUTES])) $this->tfoot = ' ' . $options[self::T_FOOT_ATTRIBUTES];

		if (!empty($options[self::FLOAT_DECIMALS])) $this->decimals = $options[self::FLOAT_DECIMALS];
		if (!empty($options[self::DATE_FORMAT])) $this->dateFormat = $options[self::DATE_FORMAT];

		if ($this->debug) App::lg('Nactena konfigurace', $this);
	}

	public function go() {
		if (isset($this->resource) && $row = mysql_fetch_assoc($this->resource)) {
			$this->resColumns = array_keys($row);
			$this->data = array(array_values($row));
		} elseif (empty($this->data)) $this->data = array(array());

		if (($i = count($this->columns)) > ($j = count($this->data[0]))) array_splice($this->columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $this->columns[$i] = array();

		if ($this->debug) App::lg('Tabulka pripravena pro export', $this);
		elseif ($this->type !== self::TYPE_SCREEN) $this->sendHead();

		echo $this->getTable();

		if ($this->type !== self::TYPE_SCREEN) die;
		else if ($this->container) $this->container->stop = TRUE;

		if ($this->debug) App::lg('Tabulka vyexportovana', $this);
	}


	private function sendHead() {
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Disposition: attachment; filename=$this->filename.xls");

		if ($this->type === self::TYPE_SCREEN) {
			header("Content-Type: application/vnd.ms-excel; charset=utf-8");
		} else {
			header("Content-Type: application/vnd.ms-excel; charset=utf-16");
		}
	}


	private function getTable() {
		$columns = $this->prepareColumns();

		$retText = "<table$this->table>\n" . $this->getTableHeader($columns);

		$rowNum = 0;
		if (isset($this->data)) {
			for ($j = count($this->data); $rowNum < $j; ++$rowNum) {
				$retText .= $this->printOneRow($this->data[$rowNum], $rowNum + 1, $columns);
			}
		}
		if (isset($this->resource)) {
			while ($row = mysql_fetch_assoc($this->resource)) {
				$retText .= $this->printOneRow(array_values($row), ++$rowNum, $columns);
			}
		}

		$retText .= $this->getTableFooter($rowNum, $columns) . "</table>\n";

		return $this->type === self::TYPE_SCREEN ? $retText : "\xFF\xFE" . mb_convert_encoding($retText, 'UTF-16LE', 'UTF-8');
	}


	private function prepareColumns() {
		for ($columns = array(), $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			$col = &$columns[$i];

			$col = (array) $this->columns[$i] + array(
				self::COLUMN_FORMAT => self::FORMAT_TEXT,
				self::COLUMN_HEADER => isset($this->resColumns[$i]) ? $this->resColumns[$i] : 'Sloupec ' . ($i + 1),
				self::COLUMN_PREFIX => '',
				self::COLUMN_POSTFIX => '',
				self::COLUMN_FUNCTION => '',
				self::COLUMN_ALIGN => 'left'
			);

			$func = $col[self::COLUMN_FUNCTION] = strtolower(strval($col[self::COLUMN_FUNCTION]));

			$col['_prePostLen'] = strlen($col[self::COLUMN_PREFIX] . $col[self::COLUMN_POSTFIX]);
			$col['_num'] = $col[self::COLUMN_FORMAT] === self::FORMAT_INTEGER || $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT
				|| $col[self::COLUMN_FORMAT] === self::FORMAT_UT;;

			if (!$col['_num'] && ($func === 'sum' || $func === 'avg')) $col[self::COLUMN_FUNCTION] = '';
		} unset($col);

		return $columns;
	}


	private function getTableHeader(&$columns) {
		for ($header = $colgroup = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			$colgroup .= '<col' . ($columns[$i][self::COLUMN_ALIGN] ? ' align="' . $columns[$i][self::COLUMN_ALIGN] . '"' : '') . '>';
			$header .= "<th$this->thead>" . $columns[$i][self::COLUMN_HEADER] . '</th>';
		}

		return "<colgroup>" . $colgroup . "</colgroup>\n<tr>" . $header . "</tr>\n";
	}


	private function printOneRow($row, $rowNum, &$columns) {
		$rowText = '';
		for ($i = 0, $j = count($row); $i < $j; ++$i) {
			$col = &$columns[$i];

			switch($col[self::COLUMN_FORMAT]) {
				case self::FORMAT_INTEGER:
					$value = $var = is_int($row[$i]) ? $row[$i] : intval(strval($row[$i]));
					break;
				case self::FORMAT_FLOAT:
					$value = $var = is_float($row[$i]) ? $row[$i] : floatval(strval($row[$i]));
					break;
				case self::FORMAT_UT:
					$var = date($this->dateFormat, $value = $row[$i]);
					break;
				default:
					if (strlen($value = strval($row[$i]))) {
						$var = $this->type === self::TYPE_OPENOFFICE ? "'$value" : $value;
					} else $var = $value = '';
			}

			switch($col[self::COLUMN_FUNCTION]) {
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

			$attributes = $rowNum % 2 ? $this->odd : $this->even;
			if (!$num) $attributes .= ' style="mso-number-format:\'\@\'"';
			if ($this->type !== self::TYPE_EXCEL && $col[self::COLUMN_ALIGN] != 'left') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';

			$rowText .= '<td' . $attributes . '>' . $var . '</td>';
		} unset($col);

		return '<tr' . ($this->type !== self::TYPE_EXCEL ? ' align="left"' : '') . '>' . $rowText . "</tr>\n";
	}


	private function getTableFooter($rowNum, &$columns) {
		$labels = $results = '';

		for ($i = 0, $j = count($columns); $i < $j; ++$i) {
			$col = &$columns[$i];
			$result = NULL;
			$label = '';

			switch ($col[self::COLUMN_FUNCTION]) {
				case 'min':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'min';
					}
					break;
				case 'max':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'max';
					}
					break;
				case 'avg':
					if (isset($col['_calc'])) {
						$result = $rowNum ? round($col['_calc'] / $rowNum, $this->decimals) : 0;
						$label = 'ø';
					}
					break;
				case 'sum':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Σ';
					}
			}

			$num = FALSE;

			if ($result !== NULL) {
				switch($col[self::COLUMN_FORMAT]) {
					case self::FORMAT_INTEGER:
						if ($col[self::COLUMN_FUNCTION] === 'avg') {
							$var = is_float($result) ? $result : floatval(strval($result));
						} else $var = is_int($result) ? $result : intval(strval($result));
						break;
					case self::FORMAT_FLOAT:
						$var = is_float($result) ? $result : floatval(strval($result));
						break;
					case self::FORMAT_UT:
						$var = date($this->dateFormat, $result);
						break;
					default:
						$var = strval($result);
				}

				if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $result . $col[self::COLUMN_POSTFIX];
				else $num = $col['_num'];
			} else $var = '';

			$attributes = '';
			if (!$num) $attributes .= ' style="mso-number-format:\'\@\'"';
			if ($this->type !== self::TYPE_EXCEL && $col[self::COLUMN_ALIGN] != 'left') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';

			$results .= "<td$this->tfoot$attributes>" . $var . '</td>';
			$labels .=  "<th$this->tfoot>" . $label . '</th>';
		} unset($col);

		return '<tr' . ($this->type !== self::TYPE_EXCEL ? ' align="left"' : '') . '>' . $results . "</tr>\n<tr>" . $labels . "</tr>\n";
	}

}

