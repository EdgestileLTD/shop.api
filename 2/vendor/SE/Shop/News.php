<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class News extends Base
{
    protected $tableName = "news";

    private function convertFields($str)
    {
        $str = str_replace('id ', 'n.id ', $str);
        $str = str_replace('idGroup', 'n.id_category', $str);
        $str = str_replace('newsDate', 'n.news_date', $str);
        $str = str_replace('nameCategory', 'nc.title', $str);
        $str = str_replace('idCategory', 'n.id_category', $str);
        $str = str_replace('image', 'n.id', $str);
        $str = str_replace('name', 'n.title', $str);
        $str = str_replace('display', 'n.title', $str);
        //$str = str_replace('localize', 'ng.id', $str);


        return $str;
    }

    public function fetch()
    {
        try {
            $this->createDb();
            $items = array();

            $u = new DB('news', 'n');
            $u->addField('is_date_public', 'tinyint(1)', '0', 1);
            $u->addField('sort', 'integer', '0', 1);
            $u->addField('url', 'varchar(255)', 'NULL', 2);

            $u->select('n.*, nc.title name_category, GROUP_CONCAT(sc.name) AS localize');
            $u->leftJoin('news_category nc', 'nc.id = n.id_category');
            $u->leftJoin('news_gcontacts ng', 'n.id = ng.id_news');
            $u->leftJoin('shop_contacts sc', 'sc.id = ng.id_gcontact');

            $filter = array();
            if (!empty($this->input["filters"])) {
                foreach($this->input["filters"] as $f) {
                    $filter[] = '(' . $this->convertFields($f['field']) . '=' . $f['value'] . ')';
                }
            }

            if(!empty($this->input["searchText"])){
                $s = trim($this->input["searchText"]);
                $filter[] = "(n.text LIKE '%{$s}%' OR n.short_txt LIKE '%{$s}%' OR n.title LIKE '%{$s}%')";
            }

            if (!empty($filter))
                $where = implode(' AND ', $filter);

            if (!empty($where))
                $u->where($where);
            $u->groupBy('n.id');

            $sortBy = $this->convertFields($this->sortBy);
            if ($sortBy)
                $u->orderby($sortBy, $this->sortOrder === 'desc');

            $count = $u->getListCount();
            $objects = $u->getList($this->limit, $this->offset);
            foreach ($objects as $item) {
                $new = $item;
                $new['name'] = $item['title'];
                $new['isActive'] = $item['active'] == 'Y';
                $new['imageFile'] = $item['img'];
                $new['fullDescription'] = $item['text'];
                if (!empty($item['newsDate'])) {
                    $new['newsDate'] = date('Y-m-d', $item['newsDate']);
                    $new['newsDateDisplay'] = date('d.m.Y', $item['newsDate']);
                }

                if (!empty($item['pubDate'])) {
                    $new['publicationDate'] = date('Y-m-d', $item['pubDate']);
                    //$new['publicationDateDisplay'] = date('d.m.Y', $item['pubDate']);
                }
                if ($new['imageFile']) {
                    if (strpos($new['imageFile'], "://") === false) {
                        $new['imageUrl'] = 'http://' . $this->hostname . "/images/rus/newsimg/" . $new['imageFile'];
                        $new['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $new['imageFile'];
                    } else {
                        $new['imageUrl'] = $new['imageFile'];
                        $new['imageUrlPreview'] = $new['imageFile'];
                    }
                }
                $items[] = $new;
            }

            $this->result['count'] = $count;
            $this->result['items'] = $items;

        } catch (Exception $e) {
            $this->error = "Не удаётся получить список новостей!";
        }

        return $this;
    }

    private function createDb()
    {
        DB::query("CREATE TABLE IF NOT EXISTS `news_gcontacts` (
              `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `id_news` int(10) UNSIGNED NOT NULL,
              `id_gcontact` int(10) UNSIGNED NOT NULL,
              `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `id_news` (`id_news`),
              KEY `id_gcontact` (`id_gcontact`),
              CONSTRAINT `news_gcontact_ibfk_1` FOREIGN KEY (`id_news`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `news_gcontact_ibfk_2` FOREIGN KEY (`id_gcontact`) REFERENCES `shop_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    private function createDbCustom()
    {
        DB::query("CREATE TABLE IF NOT EXISTS `news_userfields` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_news` int(10) UNSIGNED NOT NULL,
          `id_userfield` int(10) UNSIGNED NOT NULL,
          `value` text,
          `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `FK_person_userfields_se_user_id` (`id_news`),
          KEY `FK_person_userfields_shop_userfields_id` (`id_userfield`),
          CONSTRAINT `news_userfields_ibfk_1` FOREIGN KEY (`id_news`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `news_userfields_ibfk_2` FOREIGN KEY (`id_userfield`) REFERENCES `shop_userfields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    private function getImages($id)
    {
        $u = new DB('news_img', 'ni');
        $u->where('ni.id_news=?', $id);
        $objects = $u->getList();
        $result = array();
        foreach ($objects as $item) {
            $image = null;
            $image['id'] = $item['id'];
            $image['imageFile'] = $item['picture'];
            $image['imageAlt'] = $item['pictureAlt'];
            $image['sortIndex'] = $item['sort'];
            $image['isMain'] = (bool)$item['default'];
            if ($image['imageFile']) {
                if (strpos($image['imageFile'], "://") === false) {
                    $image['imageUrl'] = 'http://' . $this->hostname . "/images/rus/newsimg/" . $image['imageFile'];
                    $image['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $image['imageFile'];
                } else {
                    $image['imageUrl'] = $image['imageFile'];
                    $image['imageUrlPreview'] = $image['imageFile'];
                }
            }
            $result[] = $image;
        }
        return $result;
    }

    private function getSubscribersGroups($id)
    {
        $u = new DB('news_subscriber_se_group', 's');
        $u->select('s.id_group id, gr.name');
        $u->innerJoin('se_group gr', 'gr.id = s.id_group');
        $u->where('id_news = ?', $id);
        return $u->getList();
    }

    private function getGeoCity($id)
    {
        $u = new DB('news_gcontacts', 'ng');
        $u->select('sc.id, sc.name');
        $u->innerJoin('shop_contacts sc', 'ng.id_gcontact = sc.id');
        $u->where('ng.id_news = ?', $id);
        return $u->getList();
    }

    private function getCustomFields()
    {
        try {
            $this->createDbCustom();
            $idNews = intval($this->input["id"]);
            $u = new DB('shop_userfields', 'su');
            $u->addField('def', 'text');
            $u->select("cu.id, cu.id_news, cu.value, su.def, su.id id_userfield, 
                    su.name, su.type, su.values, sug.id id_group, sug.name name_group");
            $u->leftJoin('news_userfields cu', "cu.id_userfield = su.id AND cu.id_news = {$idNews}");
            $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
            $u->where('su.data = "public"');
            $u->groupBy('su.id');
            $u->orderBy('sug.sort');
            $u->addOrderBy('su.sort');
            $result = $u->getList();

            $groups = array();
            foreach ($result as $item) {
                $groups[intval($item["idGroup"])]["id"] = $item["idGroup"];
                $groups[intval($item["idGroup"])]["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
                if ($item['type'] == "date")
                    $item['value'] = date('Y-m-d', strtotime($item['value']));
                $groups[intval($item["idGroup"])]["items"][] = $item;
            }
            $grlist = array();
            foreach ($groups as $id => $gr) {
                $grlist[] = $gr;
            }
            return $grlist;
        } catch (Exception $e) {
            return false;
        }
    }

    public function info($id = NULL)
    {
        try {
            $u = new DB('news', 'n');
            $u->select('n.*, nc.title AS name_category');
            $u->leftJoin('news_category nc', 'nc.id = n.id_category');
            $news = $item = $u->getInfo($this->input["id"]);
            $news['name'] = $item['title'];
            $news['isActive'] = $item['active'] == 'Y';
            $news['imageFile'] = $item['img'];
            //$news['description'] = $item['short_txt'];
            //$news['fullDescription'] = $item['text'];
            if (!empty($item['newsDate'])){
                $news['newsDate'] = date('Y-m-d', $item['newsDate']);
                $news['newsDateDisplay'] = date('d.m.Y', $item['newsDate']);
            }
            if (!empty($item['pubDate'])) {
                $news['publicationDate'] = date('Y-m-d', $item['pubDate']);
                $news['publicationDateDisplay'] = date('d.m.Y', $item['pubDate']);
            }
            $news['images'] = $this->getImages($item['id']);
            if ($news['imageFile']) {
                if (strpos($news['imageFile'], "://") === false) {
                    $news['imageUrl'] = 'http://' . $this->hostname . "/images/rus/newsimg/" . $news['imageFile'];
                    $news['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $news['imageFile'];
                } else {
                    $news['imageUrl'] = $news['imageFile'];
                    $news['imageUrlPreview'] = $news['imageFile'];
                }
            }
            $news['subscribersGroups'] = $this->getSubscribersGroups($item['id']);
            $news['geoCity'] = $this->getGeoCity($item['id']);
            $news['customFields'] = $this->getCustomFields();
            $this->result = $news;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о запрошенной новсти!";
        }
        return $this;
    }

    private function saveImages()
    {

        if (!$this->input["id"] || !isset($this->input["images"]))
            return;

        $u = new DB('news_img');
        $u->where('id_news = (?)', $this->input["id"])->deleteList();
        foreach ($this->input["images"] as $image)
            $data[] = array('id_news' => $this->input["id"], 'picture' => $image["imageFile"],
                'sort' => (int)$image["sortIndex"], 'picture_alt' => $image["imageAlt"]);
        if ($data)
            DB::insertList('news_img', $data);
    }

    private function saveSubscribersGroups()
    {
        if (!$this->input["id"] || !isset($this->input["subscribersGroups"]))
            return;
        DB::saveManyToMany($this->input["id"], $this->input["subscribersGroups"],
            array("table" => "news_subscriber_se_group", "key" => "id_news", "link" => "id_group"));
    }

    private function createCampaignForMails()
    {
        $idsGroups = array();
        foreach ($this->input["subscribersGroups"] as $group)
            $idsGroups[] = $group["id"];
        $idsBooks = ContactCategory::getIdsBooksByIdGroups($idsGroups);

        foreach ($idsBooks as $idBook) {
            $ep = new EmailProvider();
            $ep->createCampaign($this->input["name"], $this->input["text"], $idBook, $this->input["pubDate"]);
        }
    }

    private function saveGeoCity()
    {
        try {
            $ids = $this->input["ids"];
            if (!$ids) {
                $ids = array($this->input["id"]);
            }
            $gcontacts = $this->input['geoCity'];
            if (!isset($gcontacts)) return true;
            $idsExists = array();
            foreach ($gcontacts as $p)
                if ($p["id"])
                    $idsExists[] = $p["id"];
            //$idsExists = array_diff($idsExists, $ids);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $ids);
            $u = new DB('news_gcontacts', 'ng');
            if ($idsExistsStr)
                $u->where("((NOT id_gcontact IN ({$idsExistsStr})) AND id_news IN (?))", $idsStr)->deleteList();
            else $u->where('id_news IN (?)', $idsStr)->deleteList();

            $idsExists = array();
            if ($idsExistsStr) {
                $u->select("id_news, id_gcontact");
                $u->where("((id_gcontact IN ({$idsExistsStr})) AND id_news IN (?))", $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item) {
                    $idsExists[] = $item["idGcontact"];
                }
            };
            $data = array();
            foreach ($gcontacts as $p)
                if (empty($idsExists) || !in_array($p["id"], $idsExists))
                    foreach ($ids as $idNews)
                        $data[] = array('id_news' => $idNews, 'id_gcontact' => $p["id"]);
            if (!empty($data))
                DB::insertList('news_gcontacts', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить Гео-контакты!";
            throw new Exception($this->error);
        }
    }

    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]) && !$this->input["customFields"])
            return true;

        try {
            $idNews = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $u = new DB('news_userfields', 'cu');
                if (!$field["value"] && $field['id']) {
                    $u->where('id=?', $field['id'])->deleteList();
                } else {
                    $field["idNews"] = $idNews;
                    $u->setValuesFields($field);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о публикации!";
            throw new Exception($this->error);
        }
    }

    private function deleteCampaignForMails()
    {

    }

    public function save($isTransactionMode = true)
    {
        try {
            DB::beginTransaction();
            if (empty($this->input["url"]))
                $this->input["url"] = strtolower(se_translite_url($this->input["name"]));
            if (isset($this->input["name"]))
                $this->input["title"] = $this->input["name"];
            if (isset($this->input["newsDate"]))
                $this->input["newsDate"] = strtotime($this->input["newsDate"]);
            if (isset($this->input["publicationDate"]))
                $this->input["pubDate"] = strtotime($this->input["publicationDate"]);
            //if (isset($this->input["fullDescription"]))
            //    $this->input["text"] = $this->input["fullDescription"];
            if (isset($this->input["imageFile"]))
                $this->input["img"] = $this->input["imageFile"];
            if (isset($this->input["isActive"]))
                $this->input["active"] = $this->input["isActive"];

            $u = new DB('news');
            $u->addField('seotitle', 'varchar(255)');
            $u->addField('keywords', 'varchar(255)');
            $u->addField('description', 'varchar(255)');
            while (true) {
                $u = new DB('news');
                $u->select('id');
                $u->where("url='?'", $this->input["url"]);
                $res = $u->fetchOne();
                if (empty($res['id']) || $res['id'] == $this->input["id"]) {
                    break;
                } else {
                    $url = $this->input["url"];
                    $rr = end(explode('-', $this->input["url"]));
                    if (is_numeric($rr)) {
                        $url = substr($url, 0, -strlen($rr));
                        $rr++;
                        $this->input["url"] = $url . $rr;
                    } else {
                        $this->input["url"] = $this->input["url"] . '-1';
                    }
                }
            }

            $u = new DB('news');
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();

            $this->saveImages();
            $this->saveSubscribersGroups();
            if ($this->isNew)
                $this->deleteCampaignForMails();
            if ($this->input["customFields"])
                $this->saveCustomFields();

            if (isset($this->input["geoCity"]))
                $this->saveGeoCity();

            $this->createCampaignForMails();
            $this->info();
            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            $this->error = "Не удаётся сохранить публикацию!";
        }

        return $this;
    }

}