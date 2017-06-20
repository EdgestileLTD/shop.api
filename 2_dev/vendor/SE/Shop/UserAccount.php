<?php

namespace SE\Shop;

use \PHPExcel as PHPExcel;
use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;
use \PHPExcel_Style_Fill as PHPExcel_Style_Fill;
class UserAccount extends Base
{
    protected $tableName = "se_user_account";
    protected $groupBy = "p.id";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'p.id, sua.operation, SUM(IF(sua.in_payee IS NULL,0,sua.in_payee)) in_pay, SUM(IF(sua.out_payee IS NULL,0,sua.out_payee)) out_pay,
                (SUM(IF(sua.in_payee IS NULL,0,sua.in_payee)) - SUM(IF(sua.out_payee IS NULL,0,sua.out_payee))) balanse,
                CONCAT_WS(" ",p.last_name,p.first_name) as name',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'person p',
                    "condition" => 'p.id = sua.user_id'
                ),
            ),
            "aggregation" => array(
                array(
                    "type" => "SUM",
                    "field" => "in_pay",
                    "name" => "totalInPay"
                ),
                array(
                    "type" => "SUM",
                    "field" => "out_pay",
                    "name" => "totalOutPay"
                ),
                array(
                    "type" => "SUM",
                    "field" => "balanse",
                    "name" => "totalBalansePay"
                )
            )
        );
    }

    public function fetch()
    {
        parent::fetch();
        foreach($this->result['items'] as $fld=>$item){
            $this->result['items'][$fld]['inPay'] = round($this->result['items'][$fld]['inPay']);
            $this->result['items'][$fld]['outPay'] = round($this->result['items'][$fld]['outPay']);
            $this->result['items'][$fld]['balanse'] = round($this->result['items'][$fld]['balanse']);
        }
        $this->result['totalInPay'] = round($this->result['totalInPay']);
        $this->result['totalOutPay'] = round($this->result['totalOutPay']);
        $this->result['totalBalansePay'] = round($this->result['totalBalansePay']);
    }

    public function export()
    {
        if (!class_exists("PHPExcel")) {
            $this->result = "Отсутствуют необходимые библиотеки для экспорта!";
            return;
        }

        $this->fetch();
        $items = $this->result['items'];
        $totalInPay = $this->result['totalInPay'];
        $totalOutPay = $this->result['totalOutPay'];
        $totalBalansePay = $this->result['totalBalansePay'];

        $this->result = null;
        $fileName = "export_account.xlsx";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";
        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle('Список операция с лиц.счета '/* . $contact["displayName"] ? $contact["displayName"] : $contact["id"]*/);
        /*$sheet->setCellValue("A1", 'Ид. № ' . $contact["id"]);
        $sheet->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $sheet->getStyle('A1')->getFill()->getStartColor()->setRGB('EEEEEE');
        $sheet->mergeCells('A1:B1');*/
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->setCellValue('A1', '#');
        $sheet->setCellValue('B1', 'Ф.И.О');
        $sheet->setCellValue('C1', 'Начисление');
        $sheet->setCellValue('D1', 'Списание');
        $sheet->setCellValue('E1', 'Остаток');
        $i = 2;


        foreach ($items as $Contact){
            $sheet->setCellValue("A$i", $Contact['id']);
            $sheet->setCellValue("B$i", $Contact['name']);
            $sheet->setCellValue("C$i", $Contact['inPay']);
            $sheet->setCellValue("D$i", $Contact['outPay']);
            $sheet->setCellValue("E$i", $Contact['balanse']);
            $i++;
        }
        $sheet->getStyle('A1:E1')->getFont()->setSize(14);
        $sheet->setCellValue("B$i", "Итого:");
        $sheet->setCellValue("C$i", $totalInPay);
        $sheet->setCellValue("D$i", $totalOutPay);
        $sheet->setCellValue("E$i", $totalBalansePay);
        /*$sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(
            PHPExcel_Style_Alignment::HORIZONTAL_CENTER);*/
        $sheet->getStyle('A2:E'.$i)->getFont()->setSize(12);

        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $objWriter->save($filePath);

        if (file_exists($filePath) && filesize($filePath)) {
            $this->result['url'] = $urlFile;
            $this->result['name'] = $fileName;
        } else $this->result = "Не удаётся экспортировать данные контакта!";
    }

}