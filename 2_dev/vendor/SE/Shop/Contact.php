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

    // @@@@@@ @@@@@@    @@    @@  @@ @@  @@   @@  @@    @@    @@@@@@ @@@@@@@@ @@@@@@ @@@@@@ @@    @@
    // @@  @@ @@  @@   @@@@   @@  @@ @@  @@   @@  @@   @@@@   @@        @@    @@  @@ @@  @@ @@   @@@
    // @@  @@ @@  @@  @@  @@   @@@@  @@@@@@   @@@@@@  @@  @@  @@        @@    @@@@@@ @@  @@ @@  @@@@
    // @@  @@ @@  @@ @@    @@   @@       @@   @@  @@ @@@@@@@@ @@        @@    @@     @@  @@ @@@@  @@
    // @@  @@ @@@@@@ @@    @@   @@       @@   @@  @@ @@    @@ @@@@@@    @@    @@     @@@@@@ @@@   @@
    // получить настройки
    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** Премножение на валюты с использованием временной таблицы валют
         * 1 запросБД базовой валюты
         * 2 запросБД на состав валют на получение
         * 3 получение валют у ЦБ
         * 4 запросБД на наличие таблицы : на создание таблицы при ее отсутствие
         * 5 чистим таблицу валют перед записью
         * 6 запросБД на заполение
         * 7 запросБД на расчет с перемножением на текущие курсы
         */

        $u = new DB('main', 'm'); // 1
        $u->select('mt.name, mt.title, mt.name_front');
        $u->innerJoin('money_title mt', 'm.basecurr = mt.name');
        $this->currData = $u->fetchOne();
        unset($u);

        $u = new DB('shop_order', 'so'); // 2
        $u->select("so.curr");
        $u->groupBy('so.curr');
        $data = $u->getList();
        unset($u);

        foreach ($data as $k => $item) { // 3
            $course = DB::getCourse($this->currData["name"], $item["curr"]);
            $result[] = array(
                "curr"   => $item["curr"],
                "course" => $course,
            );
        };

        DB::query("
            CREATE TABLE IF NOT EXISTS `courses` (
                `curr` varchar(255),
                `course` float,
                PRIMARY KEY (curr)
            )
        "); // 4
        DB::query("TRUNCATE TABLE courses"); // 5
        DB::insertList('courses', $result); // 6

        return array( // 7
            "select" => 'p.*,
                         CONCAT_WS(
                            " ",
                            p.last_name,
                            p.first_name,
                            p.sec_name
                        ) display_name, 
                        c.name company,
                        sug.group_id id_group,
                        COUNT(so.id) count_orders,
                        SUM(
                            so.amount
                            * ( SELECT c.course
                                FROM `courses` `c`
                                WHERE c.curr = so.curr
                                LIMIT 1
                            )
                        ) amount_orders,
                        SUM(
                            sop.amount
                            * ( SELECT c.course
                                FROM `courses` `c`
                                WHERE c.curr = sop.curr
                                LIMIT 1
                            )
                        ) paid_orders,
                        su.username username,
                        su.password password,
                        (su.is_active = "Y") is_active',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'se_user su',
                    "condition" => 'p.id = su.id'
                ),
                array(
                    "type" => "left",
                    "table" =>
                        '(
                            SELECT 
                                so.id,
                                so.id_author,
                                so.curr,
                                (
                                    SUM((sto.price - IFNULL(sto.discount, 0))* sto.count)
                                    - IFNULL(so.discount, 0)
                                    + IFNULL(so.delivery_payee, 0)
                                ) amount 
                            FROM shop_order so 
                            INNER JOIN shop_tovarorder sto ON sto.id_order = so.id AND is_delete="N"
                            GROUP BY so.id
                        ) so',
                    "condition" => 'so.id_author = p.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_order_payee sop',
                    "condition" => 'sop.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => "se_user_group sug",
                    "condition" => "p.id = sug.user_id"
                ),
                array(
                    "type" => "left",
                    "table" => 'company_person cp',
                    "condition" => 'cp.id_person = p.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'company c',
                    "condition" => 'c.id = cp.id_company'
                )
            ),
            "patterns" => array("displayName" => "p.last_name"),
            "convertingValues" => array(
                "amountOrders",
                "paidOrders",
            )
        );
    }

    protected function correctItemsBeforeFetch($items = [])
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($items as &$item) {
            $item['phone'] = $this->correctPhone($item['phone']);
            $item['regDate'] = date("d.m.Y", strtotime($item['regDate']));
        }

        return $items;
    }

    // @@@@@@   @@@@@@ @@@@@@    @@    @@     @@@@@@   @@@@@@ @@@@@@    @@    @@@@@@
    // @@  @@   @@  @@ @@  @@   @@@@   @@         @@   @@  @@ @@  @@   @@@@   @@  @@
    // @@  @@   @@  @@ @@  @@  @@  @@  @@@@@@ @@@@@    @@  @@ @@  @@  @@  @@  @@@@@@
    // @@  @@   @@  @@ @@  @@ @@    @@ @@  @@     @@   @@  @@ @@  @@ @@    @@  @@ @@
    // @@  @@   @@  @@ @@@@@@ @@    @@ @@@@@@ @@@@@@   @@  @@ @@@@@@ @@    @@ @@  @@
    // получить пользовательские поля
    private function getCustomFields($idContact)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_userfields', 'su');
        $u->select("cu.id, cu.id_person, cu.value, su.id id_userfield, 
                    su.name, su.required, su.enabled, su.type, su.placeholder, su.description, su.values, sug.id id_group, sug.name name_group");
        $u->leftJoin('person_userfields cu', "cu.id_userfield = su.id AND id_person = {$idContact}");
        $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
        $u->where('su.data = "contact"');
        $u->groupBy('su.id');
        $u->orderBy('sug.sort');
        $u->addOrderBy('su.sort');
        $result = $u->getList();

        $groups = array();
        foreach ($result as $item) {
            $key = (int)$item["idGroup"];
            $group = key_exists($key, $groups) ? $groups[$key] : array();
            $group["id"] = $item["idGroup"];
            $group["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
            if ($item['type'] == "date")
                $item['value'] = date('Y-m-d', strtotime($item['value']));
            if (!key_exists($key, $groups))
                $groups[$key] = $group;
            $groups[$key]["items"][] = $item;
        }
        return array_values($groups);
    }


    // @@    @@ @@  @@ @@@@@@@@@ @@@@@@
    // @@   @@@ @@  @@ @@  @  @@ @@  @@
    // @@  @@@@ @@@@@@ @@  @  @@ @@  @@
    // @@@@  @@ @@  @@ @@@ @ @@@ @@  @@
    // @@@   @@ @@  @@     @     @@@@@@
    // информация
    public function info($id = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $id = empty($id) ? $this->input["id"] : $id;
        try {
            $u = new DB('person', 'p');
            $u->select('p.*,
                        CONCAT_WS(
                            " ",
                            p.last_name,
                            p.first_name,
                            p.sec_name
                        ) display_name,
                        p.avatar imageFile,
                        su.username login,
                        su.password,
                        (su.is_active = "Y") isActive,
                        uu.company,
                        uu.director,
                        uu.tel,
                        uu.fax,
                        uu.uradres,
                        uu.fizadres,
                        CONCAT_WS(
                            " ",
                            pr.last_name,
                            pr.first_name,
                            pr.sec_name
                        ) refer_name,
                        CONCAT_WS(
                            " ",
                            pm.last_name,
                            pm.first_name,
                            pm.sec_name
                        ) manager_name
                        ');
            $u->leftJoin('se_user su', 'p.id=su.id');
            $u->leftJoin('user_urid uu', 'uu.id=su.id');
            $u->leftJoin('person pr', 'pr.id=p.id_up');
            $u->leftJoin('person pm', 'pm.id=p.manager_id');
            $contact = $u->getInfo($id);
            $contact["birthDate"] = date("d.m.Y", strtotime($contact["birthDate"]));
            $contact["regDate"] = date("d.m.Y", strtotime($contact["regDate"]));
            $contact['groups'] = $this->getGroups($contact['id']);
            $contact['companyRequisites'] = $this->getCompanyRequisites($contact['id']);
            $contact['personalAccount'] = $this->getPersonalAccount($contact['id']);
            $accountTypeOperations = new BankAccountTypeOperation();
            $contact['accountOperations'] = $accountTypeOperations->fetch();
            $contact["customFields"] = $this->getCustomFields($contact["id"]);
            if ($count = count($contact['personalAccount']))
                $contact['balance'] = $contact['personalAccount'][$count - 1]['balance'];
            $this->result = $contact;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о контакте!";
        }

        return $this->result;
    }

    // @@  @@ @@@@@@     @@       @@    @@@@@@ @@  @@ @@    @@ @@@@@@
    // @@  @@ @@   @@   @@@@     @@@@   @@     @@  @@ @@   @@@ @@
    //  @@@@  @@   @@  @@  @@   @@  @@  @@@@@@ @@@@@@ @@  @@@@ @@@@@@
    //   @@   @@   @@ @@@@@@@@ @@    @@ @@     @@  @@ @@@@  @@ @@
    //   @@   @@@@@@  @@    @@ @@    @@ @@@@@@ @@  @@ @@@   @@ @@@@@@
    // удаление
    public function delete()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, 'удаление');
        $emails = array();
        $u = new DB('person');
        $u->select('email');
        if(!empty($this->input["ids"]))
            $u->where('id IN (?)', implode(",", $this->input["ids"]));
        $u->andWhere('email IS NOT NULL');
        $u->andWhere('email <> ""');
        $list = $u->getList();
        foreach ($list as $value)
            $emails[] = $value["email"];
        if (parent::delete()) {
            if (!empty($emails))
                foreach($emails as $email) {
                    $emailProvider = new EmailProvider();
                    $emailProvider->removeEmailFromAllBooks($email);
                }
            $u = new DB('se_user');
            $u->where('id IN (?)', implode(",", $this->input["ids"]));
            $u->deleteList();
            return true;
        }
        return false;
    }

    // @@  @@ @@  @@ @@@@@@ @@@@@@@@   @@@@@@    @@    @@@@@@ @@    @@ @@@@@@ @@
    // @@  @@ @@  @@ @@        @@          @@   @@@@   @@  @@ @@   @@@ @@     @@
    //  @@@@  @@@@@@ @@@@@@    @@      @@@@@   @@  @@  @@  @@ @@  @@@@ @@     @@@@@@
    //   @@       @@ @@        @@          @@ @@@@@@@@ @@  @@ @@@@  @@ @@     @@  @@
    //   @@       @@ @@@@@@    @@      @@@@@@ @@    @@ @@  @@ @@@   @@ @@@@@@ @@@@@@
    private function getPersonalAccount($id)
    {
        /** Получить личную учетную запись
         * 1 получаем поступления/расходы по аккаунту
         * 2 получаем базовую валюту
         * 3 запрашиваем курс и конвертируем столбцы по списку (с прибавлением данных по базовой валюте)
         *
         * @param int $id idАккаунта
         * @reuturn array $account массивы с данными транзакицй по клиенту
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('se_user_account'); // 1
        $u->where('user_id = ?', $id);
        $u->orderBy("date_payee");
        $result = $u->getList();

        $u = new DB('main', 'm'); // 2
        $u->select('mt.name, mt.title, mt.name_front');
        $u->innerJoin('money_title mt', 'm.basecurr = mt.name');
        $this->currData = $u->fetchOne();
        unset($u);

        $account = array();
        $balance = 0;
        foreach ($result as $item) {
            $balance += ($item['inPayee'] - $item['outPayee']);
            $item['balance'] = $balance;

            $course = DB::getCourse($this->currData["name"], $item["curr"]); // 3
            $convertingValues = array('inPayee','outPayee','balance');
            foreach ($convertingValues as $key => $i) {
                $item[$i] = $item[$i] * $course;
            }
            unset($item["curr"]);
            $item["nameFlang"] = $this->currData["name"];
            $item["titleCurr"] = $this->currData["title"];
            $item["nameFront"] = $this->currData["nameFront"];
            $account[] = $item;
        }
        return $account;
    }

    // @@@@@@ @@@@@@ @@  @@ @@@@@    @@  @@ @@@@@@ @@     @@ @@@@@@
    // @@  @@ @@     @@ @@  @@  @@   @@ @@  @@  @@ @@@   @@@ @@  @@
    // @@@@@@ @@@@@@ @@@@   @@@@@    @@@@   @@  @@ @@ @@@ @@ @@  @@
    // @@     @@     @@ @@  @@  @@   @@ @@  @@  @@ @@  @  @@ @@  @@
    // @@     @@@@@@ @@  @@ @@@@@    @@  @@ @@@@@@ @@     @@ @@  @@
    // получить реквизиты компании
    private function getCompanyRequisites($id)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('user_rekv_type', 'urt');
        $u->select('ur.id, ur.value, urt.code rekv_code, urt.size, urt.title');
        $u->leftJoin('user_rekv ur', "ur.rekv_code = urt.code AND ur.id_author = {$id}");
        $u->groupBy('urt.code');
        $u->orderBy('urt.id');
        return $u->getList();
    }

    // @@@@@@  @@@@@@ @@  @@ @@@@@@ @@@@@@
    // @@      @@  @@ @@  @@ @@  @@ @@  @@
    // @@      @@@@@@  @@@@  @@  @@ @@  @@
    // @@      @@       @@   @@  @@ @@  @@
    // @@      @@       @@   @@  @@ @@  @@
    // получить группы
    private function getGroups($id)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('se_group', 'sg');
        $u->select('sg.id, sg.title name');
        $u->innerjoin('se_user_group sug', 'sg.id = sug.group_id');
        $u->where('sg.title IS NOT NULL AND sg.name <> "" AND sg.name IS NOT NULL AND sug.user_id = ?', $id);
        return $u->getList();
    }


    // @@@@@@@@ @@@@@@    @@    @@  @@ @@@@@@    @@    @@    @@ @@@@@@@@
    //    @@    @@  @@   @@@@   @@  @@ @@       @@@@   @@   @@@    @@
    //    @@    @@@@@@  @@  @@  @@@@@@ @@      @@  @@  @@  @@@@    @@
    //    @@    @@     @@@@@@@@ @@  @@ @@     @@    @@ @@@@  @@    @@
    //    @@    @@     @@    @@ @@  @@ @@@@@@ @@    @@ @@@   @@    @@
    // транслит (перевод знаков в латинский алфавит)
    private function getUserName($lastName, $userName, $id = 0)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        // преобразование $userName в транслит
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

    // @@@@@@ @@@@@@ @@  @@ @@@@@@@@    @@
    // @@  @@ @@  @@ @@  @@    @@      @@@@
    // @@  @@ @@  @@ @@@@@@    @@     @@  @@
    // @@  @@ @@  @@     @@    @@    @@@@@@@@
    // @@  @@ @@@@@@     @@    @@    @@    @@
    // Добавить адрес электронной почты в адресную книгу
    private function addInAddressBookEmail($idsContacts, $idsNewsGroups, $idsDelGroups)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $emails = array();
        $u = new DB('person');
        $u->select("email, concat_ws(' ', first_name, sec_name) as name");
        $u->where('id IN (?)', implode(",", $idsContacts));
        $u->andWhere('email IS NOT NULL');
        $u->andWhere('email <> ""');
        $list = $u->getList();
        foreach ($list as $value) {
            if (se_CheckMail($value['email']))
                $emails[] = array(
                    'email' =>$value['email'],
                    'variables'=>array('name'=>$value['name'])
                );
        }
        if (empty($emails))
            return;

        if ($idsNewsGroups && ($idsBooks = ContactCategory::getIdsBooksByIdGroups($idsNewsGroups))) {
            $emailProvider = new EmailProvider();
            $emailProvider->addEmails($idsBooks, $emails);
        }
        if ($idsDelGroups && ($idsBooks = ContactCategory::getIdsBooksByIdGroups($idsDelGroups))) {
            $emailProvider = new EmailProvider();
            $emailProvider->removeEmails($idsBooks, $emails);
        }
    }

    // @@@@@@ @@@@@@ @@  @@ @@@@@@   @@@@@@  @@@@@@ @@  @@ @@@@@@
    // @@     @@  @@  @@@@  @@  @@   @@      @@  @@ @@  @@ @@  @@
    // @@     @@  @@   @@   @@@@@@   @@      @@@@@@  @@@@  @@  @@
    // @@     @@  @@  @@@@  @@       @@      @@       @@   @@  @@
    // @@@@@@ @@@@@@ @@  @@ @@       @@      @@       @@   @@  @@
    // сохранеие группы
    private function saveGroups($groups, $idsContact, $addGroup = false)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $newIdsGroups = array();
            foreach ($groups as $group)
                $newIdsGroups[] = $group["id"];
            $idsGroupsS = implode(",", $newIdsGroups);
            $idsContactsS = implode(",", $idsContact);

            if (!$addGroup) {
                $u = new DB('se_user_group', 'sug');
                $u->select("id, group_id, user_id");
                if ($newIdsGroups)
                    $u->where("NOT group_id IN ($idsGroupsS) AND user_id IN ($idsContactsS)");
                else
                    $u->where("user_id IN ($idsContactsS)");
                $groupsDel = $u->getList();

                $idsGroupsDelEmail = array();
                foreach ($groupsDel as $group)
                    $idsGroupsDelEmail[$group["userId"]][] = $group["groupId"];
                $u->deleteList();

                //writeLog($idsGroupsDelEmail);
                foreach($idsGroupsDelEmail as $userId =>$gr) {
                    if (!empty($gr) && $userId) {
                        $this->addInAddressBookEmail(array($userId), false, $gr);
                    }
                }
            }

            $u = new DB('se_user_group', 'sug');
            $u->select("group_id, user_id");
            $u->where("user_id IN ($idsContactsS)");
            $objects = $u->getList();

            $idsExists = array();
            foreach ($objects as $object)
                $idsExists[$object["userId"]][] = $object["groupId"];

            if (!empty($newIdsGroups)) {
                $data = array();
                foreach ($newIdsGroups as $id) {
                    $idsContactsNewEmail = array();
                    if (!empty($id)) {
                        foreach ($idsContact as $idContact) {
                            if (!in_array($id, $idsExists[$idContact])) {
                                $data[] = array('user_id' => $idContact, 'group_id' => $id);
                                $idsContactsNewEmail[] = $idContact;
                            }
                        }
                    }
                    if (!empty($idsContactsNewEmail))
                        $this->addInAddressBookEmail($idsContactsNewEmail, array($id), array());
                }
                if (!empty($data)) {
                    DB::insertList('se_user_group', $data);
                }

            }

        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы контакта!";
            throw new Exception($this->error);
        }
    }


    // @@@@@@ @@@@@@ @@  @@ @@@@@@   @@@@@@ @@@@@@ @@  @@ @@@@@  @@    @@ @@@@@@
    // @@     @@  @@  @@@@  @@  @@   @@  @@ @@     @@ @@  @@  @@ @@   @@@     @@
    // @@     @@  @@   @@   @@@@@@   @@@@@@ @@@@@@ @@@@   @@@@@  @@  @@@@ @@@@@
    // @@     @@  @@  @@@@  @@       @@     @@     @@ @@  @@  @@ @@@@  @@     @@
    // @@@@@@ @@@@@@ @@  @@ @@       @@     @@@@@@ @@  @@ @@@@@  @@@   @@ @@@@@@
    // Сохранить реквизиты компании
    private function saveCompanyRequisites($id, $input)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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


    // @@@@@@ @@@@@@ @@  @@ @@@@@@      @@    @@  @@ @@  @@
    // @@     @@  @@  @@@@  @@  @@     @@@@   @@ @@  @@ @@
    // @@     @@  @@   @@   @@@@@@    @@  @@  @@@@   @@@@
    // @@     @@  @@  @@@@  @@       @@@@@@@@ @@ @@  @@ @@
    // @@@@@@ @@@@@@ @@  @@ @@       @@    @@ @@  @@ @@  @@
    // Сохранить личные аккаунты
    private function savePersonalAccounts($id, $accounts)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // @@@@@@    @@    @@@@@@     @@    @@@@@@@@ @@         @@@@@@  @@@@@@ @@  @@ @@@@@@
    //     @@   @@@@   @@   @@   @@@@      @@    @@         @@      @@  @@ @@  @@ @@  @@
    // @@@@@   @@  @@  @@   @@  @@  @@     @@    @@@@@@     @@      @@@@@@  @@@@  @@  @@
    //     @@ @@@@@@@@ @@   @@ @@@@@@@@    @@    @@  @@     @@      @@       @@   @@  @@
    // @@@@@@ @@    @@ @@@@@@  @@    @@    @@    @@@@@@     @@      @@       @@   @@  @@
    // задать группу пользователя
    private function setUserGroup($idUser)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    // @@@@@@ @@@@@@ @@  @@ @@@@@@   @@@@@@ @@@@@@    @@    @@     @@@@@@
    // @@     @@  @@  @@@@  @@  @@   @@  @@ @@  @@   @@@@   @@         @@
    // @@     @@  @@   @@   @@@@@@   @@  @@ @@  @@  @@  @@  @@@@@@ @@@@@
    // @@     @@  @@  @@@@  @@       @@  @@ @@  @@ @@    @@ @@  @@     @@
    // @@@@@@ @@@@@@ @@  @@ @@       @@  @@ @@@@@@ @@    @@ @@@@@@ @@@@@@
    // сохранить пользовательские поля
    private function saveCustomFields()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        // если присутствуют нстраиваемые поля - передать правду
        if (!isset($this->input["customFields"]))
            return true;

        // сохраняем информацию о контакте
        try {
            $idContact = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idPerson"] = $idContact;
                $u = new DB('person_userfields', 'cu');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            // ошибка сохранения
            $this->error = "Не удаётся сохранить доп. информацию о контакте!";
            throw new Exception($this->error);
        }
    }


    // @@@@@@ @@@@@@ @@  @@ @@@@@@
    // @@     @@  @@  @@@@  @@  @@
    // @@     @@  @@   @@   @@@@@@
    // @@     @@  @@  @@@@  @@
    // @@@@@@ @@@@@@ @@  @@ @@
    // сохранить
    public function save($contact = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            // добавляем группу (привязка по ids)
            if ($contact)
                $this->input = $contact;
            if ($this->input["add"] && !empty($this->input["ids"]) && !empty($this->input["groups"])) {
                $this->saveGroups($this->input["groups"], $this->input["ids"], true);
                $this->info();
                return $this;
            }
            if ($this->input["upd"] && !empty($this->input["ids"]) && !empty($this->input["groups"])) {
                $this->saveGroups($this->input["groups"], $this->input["ids"]);
                $this->info();
                return $this;
            }


            // начать транзакцию БД
            DB::beginTransaction();

            $ids = array();
            // если ids пустой и id определен: добавляем в ids
            if (empty($this->input["ids"]) && !empty($this->input["id"]))
                $ids[] = $this->input["id"];
            else $ids = $this->input["ids"];
            // инициализация поля ввода нового пользователя
            $isNew = empty($ids);
            // если присутствует логин, то выдаем логин, иначе ноль
            $userName = isset($this->input["login"]) ? $this->input["login"] : null;
            if (!empty($this->input["birthDate"]))
                $this->input["birthDate"] = date("Y-m-d", strtotime($this->input["birthDate"]));
            if (!empty($this->input["regDate"]))
                $this->input["regDate"] = date("Y-m-d", strtotime($this->input["regDate"]));
            // создаем новый контакт
            if ($isNew) {
                // разбиваем ФИО на фамилию, имя, отчество
                $lastfirstsec = explode(" ", $this->input["firstName"]);
                if (count($lastfirstsec) == 1) {
                    $this->input["firstName"] = $lastfirstsec[0];
                } elseif (count($lastfirstsec) == 2) {
                    $this->input["lastName"] = $lastfirstsec[0];
                    $this->input["firstName"] = $lastfirstsec[1];
                } elseif (count($lastfirstsec) == 3) {
                    $this->input["lastName"] = $lastfirstsec[0];
                    $this->input["firstName"] = $lastfirstsec[1];
                    $this->input["secName"] = $lastfirstsec[2];
                } elseif (count($lastfirstsec) > 3) {
                    $this->input["lastName"] = $lastfirstsec[0];
                    $this->input["firstName"] = $lastfirstsec[1];
                    $secN = array_slice($lastfirstsec, 2);
                    $secN = implode(" ", $secN);
                    $this->input["secName"] = $secN;
                } else {
                    throw new Exception("Не удаётся сохранить контакт!");
                }

                // если ИМЯ не пустое, то удаляя пробелы в начале и конце строки выдаем ИМЯ, в ином случае передаем фамилию
                $login = !empty($this->input["lastName"]) ? trim($this->input["lastName"]) : $this->input["firstName"]; // входной параметр
                $this->input["username"] = $this->getUserName($login, $userName);
                if (!empty($this->input["username"])) {
                    // таблиблица в БД, куда передается username
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

            // если поле нового контакта не пустое...
            if ($isNew || !empty($ids)) {
                if ($isNew)
                    $this->input["regDate"] = date("Y-m-d H:i:s");
                $this->input["id"] = $ids[0];
                if (isset($this->input["imageFile"]))
                    $this->input["avatar"] = $this->input["imageFile"];
                $u = new DB('person', 'p'); // вписываем БД
                $u->setValuesFields($this->input);
                $id = $u->save($isNew);
                if (empty($id))
                    throw new Exception("Не удаётся сохранить контакт!");

                // обработать имя $this->input // explode(...)

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
                if ($ids && isset($this->input["customFields"]))
                    $this->saveCustomFields();
            }
            DB::commit();
            $this->info();

            return $this;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить контакт!" : $this->error;
        }

    }

    // @@@@@  @@  @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    //     @@ @@ @@  @@     @@  @@ @@  @@ @@  @@    @@
    // @@@@@@ @@@@   @@     @@  @@ @@  @@ @@@@@@    @@
    //     @@ @@ @@  @@     @@  @@ @@  @@ @@        @@
    // @@@@@  @@  @@ @@@@@@ @@  @@ @@@@@@ @@        @@
    // экспорт
    public function export()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        // проверяем на наличие записей в id
        if (!empty($this->input["id"])) {
            $this->exportItem();
            return;
        }

        // инициализируем файл экспорта
        $fileName = "export_persons.csv";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $fp = fopen($filePath, 'w');
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        // поднимаем записи из БД
        $rusCols = array(
            "regDateTime"   => "Время регистрации",
            "username"      => "Код",
            "lastName"      => "Фамилия",
            "firstName"     => "Имя",
            "secName"       => "Отчество",
            "gender"        => "Пол",
            "birthDate"     => "Дата рождения",
            "email"         => "Email",
            "phone"         => "Телефон",
            "note"          => "Заметка"
        );

        $header = array();
        $u = new DB('person', 'p');
        // отбираем
        //$u->select('p.reg_date regDateTime, su.username, p.last_name, p.first_name Name, p.sec_name patronymic,
        //p.sex gender, p.birth_date, p.email, p.phone, p.note');
//        $u->select('p.reg_date regDateTime, su.username, p.last_name, p.first_name, p.sec_name,
//            p.sex gender, p.birth_date, p.email, p.phone, p.note');

        $u->select('p.reg_date regDateTime, su.username, p.last_name, p.first_name, p.sec_name, 
            p.sex gender, p.birth_date, p.email, p.phone, p.note');

        // сопоставляем, выводим результат
        $u->innerJoin('se_user su', 'p.id = su.id');
        // вернуть записи из левой таблицы
        $u->leftJoin('se_user_group sug', 'p.id = sug.user_id');
        // группируем
        $u->groupBy('p.id');
        // сортируем
        $u->orderBy('p.id');
        // формируем список
        $contacts = $u->getList();
        // для каждого контакта (в случае наличия заголовка)... экспортируем
        foreach ($contacts as $contact) {
            if (!$header) {
                $header = array_keys($contact);
                $headerCSV = array();
                foreach ($header as $col) {
                    // $headerCSV[] = iconv('utf-8', 'CP1251', $col);
                    $headerCSV[] = iconv('utf-8', 'CP1251', $rusCols[$col] ? $rusCols[$col] : $col);
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

    // @@@@@  @@  @@ @@@@@@ @@@@@@     @@  @@ @@@@@@ @@  @@ @@@@@@@@    @@    @@  @@ @@@@@@@@    @@
    //     @@ @@ @@  @@     @@  @@     @@ @@  @@  @@ @@  @@    @@      @@@@   @@ @@     @@      @@@@
    // @@@@@@ @@@@   @@     @@  @@     @@@@   @@  @@ @@@@@@    @@     @@  @@  @@@@      @@     @@  @@
    //     @@ @@ @@  @@     @@  @@     @@ @@  @@  @@ @@  @@    @@    @@@@@@@@ @@ @@     @@    @@@@@@@@
    // @@@@@  @@  @@ @@@@@@ @@  @@     @@  @@ @@@@@@ @@  @@    @@    @@    @@ @@  @@    @@    @@    @@
    // экспорт контактА
    private function exportItem()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        // проверка параметров / библиотек
        $idContact = $this->input["id"];
        if (!$idContact) {
            $this->result = "Отсутствует параметр: id контакта!";
            return;
        }
        if (!class_exists("PHPExcel")) {
            $this->result = "Отсутствуют необходимые библиотеки для экспорта!";
            return;
        }

        // инициализация контакта
        $contact = new Contact();
        $contact = $contact->info($idContact);

        // задаем параметры файла
        $fileName = "export_person_{$idContact}.xlsx";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        // инициализация файла
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
        $sheet->setCellValue("B2", $contact["displayName"]);
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

    // @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    // @@  @@ @@  @@ @@        @@
    // @@  @@ @@  @@ @@        @@
    // @@  @@ @@  @@ @@        @@
    // @@  @@ @@@@@@ @@@@@@    @@
    // пост
    public function post()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if ($items = parent::post())
            $this->import($items[0]["name"]);
    }

    // @@    @@ @@     @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    // @@   @@@ @@@   @@@ @@  @@ @@  @@ @@  @@    @@
    // @@  @@@@ @@ @@@ @@ @@  @@ @@  @@ @@@@@@    @@
    // @@@@  @@ @@  @  @@ @@  @@ @@  @@ @@        @@
    // @@@   @@ @@     @@ @@  @@ @@@@@@ @@        @@
    // импорт (обновление!!)
    public function import($fileName)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        // адрес эксель файла
        $dir = DOCUMENT_ROOT . "/files";
        $filePath = $dir . "/{$fileName}";
        // получаем массив из файла
        $contacts = $this->getArrayFromCsv($filePath);

        // сохранить каждый контакт
        foreach ($contacts as $contact) {

            // Словарь заголовков
            $enCols = array(
                "Время регистрации"     => "regDateTime",
                "Код"                   => "username",
                "Фамилия"               => "lastName",
                "Имя"                   => "firstName",
                "Отчество"              => "secName",
                "Пол"                   => "gender",
                "Дата рождения"         => "birthDate",
                "Email"                 => "email",
                "Телефон"               => "phone",
                "Заметка"               => "note"
            );

            // замена ру-заголовков на стандартные англ-заголовки
            $header = array_keys($contact);
            foreach ($header as $head)
                $headerDB[$enCols[$head] ? $enCols[$head] : $head] = $contact[$head];

            // фильтрация нулевых значений даты рождения
            if($headerDB['birthDate'] === '0000-00-00')
                unset($headerDB['birthDate']);

            // фильтрация пустых значений
            $header = array_keys($headerDB);
            foreach ($header as $head)
                if($headerDB[$head] == '')
                    unset($headerDB[$head]);

            // сохранение в БД
            $this->save($headerDB);
        }
    }

    static public function correctPhone($phone)
    {
        $phoneIn = $phone;
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) < 10)
            return $phoneIn;

        if (strlen($phone) == 10)
            $phone = '7' . $phone;
        if ((strlen($phone) == 11) && ($phone[0] == '8'))
            $phone[0] = 7;
        $result = null;
        for ($i = 0; $i < strlen($phone); $i++) {
            $result .= $phone[$i];
            if ($i == 0)
                $result .= ' (';
            if ($i == 3)
                $result .= ') ';
            if ($i == 6 || $i == 8)
                $result .= '-';
        }
        return '+' . $result;
    }

}

