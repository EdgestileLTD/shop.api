<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ContactCategory extends Base
{
    protected $tableName = "se_group";


    // @@    @@ @@@@@@  @@@@@@ @@  @@ @@@@@@@@ @@    @@ @@@@@@@@@
    // @@   @@@ @@   @@ @@     @@  @@    @@    @@   @@@ @@  @  @@
    // @@  @@@@ @@   @@ @@@@@@ @@@@@@    @@    @@  @@@@ @@  @  @@
    // @@@@  @@ @@   @@ @@     @@  @@    @@    @@@@  @@ @@@ @ @@@
    // @@@   @@ @@@@@@  @@@@@@ @@  @@    @@    @@@   @@     @
    // Получить идентификаторы книг по группам идентификаторов
    static public function getIdsBooksByIdGroups($idsGroups)
    {
        $idsBooks = array();
        if (!empty($idsGroups)) {
            $u = new DB('se_group', 'sg');
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

    // @@@@@@ @@@@@@    @@    @@  @@ @@  @@ @@    @@ @@@@@@@@ @@
    // @@  @@ @@  @@   @@@@   @@  @@ @@  @@ @@   @@@    @@    @@
    // @@  @@ @@  @@  @@  @@   @@@@  @@@@@@ @@  @@@@    @@    @@@@@@
    // @@  @@ @@  @@ @@    @@   @@       @@ @@@@  @@    @@    @@  @@
    // @@  @@ @@@@@@ @@    @@   @@       @@ @@@   @@    @@    @@@@@@
    // получить
    public function fetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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


    // @@@@@@@@ @@@@@@ @@@@@@@@ @@     @@@@@@
    //    @@      @@      @@    @@     @@
    //    @@      @@      @@    @@     @@@@@@
    //    @@      @@      @@    @@     @@
    //    @@    @@@@@@    @@    @@@@@@ @@@@@@
    // Правильные заглавия перед сохранением
    public function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->input["title"] = $this->input["name"];
    }


    // @@@@@@    @@    @@    @@ @@@@@@
    // @@       @@@@   @@    @@ @@
    // @@@@@@  @@  @@   @@  @@  @@@@@@
    //     @@ @@@@@@@@   @@@@   @@
    // @@@@@@ @@    @@    @@    @@@@@@
    public function save($isTransactionMode = true)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // @@@@@@  @@@@@@ @@     @@@@@@ @@@@@@@@ @@@@@@
    // @@   @@ @@     @@     @@        @@    @@
    // @@   @@ @@@@@@ @@     @@@@@@    @@    @@@@@@
    // @@   @@ @@     @@     @@        @@    @@
    // @@@@@@  @@@@@@ @@@@@@ @@@@@@    @@    @@@@@@
    public function delete()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // @@@@@       @@    @@@@@@  @@@@@@ @@@@@@ @@@@@@   @@  @@ @@  @@ @@    @@ @@@@@@
    // @@  @@     @@@@   @@   @@ @@  @@ @@     @@       @@ @@  @@  @@ @@   @@@ @@
    // @@@@@     @@  @@  @@   @@ @@@@@@ @@@@@@ @@       @@@@   @@@@@@ @@  @@@@ @@
    // @@  @@   @@@@@@@@ @@   @@ @@     @@     @@       @@ @@  @@  @@ @@@@  @@ @@
    // @@@@@    @@    @@ @@@@@@  @@     @@@@@@ @@@@@@   @@  @@ @@  @@ @@@   @@ @@

    // Добавить Контакты в адресную книгу
    private function addContactsInAddressBook($id_group)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
                if (se_CheckMail($email['email']))
                    $emails[] = array(
                        'email' =>$email['email'],
                        'variables'=>array('name'=>$email['name'])
                    );
            }
            $emailService = new EmailProvider();
            $emailService->addEmails(array($emailSettings['idBook']), $emails);
        }
    }
}