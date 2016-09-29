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
        $str = str_replace('image', 'n.id', $str);
        $str = str_replace('name', 'n.title', $str);
        $str = str_replace('display', 'n.title', $str);
        return $str;
    }

    public function fetch()
    {
        try {
            $u = new DB('news', 'n');
            $u->select('n.*');

            if (!empty($this->input["filter"]))
                $filter = convertFields($this->input["filter"]);
            if (!empty($filter))
                $where = $filter;
            if (!empty($where))
                $where = "(" . $where . ")";
            if (!empty($where))
                $u->where($where);
            $u->groupBy('id');

            $sortBy = $this->convertFields($this->sortBy);
            if ($sortBy)
                $u->orderby($sortBy, $this->sortOrder === 'desc');

            $count = $u->getListCount();
            $objects = $u->getList($this->offset, $this->limit);
            foreach ($objects as $item) {
                $new = $item;
                $new['name'] = $item['title'];
                $new['isActive'] = $item['active'] == 'Y';
                $new['imageFile'] = $item['img'];
                $new['fullDescription'] = $item['text'];
                if (!empty($item['newsDate']))
                    $new['newsDate'] = date('Y-m-d', $item['newsDate']);
                if (!empty($item['pubDate'])) {
                    $new['publicationDate'] = date('Y-m-d', $item['pubDate']);
                    $new['publicationDateDisplay'] = date('d.m.Y', $item['pubDate']);
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
            $image['imageAlt'] = $item['picture_alt'];
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

    public function info()
    {
        try {
            $u = new DB('news', 'n');
            $u->select('n.*, nc.title name_category');
            $u->leftJoin('news_category nc', 'nc.id = n.id_category');
            $news = $item = $u->getInfo($this->input["id"]);
            $news['name'] = $item['title'];
            $news['isActive'] = $item['active'] == 'Y';
            $news['imageFile'] = $item['img'];
            $news['description'] = $item['short_txt'];
            $news['fullDescription'] = $item['text'];
            if (!empty($item['newsDate']))
                $news['newsDate'] = date('Y-m-d', $item['newsDate']);
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
            $this->result = $news;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о запрошенной новсти!";
        }
        return $this;
    }

    private function saveImages()
    {
        if (!$this->input["id"] || !($images = $this->input["images"]))
            return;

        $u = new DB('news_img');
        $u->where('id_news = (?)', $this->input["id"])->deleteList();
        foreach ($images as $image)
            $data[] = array('id_news' => $this->input["id"], 'picture' => $image["imageFile"],
                'sort' => (int) $image["sortIndex"], 'picture_alt' => $image["imageAlt"]);
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

        foreach ($idsBooks as $idBook)
            (new EmailProvider())->createCampaign($this->input["name"], $this->input["text"], $idBook, $this->input["pubDate"]);
    }

    private function deleteCampaignForMails()
    {

    }

    public function save()
    {
        try {
            DB::beginTransaction();
            if (isset($this->input["name"]))
                $this->input["title"] = $this->input["name"];
            if (isset($this->input["newsDate"]))
                $this->input["newsDate"] = strtotime($this->input["newsDate"]);
            if (isset($this->input["publicationDate"]))
                $this->input["pubDate"] = strtotime($this->input["publicationDate"]);            
            if (isset($this->input["fullDescription"]))
                $this->input["text"] = $this->input["fullDescription"];
            if (isset($this->input["imageFile"]))
                $this->input["img"] = $this->input["imageFile"];
            if (isset($this->input["isActive"]))
                $this->input["active"] = $this->input["isActive"];

            $u = new DB('news');
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            $this->saveImages();
            $this->saveSubscribersGroups();
            if ($this->isNew)
                $this->deleteCampaignForMails();
            $this->createCampaignForMails();
            $this->info();
            DB::commit();
            
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = "Не удаётся сохранить новость!";
        }
        
        return $this;
    }

}
