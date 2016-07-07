<?php

class ApiGoodsGroups extends ApiBase
{

    public function fetch()
    {

        $sqlWhere = 'sgt.id_lang = :id_lang';
        if (empty($this->input["id_parent"]))
            $sqlWhere .= ' AND sgc.level = :level';
        else $sqlWhere .= ' AND sgp.id_parent = :id_parent AND sgc.level = :level';

        $level = 0;
        if (!empty($this->input["id_parent"])) {
            $level = $this->getLevel($this->input["id_parent"]);
            $level++;
        }

        $sql = "SELECT sg.id, sg.url, sg.is_visible, sg.sort, sgp.id_parent, sgt.name, sgt.description, sgt.content,
                  sgt.meta_title, sgt.meta_keywords, sgt.meta_description, (COUNT(DISTINCT(sgc.id_child)) - 1) count_childs
            FROM shop_group sg
            INNER JOIN shop_group_translate sgt ON sgt.id_group = sg.id
            INNER JOIN shop_group_tree sgp ON sgp.id_child = sg.id
            INNER JOIN shop_group_tree sgc ON sgc.id_parent = sg.id
            WHERE {$sqlWhere}
            GROUP BY sg.id
            ORDER BY sg.sort";

        $sth = $this->dbh->prepare($sql);
        $params = array('id_lang' => $this->projectConfig["id_lang"], 'level' => $level);
        if (!empty($this->input["id_parent"]))
            $params["id_parent"] = $this->input["id_parent"];
        $status = $sth->execute($params);
        if ($status !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            $result["response"]["count"] = count($items);
            $result["response"]["items"] = $items;
            $result["response"]["server_time"] = time();
        } else {
            $this->isError = true;
            $result = 'Не удаётся получить список групп товаров!';
        }
        return $result;
    }

    public function get()
    {

        $sql = "SELECT sg.id, sg.url, sg.is_visible, sg.sort, sgp.id_parent, sgt.name, sgt.description, sgt.content,
                  sgt.meta_title, sgt.meta_keywords, sgt.meta_description
            FROM shop_group sg
            INNER JOIN shop_group_translate sgt ON sgt.id_group = sg.id
            LEFT JOIN shop_group_tree sgp ON sgp.id_child = sg.id
            WHERE sg.id = :id";

        $sth = $this->dbh->prepare($sql);
        $status = $sth->execute(array("id" => $this->input["id"]));
        if ($status !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            $result["response"]["items"] = $items;
            $result["response"]["server_time"] = time();
        } else {
            $this->isError = true;
            $result = 'Не удаётся получить информацию о группе товара!';
        }
        return $result;
    }

