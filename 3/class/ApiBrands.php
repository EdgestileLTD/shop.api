<?php

    class ApiBrands extends ApiBase {
        /*
         * список брендов
         */
        public function fetch() {
            $limit = '';
            $sqlWhere = 'sbt.id_lang = :id_lang';
            //  limit
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
                $search .= " AND (LOWER(sb.`id`) LIKE '%{$find}%' OR LOWER(`name`) LIKE '%{$find}%' OR LOWER(`description`) LIKE '%{$find}%' OR LOWER(`content`) LIKE '%{$find}%')";
            }

            $sql = "SELECT sb.id, sb.url, sb.image, sbt.name, sbt.description, sbt.content, sbt.meta_title, sbt.meta_description, sbt.meta_keywords FROM shop_brand sb
            INNER JOIN shop_brand_translate sbt ON sb.id = sbt.id_brand
            WHERE {$sqlWhere} {$search}
            ORDER BY sb.id ASC {$limit}";

            $params['id_lang'] = $this->projectConfig["id_lang"];
            $items = ApiBase::$dbh->fetch($sql, $params);
            $error = ApiBase::$dbh->error;

            $sql = "SELECT COUNT(sb.id) as `count` FROM shop_brand sb
            INNER JOIN shop_brand_translate sbt ON sb.id = sbt.id_brand
            WHERE {$sqlWhere} {$search}
            ORDER BY sb.id ASC";

            $count = ApiBase::$dbh->fetch($sql, $params, true);

            return (empty($error))
                ? $this->generateData(["count" => $count['count'], "items" => $items])
                : $this->generateData('Не удаётся получить бренды', true, $error);
        }

        /*
         * получение информации о брендах
         */
        public function get(){
            $items = (!empty($this->input['ids'])) ? $this->input['ids'] : false;
            if(!is_array($items)) {
                return $this->generateData('Не удаётся получить информацию о брендах', true, 'Отсутствует параметр ids (ids:array)');
            }

            $tmp = array();
            $params['id_lang'] = $this->projectConfig["id_lang"];
            foreach($items as $item) {
                $params[':id'.$item] = $item;
                $tmp[] = ':id'.$item;
            }
            $sqlWhere = 'sbt.id_lang = :id_lang AND sb.`id` IN (' . implode(',', $tmp). ')';

            $sql = "SELECT sb.id, sb.url, sb.image, sbt.name, sbt.description, sbt.content, sbt.meta_title, sbt.meta_description, sbt.meta_keywords FROM shop_brand sb
            INNER JOIN shop_brand_translate sbt ON sb.id = sbt.id_brand
            WHERE {$sqlWhere}
            ORDER BY sb.id ASC ";

            $items = ApiBase::$dbh->fetch($sql, $params);
            $count = count($items);
            $error = ApiBase::$dbh->error;

            return (empty($error))
                ? $this->generateData(["count" => "$count", "items" => $items])
                : $this->generateData('Не удаётся получить бренд', true, $error);
        }

        /*
         * удаление брендов
         */
        public function delete(){
            $items = (isset($this->input['ids'])) ? $this->input['ids'] : [];
            if(!is_array($items) || empty($items)) {
                return $this->generateData('Не удаётся удалить бренды', true, 'Отсутствует параметр ids (ids:array)');
            }

            $tmp = $params = array();
            foreach($items as $item) {
                $params[':id'.$item] = (int)$item;
                $tmp[] = ':id'.$item;
            }
            $sql    = 'DELETE FROM shop_brand WHERE `id` IN (' . implode(',', $tmp). ')';
            ApiBase::$dbh->execute($sql, $params);
            $error  = ApiBase::$dbh->error;

            return (empty($error))
                ? $this->generateData(["items" => $items])
                : $this->generateData('Не удаётся удалить бренд(ы)', true, $error);
        }

        /*
         * добавление брендов
         * array error_insert - ошибка создания записи
         * array ok, ids      - упешно проведенные
         * int empty          - отсутствуют параметры
         * int name           - название пустое
         */
        public function create() {
            $items = $this->input;
            if (!empty($items)) {
                $error_insert = $ok = $ids = [];
                $empty = $name = 0;
                $allowedFieldsSBT = array("id_brand", "id_lang", "name", "description", "content", "meta_title"
                                          , "meta_description", "meta_keywords");
                ApiBase::$dbh->setTransaction(false);
                foreach($items as $item) {
                    $new_id = 0;
                    if(!is_array($item) || empty($item) || empty($item['name'])) {
                        ++$empty;
                        continue;
                    }
                    ApiBase::$dbh->startTransaction();
                    $params = [];
                    $params['url']   = (isset($item['url'])) ? strtolower($item['url']) : '';
                    $params['image'] = (isset($item['image'])) ? $item['image'] : '';
                    if(empty($params['url']) && isset($item['name']) && !empty($item['name'])) {
                        $params['url'] = $this->getUrl($item['name']);
                    }
                    $status = false;
                    if($params['url']) {
                        $param_brand = ApiBase::$dbh->filterParams(array('url', 'image'), $params);
                        $sqlSB = "INSERT INTO shop_brand (url, image) VALUES (:url, :image)";
                        $new_id = ApiBase::$dbh->execute($sqlSB, $param_brand);
                        if($new_id < 1) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_insert[] = ApiBase::$dbh->error . ' Params: ' . print_r($item, 1);
                            continue;
                        }
                        $status = true;
                    }
                    if($status !== false) {
                        $params['id_brand'] = $new_id;
                        $params['id_lang']  = $this->projectConfig["id_lang"];
                        $params_translate   = ApiBase::$dbh->filterParams($allowedFieldsSBT, array_merge($item, $params));
                        $sqlSBT = "INSERT INTO shop_brand_translate (id_brand, id_lang, `name`, description, content, meta_title, meta_keywords, meta_description)
                                   VALUES (:id_brand, :id_lang, :name, :description, :content, :meta_title, :meta_keywords, :meta_description)";
                        $status_id = ApiBase::$dbh->execute($sqlSBT, $params_translate);
                        if($status_id !== false) {
                            $ok[]  = $item['name'];
                            $ids[] = $status_id;
                            ApiBase::$dbh->endTransaction();
                        } else {
                            $error_insert[] = ApiBase::$dbh->error . ' Params: ' . print_r($item, 1);
                            ApiBase::$dbh->cancelTransaction();
                        }
                    } else {
                        $name += 1;
                        ApiBase::$dbh->cancelTransaction();
                    }
                }
                ApiBase::$dbh->setTransaction(true);

                if($empty || $name || $error_insert)
                    return $this->generateData(['empty' => $empty, 'without_name' => $name, 'error' => $error_insert], true,
                        'Empty - кол-во записей без параметров, without_name - кол-во записей без названия, error - ошибки');

                $count = count($ids);
                return $this->generateData(["count" => "$count", "ids" => $ids, "names" => $ok]);
            }

            return $this->generateData('Не удаётся добавить бренды', true, 'Отсутствуют записи');
        }

        private function getUrl($name) {
            $url = strtolower($this->translit_str($name));
            $url_n = $url;
            $sql = 'SELECT url FROM shop_brand WHERE url = :url';
            $i = 1;
            while ($i < 10) {
                $status = ApiBase::$dbh->fetch($sql, array(':url' => $url_n));
                if (isset($status[0])) {
                    $url_n = $url . "-$i";
                } else {
                    return $url_n;
                }
                $i++;
            }
            return uniqid();
        }

        /*
         * изменить данные бренда
         * array empty - кол-во записей пустых или без параметра id (id:int)
         * array ids   - упешно проведенные
         */
        public function update() {
            $items = $this->input;
            if (!empty($items)) {
                $allowedFieldsSBT = array("name", "description", "content", "meta_title", "meta_description", "meta_keywords");
                $empty = 0;
                $error_update = $ids = [];
                ApiBase::$dbh->setTransaction(false);
                foreach($items as $item) {
                    if(!isset($item['id']) || ((int)$item['id'] == 0)) {
                        ++$empty;
                        continue;
                    }
                    ApiBase::$dbh->startTransaction();
                    $params = [];
                    if(isset($item['url']) && !empty($item['url'])) $params['url'] = strtolower($item['url']);
                    if(isset($item['image'])) $params['image'] = $item['image'];
                    $param_brand = ApiBase::$dbh->setParams(array('url', 'image'), $params);
                    if(!empty($param_brand)) {
                        $sqlSB = "UPDATE shop_brand SET {$param_brand[1]} WHERE `id`=:id";
                        $param_brand[0][':id'] = $item['id'];
                        $status = ApiBase::$dbh->execute($sqlSB, $param_brand[0]);
                        if(!$status) {
                            ApiBase::$dbh->cancelTransaction();
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                            continue;
                        }
                    }

                    $params_translate = ApiBase::$dbh->setParams($allowedFieldsSBT, $item);
                    if(!empty($params_translate)) {
                        $params_translate[0][':id_brand'] = $item['id'];
                        $params_translate[0][':id_lang']  = $this->projectConfig["id_lang"];
                        $sqlSBT = "UPDATE shop_brand_translate SET {$params_translate[1]} WHERE `id_brand`=:id_brand AND `id_lang`=:id_lang";
                        $status = ApiBase::$dbh->execute($sqlSBT, $params_translate[0]);
                        if(!$status) {
                            $error_update[] = ApiBase::$dbh->error . ' Params: ' . print_r($item);
                            ApiBase::$dbh->cancelTransaction();
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

                $count = count($ids);
                return $this->generateData(["ids" => $ids]);
            }

            return $this->generateData('Не удаётся изменить бренды', true, 'Отсутствуют записи');
        }


    }