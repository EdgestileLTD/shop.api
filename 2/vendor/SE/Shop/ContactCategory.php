<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ContactCategory extends Base
{
    protected $tableName = "se_group";

    static public function getIdsBooksByIdGroups($idsGroups)
    {
        $idsBooks = array();
        if (!empty($idsGroups)) {
            $u = new DB('se_group', 'sg');
            $u->addField('email_settings', 'varchar(255)');
            $u->select("email_settings");
            $u->where('id IN (?)', implode(",", $idsGroups));
            $list = $u->getList();
            foreach ($list as $value) {
                $data = json_decode($value["emailSettings"], true);
                if (!empty($data["idBook"]))
                    $idsBooks[] = $data["idBook"];
            }
        }
        return $idsBooks;
    }

    public function fetch($isId = false)
    {
        try {
            $u = new DB('se_group', 'sg');
            $u->select('sg.*, (SELECT COUNT(*) FROM se_user_group WHERE group_id=sg.id) user_count');
            $u->where('sg.title IS NOT NULL AND  sg.name <> "" AND sg.name IS NOT NULL');
            $this->result["items"] = $u->getList();
            $this->result["count"] = count($this->result["items"]);
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп контактов!";
        }
    }

    public function correctValuesBeforeSave()
    {
        $this->input["title"] = $this->input["name"];
    }

    public function save()
    {
        $result = parent::save();
        if ($this->input["addBook"]) {
            $emailService = new EmailProvider();
            if ($idBook = $emailService->createAddressBook($this->input["name"])) {
                $data["id"] = $this->input["id"];
                $data["emailSettings"] = json_encode(array("idBook" => $idBook));
                $u = new DB("se_group");
                $u->setValuesFields($data);
                $u->save();
                $this->info();
            }
            $this->addContactsInAddressBook($this->input["id"]);
        }
        return $result;
    }

    public function delete()
    {
        $group = null;
        if ($this->input["ids"]) {
            $idGroup = $this->input["ids"][0];
            $group = $this->info($idGroup);
        };
        if (parent::delete() && !empty($group["emailSettings"])) {
            if ($data = json_decode($group["emailSettings"], true)) {
                $emailService = new EmailProvider();
                $emailService->removeAddressBook($data["idBook"]);
            }
        }
    }

    private function addContactsInAddressBook($id_group)
    {
        $u1 = new DB('se_group', 'sg');
        $u1->select('sg.email_settings');
        $settings = $u1->find($id_group);

        $emailSettings = ($settings['emailSettings']) ? json_decode($settings['emailSettings'], true) : array();
        if (!empty($emailSettings) && $emailSettings['idBook']) {
            $u = new DB('se_user_group', 'sug');
            $u->select("p.email, concat_ws(' ', p.first_name, p.sec_name) as name");
            $u->innerjoin('person p', 'sug.user_id=p.id');
            $u->innerjoin('se_user su', 'sug.user_id=su.id');
            $u->groupBy('p.email');
            $u->where('sug.group_id=?', $id_group);
            $u->andwhere("su.is_active = 'Y'");
            $u->andwhere("p.email <> ''");

            $list = $u->getList();
            $emails = array();
            foreach($list as $email) {
               if (se_CheckMail($email['email'])) {
                    $emails[] = array(
                        'email' => $email['email'],
                        'variables' => array('name' => $email['name'])
                    );
               }
            }
            $emailService = new EmailProvider();
            $emailService->addEmails(array($emailSettings['idBook']), $emails);
        }

    }
}