<?php

    class ApiGoodsGroups extends ApiBase {

        public function fetch() {
            $params['id_lang'] = $this->projectConfig["id_lang"];
            $sqlWhere = 'sgt.id_lang = :id_lang';

            if (!empty($this->input["parent"])) {
                $tmp = array();
                foreach ($this->input["parent"] as $item) {
                    $params[':parent' . $item] = $item;
                    $level = $this->getLevel($item);
                    ++$level;
                    $tmp[] = "(sgp.`id_parent`=:parent{$item} AND sgc.`level`={$level})";
                }
                $sqlWhere .= ' AND (' . implode(' OR ', $tmp) . ')';
            } else {
                $params['level'] = 0;
                $sqlWhere .= ' AND sgc.level = :level';
            }

            //  limit
            $limit = '';
            if(isset($this->input['limit'])) {
                if(!isset($this->input['offset'])) {
                    $limit = ' LIMIT ' . (int)$this->input['limit'];
                } else {
                    $limit = ' LIMIT ' . (int)$this->input['offset'] . ',' . (int)$this->input['limit'];
                }
            }
            if(!isset($this->input['limit']) && isset($this->input['offset'])) {
                $limit = ' LIMIT ' . (int)$this->input['offset'] . ',999999999';
            }

            // search
            $search = '';
            if(isset($this->input['search']) && !empty($this->input['search'])) {
                $find    = strtolower(trim($this->input['search']));
                $search .= " (LOWER(sg.`id`) LIKE '%{$find}%' OR LOWER(sgt.`name`) LIKE '%{$find}%' OR LOWER(sgt.`description`)
                            LIKE '%{$find}%' OR LOWER(sgt.`content`) LIKE '%{$find}%')";
            }

            if($search) $sqlWhere = $search;

            $sql = "SELECT sg.id, sg.url, sg.is_visible, sg.sort, sgp.id_parent, sgt.name, sgt.description, sgt.content,
                    sgt.meta_title, sgt.meta_keywords, sgt.meta_description, (COUNT(DISTINCT(sgc.id_child)) - 1) count_childs
                    FROM shop_group sg
                    INNER JOIN shop_group_translate sgt ON sgt.id_group = sg.id
                    INNER JOIN shop_group_tree sgp ON sgp.id_child = sg.id
                    INNER JOIN shop_group_tree sgc ON sgc.id_parent = sg.id
                    WHERE {$sqlWhere}
                    GROUP BY sg.id
                    ORDER BY sg.sort ASC {$limit}";

            $items = ApiBase::$dbh->fetch($sql, $params);
            $error = ApiBase::$dbh->error;

            $sql1 = "SELECT sg.id
                    FROM shop_group sg
                    INNER JOIN shop_group_translate sgt ON sgt.id_group = sg.id
                    INNER JOIN shop_group_tree sgp ON sgp.id_child = sg.id
                    INNER JOIN shop_group_tree sgc ON sgc.id_parent = sg.id
                    WHERE {$sqlWhere}
                    GROUP BY sg.id
                    ORDER BY sg.sort ASC";

            $count = ApiBase::$dbh->fetch($sql1, $params);
            $count = count($count);

            return (empty($error))
                ? $this->generateData(["count" => "{$count}", "items" => $items, 'sql' => $sql])
                : $this->generateData('Не удаётся получить список групп товаров', true, $error);
        }

        private function getLevel($id) {
            $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent LIMIT 1';
            $items = ApiBase::$dbh->fetch($sqlLevel, array("id_parent" => $id), true);

            return (isset($items['level'])) ? $items['level'] : 0;
        }


        public function get() {
            $items = (!empty($this->input['ids'])) ? $this->input['ids'] : false;
            if (!is_array($items)) {
                return $this->generateData('Не удаётся получить информацию о группах', true, 'Отсутствует параметр ids (ids:array)');
            }

            $tmp = $params = array();
            foreach ($items as $item) {
                $params[':id' . $item] = $item;
                $tmp[] = ':id' . $item;
            }
            $sqlWhere = 'sg.`id` IN (' . implode(',', $tmp) . ')';

            $sql = "SELECT sg.id, sg.url, sg.is_visible, sg.sort, sgp.id_parent, sgt.name, sgt.description, sgt.content,
                          sgt.meta_title, sgt.meta_keywords, sgt.meta_description
                    FROM shop_group sg
                    INNER JOIN shop_group_translate sgt ON sgt.id_group = sg.id
                    LEFT JOIN shop_group_tree sgp ON sgp.id_child = sg.id
                    WHERE {$sqlWhere}";

            $items = ApiBase::$dbh->fetch($sql, $params);
            $error = ApiBase::$dbh->error;

            return (empty($error))
                ? $this->generateData(["items" => $items])
                : $this->generateData('Не удаётся получить информацию о группе товара', true, $error);
        }

        public function create() {
            $items = $this->input;
            if (!empty($items)) {
                $ok = $error = [];
                $empty = 0;
                $allowedFields = array("id_group", "id_lang", "name", "description", "content", "meta_title", "meta_keywords",
                                       "meta_description");

                $sqlGroup     = "INSERT INTO shop_group (`url`) VALUE (:url)";

                $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                                 SELECT id_parent, :id, `level` FROM shop_group_tree
                                 WHERE id_child = :id_parent
                                 UNION ALL
                                 SELECT :id, :id, :level";

                $sqlGroupLang = "INSERT INTO shop_group_translate (id_group, id_lang, `name`, description, content,
                                 meta_title, meta_keywords, meta_description)
                                 VALUES (:id_group, :id_lang, :name, :description, :content,
                                 :meta_title, :meta_keywords, :meta_description)";

                ApiBase::$dbh->setTransaction(false);
                foreach($items as $item) {
                    if(!is_array($item) || empty($item) || empty($item['name'])) {
                        ++$empty;
                        continue;
                    }
                    ApiBase::$dbh->startTransaction();
                    $params = [];

                    $level = 0;
                    if (!empty($item['id_parent'])) {
                        $level = $this->getLevel($item['id_parent']);
                        $level++;
                    }

                    if(empty($params['url']) && !empty($item['name'])) {
                        $params['url'] = $this->getUrl($item['name']);
                    }

                    $newItem = ApiBase::$dbh->execute($sqlGroup, $params);
                    if(!$newItem) {
                        $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                        ApiBase::$dbh->cancelTransaction();
                        continue;
                    }

                    $params["id_group"] = $newItem;
                    $params["id_lang"] = $this->projectConfig["id_lang"];
                    $tmp = array_merge($params, $item);
                    $params = ApiBase::$dbh->filterParams($allowedFields, $tmp);
                    ApiBase::$dbh->execute($sqlGroupLang, $params);
                    if(!empty(ApiBase::$dbh->error)) {
                        $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                        ApiBase::$dbh->cancelTransaction();
                        continue;
                    }

                    $id_parent = (isset($item['id_parent'])) ? (int)$item['id_parent'] : 0;
                    ApiBase::$dbh->execute($sqlGroupTree, ['id_parent' => $id_parent, 'id' => $newItem, 'level' => $level]);
                    if(!empty(ApiBase::$dbh->error)) {
                        $error[] = ApiBase::$dbh->error . ' Params:' . print_r($item, 1);
                        ApiBase::$dbh->cancelTransaction();
                        continue;
                    }
                    $ok[] = $newItem;
                    ApiBase::$dbh->endTransaction();
                }
                ApiBase::$dbh->setTransaction();

                if($empty || $error)
                    return $this->generateData(['empty' => $empty, 'error' => $error], true,
                                               'Empty - кол-во записей без параметров, error - ошибки');

                $count = count($ok);
                return $this->generateData(["count" => "$count", "ids" => $ok]);
            }

            return $this->generateData('Не удаётся записать группы товаров', true, 'Отсутствуют записи');
        }

        private function getUrl($name) {
            $url = strtolower($this->translit_str($name));
            $url_n = $url;
            $sql = 'SELECT url FROM shop_group WHERE url = :url';
            $i = 1;
            while ($i < 10) {
                $status = ApiBase::$dbh->fetch($sql, array('url' => $url_n), true);
                if(!empty($status))
                    $url_n = $url . "-$i";
                else return $url_n;
                $i++;
            }

            return uniqid();
        }

        public function delete() {
            $items = (isset($this->input['ids'])) ? $this->input['ids'] : [];
            if(!is_array($items) || empty($items)) {
                return $this->generateData('Не удаётся удалить бренды', true, 'Отсутствует параметр ids (ids:array)');
            }

            $tmp = $params = array();
            foreach($items as $item) {
                $params[':id'.$item] = (int)$item;
                $tmp[] = ':id'.$item;
            }

            $sql    = 'DELETE FROM shop_group WHERE id IN (SELECT sgt.id_child FROM shop_group_tree sgt
                       WHERE sgt.id_parent IN (' . implode(',', $tmp). ')) ORDER BY id DESC';
            ApiBase::$dbh->execute($sql, $params);
            $error  = ApiBase::$dbh->error;

            return (empty($error))
                ? $this->generateData(["items" => $items])
                : $this->generateData('Не удаётся удалить группы товаров', true, $error);
        }


        public function update() {
            $items = $this->input;
            if ($items) {
                $allowedLang = array("name", "description", "content", "meta_title", "meta_keywords", "meta_description");
                $allowedGroup = array("url", "is_discount", "is_visible", "sort");

                $empty = 0;
                $error_update = $ids = [];
                ApiBase::$dbh->setTransaction(false);
                foreach($items as $item) {
                    if(!isset($item['id']) || ((int)$item['id'] == 0)) {
                        ++$empty;
                        continue;
                    }
                    ApiBase::$dbh->startTransaction();

                    $tmp_group = [];
                    if(!empty($item['url'])) {
                        $tmp_group["url"] = $this->getUrl($item["url"]);
                    }

                    $param_group = ApiBase::$dbh->setParams($allowedGroup, $item);
                    if(!empty($param_group)) {
                        $sqlGr = "UPDATE shop_group SET {$param_group[1]} WHERE `id`=:id";
                        $param_group[0][':id'] = $item['id'];
                        $status = ApiBase::$dbh->execute($sqlGr, $param_group[0]);
                        if(!$status) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                            continue;
                        }
                    }

                    $param_lang = ApiBase::$dbh->setParams($allowedLang, $item);
                    if(!empty($param_lang)) {
                        $sqlLang = "UPDATE shop_group_translate SET {$param_lang[1]} WHERE id_group = :id AND id_lang = :id_lang";
                        $param_lang[0][':id'] = $item['id'];
                        $param_lang[0][':id_lang'] = $this->projectConfig["id_lang"];
                        $status = ApiBase::$dbh->execute($sqlLang, $param_lang[0]);
                        if(!$status) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                        } else {
                            $ids[] = $item['id'];
                            ApiBase::$dbh->endTransaction();
                        }
                    }

                    if(ApiBase::$dbh->inTransaction()) ApiBase::$dbh->endTransaction();
                }
                ApiBase::$dbh->setTransaction();

                if($empty || $error_update)
                    return $this->generateData(['empty' => $empty, 'error' => $error_update], true,
                                               'Empty - кол-во записей без параметров, error - ошибки');

                return $this->generateData(["ids" => $ids]);
            }

            return $this->generateData('Не удаётся изменить группы товаров', true, 'Отсутствуют записи');
        }



        /*

                        public function checkUrl() {
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

                */
    }