    public function delete()
    {
        $result = array();
        $request = $this->input["request"];
        $itemsDelete = $request["items"];
        if ($itemsDelete) {
            $values = array();
            foreach ($itemsDelete as $item)
                $values[] = $item["id"];
            if ($values) {
                $in = str_repeat('?,', count($values) - 1) . '?';
                $sqlGroup = "DELETE FROM shop_group WHERE id IN (SELECT sgt.id_child FROM shop_group_tree sgt
                                WHERE sgt.id_parent IN ($in))";
                $sthGroup = $this->dbh->prepare($sqlGroup);
                $sthGroup->execute($values);
                $sqlGroup = "DELETE FROM shop_group WHERE id IN ($in)";
                $sthGroup = $this->dbh->prepare($sqlGroup);
                $sthGroup->execute($values);
            }
        }
        $result["response"]["server_time"] = time();
        return $result;
    }

    public function checkUrl()
    {
        $result = array();
        $id = $this->input["id"];
        $url = $this->input["url"];
        $result["answer"] = true;

        $sql = "SELECT id FROM shop_group WHERE url = :url";
        $sth = $this->dbh->prepare($sql);
        $status = $sth->execute(array("url" => $url));
        if ($status !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            if (count($items) && $items[0]["id"] != $id)
                $result["answer"] = false;
        }

        return $result;
    }

    private function getUrl($name)
    {
        $url = strtolower(se_translite_url($name));
        $url_n = $url;
        $sql = 'SELECT url FROM shop_group WHERE url = :url';
        $sth = $this->dbh->prepare($sql);
        $i = 1;
        while ($i < 10) {
            $status = $sth->execute(array('url' => $url_n));
            if ($status !== false && $sth->fetch(PDO::FETCH_ASSOC))
                $url_n = $url . "-$i";
            else return $url_n;
            $i++;
        }
        return uniqid();
    }

    private function getLevel($id) {
        $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent LIMIT 1';
        $sth = $this->dbh->prepare($sqlLevel);
        $params = array("id_parent" => $id);
        $answer = $sth->execute($params);
        if ($answer !== false) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            if (count($items))
                $level = $items[0]['level'];
        }
        return $level;
    }

    protected function insert($items)
    {
        $itemsResult = array();
        $allowedFields = array("id_group", "id_lang", "name", "description", "content", "meta_title", "meta_keywords",
            "meta_description");
        if ($items) {
            $sqlGroup = "INSERT INTO shop_group (`url`) VALUE (:url)";
            $sthGroup = $this->dbh->prepare($sqlGroup);

            $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                        SELECT id_parent, :id, `level` FROM shop_group_tree
                        WHERE id_child = :id_parent
                        UNION ALL
                        SELECT :id, :id, :level";
            $sthGroupTree = $this->dbh->prepare($sqlGroupTree);

            $source = $items[0];
            $source["id_lang"] = $this->idLang;
            $source["id_group"] = 1;
            $sqlGroupLang = "INSERT INTO shop_group_translate SET " . $this->pdoSet($allowedFields, $values, $source);
            $sthGroupLang = $this->dbh->prepare($sqlGroupLang);

            foreach ($items as $item) {
                $level = 0;
                if (!empty($item['id_parent'])) {
                    $level = $this->getLevel($item['id_parent']);
                    $level++;
                }
                $newItem = array();
                $params = array();
                $params["url"] = $this->getUrl($item["name"]);
                $sthGroup->execute($params);
                $params = array_merge($params, $item);
                $params["id_group"] = $this->dbh->lastInsertId();
                $params["id_lang"] = $this->projectConfig["id_lang"];
                foreach ($values as $key => &$val)
                    $val = $params[$key];
                $sthGroupLang->execute($values);
                $sthGroupTree->execute(array('id_parent' => $item['id_parent'], 'id' => $params["id_group"], 'level' => $level));
                $newItem["id"] = $params["id_group"];
                $itemsResult["add"][] = $newItem;
            }
        }
        return $itemsResult;
    }

    protected function update($items)
    {
        $itemsResult = array();
        if ($items) {
            $allowedLang = array("name", "description", "content", "meta_title", "meta_keywords", "meta_description");
            $allowedGroup = array("url", "is_discount", "is_visible", "sort");
            $source = $items[0];
            $source["id_lang"] = $this->projectConfig["id_lang"];
            $source["id_group"] = 1;

            $fieldsGroup = $this->pdoSet($allowedGroup, $valuesGroup, $source);
            $sqlGroup = "UPDATE shop_group SET {$fieldsGroup} WHERE id = :id";
            $sthGroup = $this->dbh->prepare($sqlGroup);

            $fieldsGroupLang = $this->pdoSet($allowedLang, $valuesLang, $source);
            $sqlGroupLang = "UPDATE shop_group_translate SET {$fieldsGroupLang} WHERE id_group = :id AND id_lang = :id_lang";
            $sthGroupLang = $this->dbh->prepare($sqlGroupLang);

            foreach ($items as $item) {
                $updateItem = array();
                if (empty($item['url'])) {
                    $item['url'] = $item['name'];
                    $item["url"] = $this->getUrl($item["url"]);
                }
                if ($fieldsGroup) {
                    foreach ($valuesGroup as $key => &$val)
                        $val = $item[$key];
                    $valuesGroup["id"] = $item["id"];
                    $sthGroup->execute($valuesGroup);
                }
                if ($fieldsGroupLang) {
                    foreach ($valuesLang as $key => &$val)
                        $val = $item[$key];
                    $valuesLang["id"] = $item["id"];
                    $valuesLang["id_lang"] = $this->projectConfig["id_lang"];
                    $sthGroupLang->execute($valuesLang);
                }
                $updateItem["id"] = $item["id"];
                $itemsResult["update"][] = $updateItem;
            }
        }
        return $itemsResult;
    }
}