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
            $u->leftjoin('se_user su', 'p.id=su.id');
            $u->leftjoin('user_urid uu', 'uu.id=su.id');
            $contact = $u->getInfo($id);
            $contact['groups'] = $this->getGroups($contact['id']);
            $contact['companyRequisites'] = $this->getCompanyRequisites($contact['id']);
            $contact['personalAccount'] = $this->getPersonalAccount($contact['id']);
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
        $u->orderby("sua.date_payee");
        $result = $u->getList();
        $account = array();
        $balance = 0;
        foreach ($result as $item) {
            settype($item['inPayee'], float);
            settype($item['outPayee'], float);
            settype($item['typeOperation'], int);
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
        $u->select('ur.*, urt.size, urt.title');
        $u->leftjoin('user_rekv ur', 'ur.rekv_code=urt.code');
        $u->where('ur.id_author=?', $id);
        $u->groupby('urt.code');
        $u->orderby('urt.id');
        $result = $u->getList();
        $requisites = array();
        foreach ($result as $item) {
            $requisite['id'] = $item['id'];
            $requisite['code'] = $item['rekv_code'];
            $requisite['name'] = $item['title'];
            $requisite['value'] = $item['value'];
            $requisite['size'] = (int)$item['size'];
            $requisites[] = $requisite;
        }
        return $requisites;
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

    private function saveGroups($newIdsGroups, $idsContact)
    {
        $idsContactsS = implode(",", $idsContact);
        $idsGroupsS = implode(",", $newIdsGroups);
        $u = new seTable('se_user_group', 'sug');
        if ($newIdsGroups)
            $u->where("NOT group_id IN ($idsGroupsS) AND user_id IN ($idsContactsS)")->deleteList();
        else $u->where("user_id IN ($idsContactsS)")->deleteList();
        $u = new seTable('se_user_group', 'sug');
        $u->select("group_id");
        $u->where("user_id IN ($idsContactsS)");
        $objects = $u->getList();
        $idsExists = array();
        foreach ($objects as $object)
            $idsExists[] = $object["group_id"];
        if (!empty($newIdsGroups)) {
            foreach ($newIdsGroups as $id)
                if (!empty($id) && !in_array($id, $idsExists))
                    foreach ($idsContact as $idContact)
                        $data[] = array('user_id' => $idContact, 'group_id' => $id);
            if (!empty($data))
                se_db_InsertList('se_user_group', $data);
        }
    }

    private function saveCompanyRequisites($id, $json)
    {
        if (isset($json->companyRequisites)) {
            $u = new seTable('user_rekv', 'ur');
            $u->where('id_author=?', $id)->deletelist();

            foreach ($json->companyRequisites as $requisite)
                $data[] = array('id_author' => $id, 'rekv_code' => $requisite->code,
                    'value' => $requisite->value);
            if (!empty($data))
                se_db_InsertList('user_rekv', $data);
        }
    }

    private function savePersonalAccounts($id, $accounts)
    {
        $idsUpdate = null;
        foreach ($accounts as $account)
            if ($account->id) {
                if (!empty($idsUpdate))
                    $idsUpdate .= ',';
                $idsUpdate .= $account->id;
            }

        $u = new seTable('se_user_account', 'sua');
        if (!empty($idsUpdate))
            $u->where("NOT id IN ($idsUpdate) AND user_id=?", $id)->deletelist();
        else $u->where("user_id=?", $id)->deletelist();

        $data = array();
        foreach ($accounts as $account)
            if (empty($account->id))
                $data[] = array("user_id" => $id, "date_payee" => $account->datePayee,
                    "in_payee" => $account->inPayee, "out_payee" => $account->outPayee,
                    "curr" => $account->currency, "operation" => $account->typeOperation, "docum" => $account->note);
        if ($data)
            se_db_InsertList('se_user_account', $data);

        foreach ($accounts as $account)
            if (!empty($account->id)) {
                $u = new seTable('se_user_account', 'sua');
                $isUpdated = false;
                $isUpdated |= setField(0, $u, $account->datePayee, 'date_payee');
                $isUpdated |= setField(0, $u, $account->typeOperation, 'operation');
                $isUpdated |= setField(0, $u, $account->inPayee, 'in_payee');
                $isUpdated |= setField(0, $u, $account->outPayee, 'out_payee');
                $isUpdated |= setField(0, $u, $account->typeOperation, 'operation');
                $isUpdated |= setField(0, $u, $account->note, 'docum');
                if ($isUpdated) {
                    $u->where('id=?', $account->id);
                    $u->save();
                }
            }
    }

    private function setUserGroup($idUser)
    {
        $u = new seTable('se_group', 'sg');
        $u->select("id");
        $u->where("title = 'User'");
        $u->fetchOne();
        $idGroup = $u->id;
        if (!$idGroup) {
            $u = new seTable('se_group', 'sg');
            $u->title = "User";
            $u->level = 1;
            $idGroup = $u->save();
        }

        $u = new seTable('se_user_group', 'sug');
        $u->select("id");
        $u->where("sug.group_id = {$idGroup} AND sug.user_id = {$idUser}");
        $u->fetchOne();
        $id = $u->id;
        if (!$id) {
            $u = new seTable('se_user_group', 'sug');
            $u->group_id = $idGroup;
            $u->user_id = $idUser;
            $u->save();
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
                $ids[] = $u->save();
            }

            if ($isNew || !empty($ids)) {
                if ($isNew)
                    $this->input["reg_date"] = date("Y-m-d H:i:s");
                $this->input["id"] = $ids[0];
                $u = new DB('person', 'p');
                $u->setValuesFields($this->input);
                $id = $u->save($isNew);
                if (empty($id))
                    throw new Exception("Не удаётся сохранить контакт!");

                $u = new DB('user_urid', 'uu');
                $u->setValuesFields($this->input);
                $u->save(true);

//                $this->saveCompanyRequisites($ids[0], $this->input);
//            if ($ids && isset($json->personalAccount))
//                $this->savePersonalAccounts($ids[0], $json->personalAccount);
//            if (!empty($json->groups)) {
//                foreach ($json->groups as $group)
//                    $json->idsGroups[] = $group->id;
//            }
//            if (isset($json->isAdmin) && $json->isAdmin)
//                $json->idsGroups[] = 3;
//            if ($ids && isset($json->idsGroups))
//                $this->saveGroups($json->idsGroups, $ids);
//            else {
//                if (isset($json->isAdmin) && !$json->isAdmin) {
//                    $u = new seTable('se_user_group', 'sug');
//                    $u->where('group_id=3 AND user_id=?', $ids[0])->deletelist();
//                }
//            }
//
//            $this->setUserGroup($ids[0]);
            }
            DB::commit();
            $this->info();

            return $this;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = "Не удаётся сохранить контакт!";
        }

    }

}
