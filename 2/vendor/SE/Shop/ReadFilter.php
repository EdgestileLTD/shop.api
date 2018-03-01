<?php

namespace SE\Shop;

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Reader/IReadFilter.php';
use PHPExcel_Reader_IReadFilter;


/**  Define a Read Filter class implementing PHPExcel_Reader_IReadFilter  */
class ReadFilter implements PHPExcel_Reader_IReadFilter
{
    private $_startRow = 0;
    private $_endRow = 0;

    /**  Задайте список строк, которые мы хотим прочитать.  */
    public function setRows($startRow, $chunksize) {
        $this->_startRow    = $startRow;
        $this->_endRow      = $startRow + $chunksize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        //  Только прочитайте строку заголовка и строки, которые настроены в $ this -> _ startRow и $ this -> _ endRow
        if (($row >= $this->_startRow && $row < $this->_endRow)) {
            return true;
        }
        return false;
    }
}


/**
 * Создайте новый считыватель типа, определенного в $ inputFileType
 * Определите, сколько строк мы хотим прочитать для каждого «куска»,
 * Создайте новый экземпляр нашего фильтра чтения
 *
 * Скажите читателю, что мы хотим использовать фильтр чтения, который мы создали
 * Скажите фильтру чтения, ограничения на которые строки, которые мы хотим прочитать в этой итерации
 * Загружайте только строки, соответствующие нашему фильтру, из $ inputFileName в объект PHPExcel
 **/
/*
$objReader = PHPExcel_IOFactory::createReader($inputFileType);
$chunkSize = 500;
$chunkFilter = new ReadFilter();

$objReader->setReadFilter($chunkFilter);
$chunkFilter->setRows(1,500);
$objPHPExcel = $objReader->load($inputFileName);
*/
