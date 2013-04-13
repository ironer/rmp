<?php

/*
 * $table is non-associative array of non-associtive arrays (rows) containing data or MySQL resource
 * $columns is non-associative array of associtive arrays (columns):
 * 	header: header of column
 * 	pre: prefix of every data in given column
 * 	post: postfix of every data in given column
 * (ONLY for binary)
 * 	align: horizontal align of text in cell (right, center, defaults to left)
 * 	format: cell data format (number, ut, defaults to text)
 * 	halign: horizontal align of header in cell (right, center, defaults to left)
 * 	falign: horizontal align of footer in cell (right, center, defaults to left)
 * 	hback: background of header cell
 * 	fback: background of footer cell
 * 	footer: PHP aggregate function (min, max, avg, sum), which is calculated on all data in column, or string
 */

class Excel {
	const ENCODING_BINARY = 'bin',
			ENCODING_UTF8 = 'utf8', // default
			ENCODING_UTF16 = 'utf16';


	public function export($filename = 'document', $table, $columns = array(), $encoding = 'utf8') {
		$resColumns = array();

		$type = gettype($table);
		if ($type == 'resource' && $row = mysql_fetch_assoc($table)) {
			$resColumns = array_keys($row);
			$data = array(array_values($row));
			while ($row = mysql_fetch_array($table)) $data[] = $row;
		} elseif ($type == 'array' && isset($table[0][0])) $data = $table;
		else $data = array(array());

		if (($i = count($columns)) > ($j = count($data[0]))) array_splice($columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $columns[$i] = array('header' => $resColumns[$i] ?: 'Sloupec ' . ($i + 1));

		if (DEBUG) App::dump($columns, $table);
		else self::head($filename, $encoding);

		self::tableHeader($columns, $encoding);
		self::tableFooter($columns, $encoding);

		if (!DEBUG) die();
	}


	private function getNumber($var, $column) {

	}


	private function tableFooter($columns, $encoding) {
		if ($encoding === self::ENCODING_BINARY) {
			echo pack("ss", 0x0A, 0x00);
		} elseif ($encoding === self::ENCODING_UTF16) {
			echo mb_convert_encoding("<table>\n", 'UTF-16LE');
		} else {
			echo "</table>\n";
		}
	}

	private function tableHeader($columns, $encoding) {
		if ($encoding === self::ENCODING_BINARY) {
			echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
		} elseif ($encoding === self::ENCODING_UTF16) {
			echo "\xFF\xFE" . mb_convert_encoding("<table>\n", 'UTF-16LE');
		} else {
			echo "<table>\n";
		}

		for ($i = 0, $j = count($columns); $i < $j; ++$i) {

		}
	}


	private function head($filename, $encoding) {
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Disposition: attachment; filename=$filename.xls");

		if ($encoding === self::ENCODING_BINARY) {
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Transfer-Encoding: binary");
		} elseif ($encoding === self::ENCODING_UTF16) {
			header("Content-Type: application/vnd.ms-excel; charset=utf-16");
		} else {
			header("Content-Type: application/vnd.ms-excel");
		}
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


/*
  	private function tableHeader($header, $encoding) {
		if ($encoding === self::ENCODING_BINARY) {

		} elseif ($encoding === self::ENCODING_UTF16) {

		} else {

		}
	}

 */