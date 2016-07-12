<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Contact extends Base
{
    protected $tableName = "person";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) display_name,                
                su.username, su.password, (su.is_active = "Y") is_active',
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
        $u = new DB('se_user_account', 'sua');
        $u->select('id, order_id AS idOrder, account, date_payee AS datePayee, in_payee AS inPayee,
              out_payee AS outPayee, curr AS currency, operation AS typeOperation, docum AS note');
        $u->where('sua.user_id=?', $id);
        $u->orderBy("sua.date_payee");
        $result = $u->getList();
        $account = array();
        $balance = 0;
        foreach ($result as $item) {
            $item['datePayee'] = date('Y-m-d', strtotime($item['datePayee']));
            $item['datePayeeDisplay'] = date('d.m.Y', strtotime($item['datePayee']));
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
                $u->where("NOT id IN ($idsUpdate) AND user_id=?", $id)->deleteList();
            else $u->where("user_id = ?", $id)->deleteList();

            $data = array();
            foreach ($accounts as $account)
                if (empty($account["id"]))
                    $data[] = array("user_id" => $id, "date_payee" => $account["datePayee"],
                        "in_payee" => $account['inPayee'], "out_payee" => $account["outPayee"],
                        "curr" => $account["currency"], "operation" => $account["typeOperation"], "docum" => $account["note"]);
            if ($data)
                DB::insertList('se_user_account', $data);

            foreach ($accounts as $account)
                if (!empty($account["id"])) {
                    $u = new DB('se_user_account', 'sua');
                    $account["operation"] = $account["typeOperation"];
                    $account["docum"] = $account["note"];
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

    public function save()
    {

        try {
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

}
