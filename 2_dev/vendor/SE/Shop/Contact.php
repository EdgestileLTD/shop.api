<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use \PHPExcel as PHPExcel;
use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;
use \PHPExcel_Style_Fill as PHPExcel_Style_Fill;

class Contact extends Base
{
    protected $tableName = "person";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) display_name,                
                su.username username, su.password, (su.is_active = "Y") is_active',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'se_user su',
                    "condition" => 'p.id = su.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_order so',
                    "condition" => 'so.id_author = p.id AND is_delete="N"'
                )
            ),
            "patterns" => array("displayName" => "p.last_name")
        );
    }

    public function info($id = null)
    {
        $id = empty($id) ? $this->input["id"] : $id;
        try {
            $u = new DB('person', 'p');
            $u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) display_name,
                p.avatar imageFile,
                su.username login, su.password, (su.is_active = "Y") isActive, uu.company, uu.director,
                uu.tel, uu.fax, uu.uradres, uu.fizadres');
            $u->leftJoin('se_user su', 'p.id=su.id');
            $u->leftJoin('user_urid uu', 'uu.id=su.id');
            $contact = $u->getInfo($id);
            $contact['groups'] = $this->getGroups($contact['id']);
            $contact['companyRequisites'] = $this->getCompanyRequisites($contact['id']);
            $contact['personalAccount'] = $this->getPersonalAccount($contact['id']);
            $contact['accountOperations'] = (new BankAccountTypeOperation())->fetch();
            if ($count = count($contact['personalAccount']))
                $contact['balance'] = $contact['personalAccount'][$count - 1]['balance'];
            $this->result = $contact;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о контакте!";
        }

        return $this->result;
    }

    private function getPersonalAccount($id)
    {
        $u = new DB('se_user_account');
        $u->where('user_id = ?', $id);
        $u->orderBy("date_payee");
        $result = $u->getList();
        $account = array();
        $balance = 0;
        foreach ($result as $item) {
            $balance += ($item['inPayee'] - $item['outPayee']);
            $item['balance'] = $balance;
            $account[] = $item;
        }
        return $account;
    }

    private function getCompanyRequisites($id)
    {
        $u = new DB('user_rekv_type', 'urt');
        $u->select('ur.id, ur.value, urt.code rekv_code, urt.size, urt.title');
        $u->leftJoin('user_rekv ur', "ur.rekv_code = urt.code AND ur.id_author = {$id}");
        $u->groupBy('urt.code');
        $u->orderBy('urt.id');
        return $u->getList();
    }

    private function getGroups($id)
    {
        $u = new DB('se_group', 'sg');
        $u->select('sg.id, sg.title name');
        $u->innerjoin('se_user_group sug', 'sg.id = sug.group_id');
        $u->where('sg.title IS NOT NULL AND sg.name <> "" AND sg.name IS NOT NULL AND sug.user_id = ?', $id);
        return $u->getList();
    }


    private function getUserName($lastName, $userName, $id = 0)
    {
        if (empty($userName))
            $userName = strtolower(rus2translit($lastName));
        $username_n = $userName;

        $u = new DB('se_user', 'su');
        $i = 2;
        while ($i < 1000) {
            if ($id)
                $result = $u->findList("su.username='$username_n' AND id <> $id")->fetchOne();
            else $result = $u->findList("su.username='$username_n'")->fetchOne();
            if ($result["id"])
                $username_n = $userName . $i;
            else return $username_n;
            $i++;
        }
        return uniqid();
    }

    private function saveGroups($groups, $idsContact)
    {
        try {
            $newIdsGroups = array();
            foreach ($groups as $group)
                $newIdsGroups[] = $group["id"];
            $idsGroupsS = implode(",", $newIdsGroups);
            $idsContactsS = implode(",", $idsContact);
            $u = new DB('se_user_group', 'sug');
            if ($newIdsGroups)
                $u->where("NOT group_id IN ($idsGroupsS) AND user_id IN ($idsContactsS)")->deleteList();
            else $u->where("user_id IN ($idsContactsS)")->deleteList();
            $u = new DB('se_user_group', 'sug');
            $u->select("group_id");
            $u->where("user_id IN ($idsContactsS)");
            $objects = $u->getList();
            $idsExists = array();
            foreach ($objects as $object)
                $idsExists[] = $object["groupId"];
            if (!empty($newIdsGroups)) {
                foreach ($newIdsGroups as $id)
                    if (!empty($id) && !in_array($id, $idsExists))
                        foreach ($idsContact as $idContact)
                            $data[] = array('user_id' => $idContact, 'group_id' => $id);
                if (!empty($data))
                    DB::insertList('se_user_group', $data);
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы контакта!";
            throw new Exception($this->error);
        }
    }

    private function saveCompanyRequisites($id, $input)
    {
        try {
            foreach ($input["companyRequisites"] as $requisite) {
                $u = new DB("user_rekv");
                $requisite["idAuthor"] = $id;
                $u->setValuesFields($requisite);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить реквизиты компании!";
            throw new Exception($this->error);
        }
    }

    private function savePersonalAccounts($id, $accounts)
    {
        try {
            $idsUpdate = null;
            foreach ($accounts as $account)
                if ($account["id"]) {
                    if (!empty($idsUpdate))
                        $idsUpdate .= ',';
                    $idsUpdate .= $account["id"];
                }

            $u = new DB('se_user_account', 'sua');
            if (!empty($idsUpdate))
                $u->where("NOT id IN ($idsUpdate) AND user_id = ?", $id)->deleteList();
            else $u->where("user_id = ?", $id)->deleteList();

            foreach ($accounts as $account) {
                $u = new DB('se_user_account');
                $account["userId"] = $id;
                $u->setValuesFields($account);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить лицевой счёт контакта!";
            throw new Exception($this->error);
        }
    }

    private function setUserGroup($idUser)
    {
        try {
            $u = new DB('se_group', 'sg');
            $u->select("id");
            $u->where("title = 'User'");
            $result = $u->fetchOne();
            $idGroup = $result["id"];
            if (!$idGroup) {
                $u = new DB('se_group', 'sg');
                $data["title"] = "User";
                $data["level"] = 1;
                $u->setValuesFields($data);
                $idGroup = $u->save();
            }

            $u = new DB('se_user_group', 'sug');
            $u->select("id");
            $u->where("sug.group_id = {$idGroup} AND sug.user_id = {$idUser}");
            $result = $u->fetchOne();
            $id = $result["id"];
            if (!$id) {
                $u = new DB('se_user_group', 'sug');
                $data["groupId"] = $idGroup;
                $data["userId"] = $idUser;
                $u->setValuesFields($data);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группу контакта!";
            throw new Exception($this->error);
        }
    }

    public function save($contact = null)
    {
        try {
            if ($contact)
                $this->input = $contact;
            DB::beginTransaction();

            $ids = array();
            if (empty($this->input["ids"]) && !empty($this->input["id"]))
                $ids[] = $this->input["id"];
            else $ids = $this->input["ids"];
            $isNew = empty($ids);
            $userName = isset($this->input["login"]) ? $this->input["login"] : null;
            if ($isNew) {
                $login = !empty($this->input["lastName"]) ? trim($this->input["lastName"]) : $this->input["firstName"];
                $this->input["username"] = $this->getUserName($login, $userName);
                if (!empty($this->input["username"])) {
                    $u = new DB('se_user', 'su');
                    $u->setValuesFields($this->input);
                    $ids[] = $u->save();
                }
            } else {
                $u = new DB('se_user', 'su');
                if (!empty($this->input["username"])) {
                    $login = !empty($this->input["lastName"]) ? trim($this->input["lastName"]) : $this->input["firstName"];
                    $this->input["username"] = $this->getUserName($login, $userName, $ids[0]);
                }
                $u->setValuesFields($this->input);
                $u->save();
            }

            if ($isNew || !empty($ids)) {
                if ($isNew)
                    $this->input["regDate"] = date("Y-m-d H:i:s");
                $this->input["id"] = $ids[0];
                if (isset($this->input["imageFile"]))
                    $this->input["avatar"] = $this->input["imageFile"];
                $u = new DB('person', 'p');
                $u->setValuesFields($this->input);
                $id = $u->save($isNew);
                if (empty($id))
                    throw new Exception("Не удаётся сохранить контакт!");

                $u = new DB('user_urid', 'uu');
                $u->setValuesFields($this->input);
                $u->save(true);

                $this->saveCompanyRequisites($ids[0], $this->input);
                if ($ids && isset($this->input["personalAccount"]))
                    $this->savePersonalAccounts($ids[0], $this->input["personalAccount"]);
                if (isset($this->input["isAdmin"]) && $this->input["isAdmin"])
                    $this->input["idsGroups"][] = 3;
                if ($ids && isset($this->input["groups"]))
                    $this->saveGroups($this->input["groups"], $ids);
                else {
                    if (isset($this->input["isAdmin"]) && !$this->input["isAdmin"]) {
                        $u = new DB('se_user_group', 'sug');
                        $u->where('group_id = 3 AND user_id = ?', $ids[0])->deleteList();
                    }
                }
                $this->setUserGroup($ids[0]);
            }
            DB::commit();
            $this->info();

            return $this;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить контакт!" : $this->error;
        }

    }

    public function export()
    {
        if (!empty($this->input["id"])) {
            $this->exportItem();
            return;
        }

        $fileName = "export_persons.csv";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $fp = fopen($filePath, 'w');
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        $header = array();
        $u = new DB('person', 'p');
        $u->select('p.reg_date regDateTime, su.username, p.last_name, p.first_name Name, p.sec_name patronymic, 
            p.sex gender, p.birth_date, p.email, p.phone, p.note');
        $u->innerJoin('se_user su', 'p.id = su.id');
        $u->leftJoin('se_user_group sug', 'p.id = sug.user_id');
        $u->groupBy('p.id');
        $u->orderBy('p.id');
        $contacts = $u->getList();
        foreach ($contacts as $contact) {
            if (!$header) {
                $header = array_keys($contact);
                $headerCSV = array();
                foreach ($header as $col) {
                    $headerCSV[] = iconv('utf-8', 'CP1251', $col);
                }
                $list[] = $header;
                fputcsv($fp, $headerCSV, ";");
            }
            $out = array();
            foreach ($contact as $r)
                $out[] = iconv('utf-8', 'CP1251', $r);
            fputcsv($fp, $out, ";");
        }
        fclose($fp);
        if (file_exists($filePath) && filesize($filePath)) {
            $this->result['url'] = $urlFile;
            $this->result['name'] = $fileName;
        } else $this->result = "Не удаётся экспортировать контакты!";
    }

    private function exportItem()
    {
        $idContact = $this->input["id"];
        if (!$idContact) {
            $this->result = "Отсутствует параметр: id контакта!";
            return;
        }
        if (!class_exists("PHPExcel")) {
            $this->result = "Отсутствуют необходимые библиотеки для экспорта!";
            return;
        }

        $contact = new Contact();
        $contact = $contact->info($idContact);
        $fileName = "export_person_{$idContact}.xlsx";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle('Контакт ' . $contact["displayName"] ? $contact["displayName"] : $contact["id"]);
        $sheet->setCellValue("A1", 'Ид. № ' . $contact["id"]);
        $sheet->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
        $sheet->getStyle('A1')->getFill()->getStartColor()->setRGB('EEEEEE');
        $sheet->mergeCells('A1:B1');
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->setCellValue("A2", 'Ф.И.О.');
        $sheet->setCellValue("B2", $contact["fullName"]);
        $sheet->setCellValue("A3", 'Телефон:');
        $sheet->setCellValue("B3", $contact["phone"]);
        $i = 4;
        if ($contact["email"]) {
            $sheet->setCellValue("A$i", 'Эл. почта:');
            $sheet->setCellValue("B$i", $contact["email"]);
            $i++;
        }
        if ($contact["country"]) {
            $sheet->setCellValue("A$i", 'Страна:');
            $sheet->setCellValue("B$i", $contact["country"]);
            $i++;
        }
        if ($contact["city"]) {
            $sheet->setCellValue("A$i", 'Город:');
            $sheet->setCellValue("B$i", $contact["city"]);
            $i++;
        }
        $sheet->setCellValue("A$i", 'Адрес:');
        $sheet->setCellValue("B$i", $contact["address"]);
        $i++;
        if ($contact["docSer"]) {
            $sheet->setCellValue("A$i", 'Документ:');
            $sheet->setCellValue("B$i", $contact["docSer"] . " " . $contact["docNum"] . " " . $contact["docRegistr"]);
        }

        $sheet->getStyle('A1:B10')->getFont()->setSize(20);
        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $objWriter->save($filePath);

        if (file_exists($filePath) && filesize($filePath)) {
            $this->result['url'] = $urlFile;
            $this->result['name'] = $fileName;
        } else $this->result = "Не удаётся экспортировать данные контакта!";
    }

    public function post()
    {
        if ($items = parent::post())
            $this->import($items[0]["name"]);
    }

    public function import($fileName)
    {
        $dir = DOCUMENT_ROOT . "/files";
        $filePath = $dir . "/{$fileName}";
        $contacts = $this->getArrayFromCsv($filePath);
        foreach ($contacts as $contact)
            $this->save($contact);
    }

}
