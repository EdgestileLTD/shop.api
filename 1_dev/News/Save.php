<?php

    function saveImages($idsNews, $images)
    {
        $idsStr = implode(",", $idsNews);

        $u = new seTable('news_img','ni');
        $u->where('id_news in (?)', $idsStr)->deletelist();

        foreach($images as $image)
            foreach($idsNews as $idNew)
                $data[] = array('id_news' => $idNew, 'picture' => $image->imageFile,
                    'sort' => $image->sortIndex, 'picture_alt' => $image->imageAlt);
        if (!empty($data))
            se_db_InsertList('news_img', $data);
    }

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    $u = new seTable('news', 'n');

    if ($isNew || !empty($ids)) {
        $isUpdated = false;
        $isUpdated |= setField($isNew, $u, $json->idGroup, 'id_category');
        $isUpdated |= setField($isNew, $u, $json->name, 'title');
        if (isset($json->newsDate)) {
            $isUpdated |= setField($isNew, $u, strtotime($json->newsDate), 'news_date');
        }
        if (isset($json->publicationDate))
            $isUpdated |= setField($isNew, $u, strtotime($json->publicationDate), 'pub_date');
        $isUpdated |= setField($isNew, $u, $json->fullDescription, 'text');
        $isUpdated |= setField($isNew, $u, $json->imageFile, 'img');
        if ($json->isActive)
            $isUpdated |= setField($isNew, $u, 'Y', 'active');
        else $isUpdated |= setField($isNew, $u, 'N', 'active');

        if ($isUpdated){
            if (!empty($idsStr)) {
                if ($idsStr != "all")
                    $u->where('id in (?)', $idsStr);
                else $u->where('true');
            }
            $idv = $u->save();
            if ($isNew)
                $ids[] = $idv;
        }
        if ($ids && isset($json->images))
            saveImages($ids, $json->images);
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);