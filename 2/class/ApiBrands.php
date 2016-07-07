<?php

    class ApiBrands extends ApiBase {

        public function fetch() {
            $sqlWhere = 'sbt.id_lang = :id_lang';
            $id = (isset($this->input['id'])) ? $this->input['id'] : '';
            if ($id > 0) {
                $params['id'] = $id;
                $sqlWhere .= ' AND sb.`id` = :id';
            }

            $sql = "SELECT sb.id, sb.url, sb.image, sbt.name, sbt.description, sbt.content, sbt.meta_title, sbt.meta_description, sbt.meta_keywords FROM shop_brand sb
            INNER JOIN shop_brand_translate sbt ON sb.id = sbt.id_brand
            WHERE {$sqlWhere}
            ORDER BY sb.id ASC ";

            $sth = $this->dbh->prepare($sql);
            $params['id_lang'] = $this->projectConfig["id_lang"];
            $status = $sth->execute($params);
            if ($status !== false) {
                $items = $sth->fetchAll(PDO::FETCH_ASSOC);
//                foreach($items as $key => $line) {
//                    $fields = array("name", "description", "content", "meta_title", "meta_description", "meta_keywords");
//                    foreach($fields as $row) {
//                        $items[$key][$row] = htmlspecialchars_decode($line[$row]);
//                    }
//                }
                $result["response"]["count"] = count($items);
                $result["response"]["items"] = $items;
                $result["response"]["server_time"] = time();
            } else {
                $this->isError = true;
                $result = 'Не удаётся получить бренды!';
            }
            return $result;

        }

        public function get(){
            return $this->fetch();
        }

        /*
         * удаление брендов
         * $this->input["id"] ожидается массив
         */
        public function delete(){
            $result = array();
            $values = (is_array($this->input["id"])) ? $this->input["id"] : array();
            if(!empty($values)) {
                $params[':values'] = implode(",", $values);
                $sql_group = "DELETE FROM shop_brand WHERE id IN (:values)";
                $sth_group = $this->dbh->prepare($sql_group);
                $sth_group->execute($params);
            }
            $result["response"]["server_time"] = time();
            return $result;
        }

        public function create(){
            return $this->insert($this->input);
        }

        protected function insert($items) {
            $result = array();
            $allowedFieldsSBT = array("name", "description", "content", "meta_title", "meta_description", "meta_keywords");
            if ($items) {
                $sqlSB = "INSERT INTO shop_brand (url, image) VALUES (:url, :image)";
                $sthSB = $this->dbh->prepare($sqlSB);

                $params[':url'] = (isset($items['url'])) ? strtolower($items['url']) : '';
                $params[':image'] = (isset($items['image'])) ? $items['image'] : '';

                $status = false;
                if(empty($params[':url']) && isset($items['name']) && !empty($items['name'])) {
                    $params[':url'] = $this->getUrl($items['name']);
                }
                if($params[':url']) {
                    $status = $sthSB->execute($params);
                }
                if ($status !== false) {
                    $status = false;
                    $sqlSBT = "INSERT INTO shop_brand_translate (id_brand, id_lang, `name`, description, content, meta_title, meta_keywords, meta_description)
                              VALUES (:id_brand, :id_lang, :name, :description, :content, :meta_title, :meta_keywords, :meta_description)";
                    $sthSBT = $this->dbh->prepare($sqlSBT);

                    unset($params);
                    $params[':id_brand'] = $this->dbh->lastInsertId();
                    $params[':id_lang'] = 1;
                    foreach($allowedFieldsSBT as $line) {
                        if($line == 'name') {
                            $items['name'] = (isset($items[$line])) ? strip_tags($items[$line]) : '';
                        }
                        $params[':'.$line] = (isset($items[$line])) ? htmlspecialchars($items[$line]) : '';
                    }

                    $status = $sthSBT->execute($params);
                    if ($status !== false) {
                        $result["response"]["id"] = $params[':id_brand'];
                        $result["response"]["server_time"] = time();
                    } else {
                        $this->isError = true;
                        $result = 'Ошибка при записи в shop_brand_translate';
                    }
                } else {
                    $this->isError = true;
                    $result = 'Не удаётся записать бренд!';
                }
            }

            return $result;
        }

        protected function update($items) {

        }

        private function getUrl($name) {
            $url = strtolower($this->translit_str($name));
            $url_n = $url;
            $sql = 'SELECT url FROM shop_product WHERE url = :url';
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

    }