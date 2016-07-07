<?php

class plugin_macros {
	
	private $user_id = 0;
	private $order_id = 0;
	private $payment_id = 0;
	private $lang;
	private $page = '';
	private $razdel = '';
	private $curr = '';
	private $basecurr= '';
	private $main;
	private $summ = 0.00;
	private $ORDER = array();

	public function __construct($user_id = 0, $order_id = 0, $payment_id = 0){

        $this->user_id = $user_id;
		if (!$this->user_id && function_exists('seUserId')) {
			$this->user_id = seUserId();
		}
		$this->order_id = $order_id;
		$this->payment_id = $payment_id;
		$this->curr = se_baseCurrency();
		$this->basecurr = $this->curr;
		if (function_exists('getRequest')){
			$this->page = getRequest('page');
			$this->razdel = getRequest('razdel');
		}
        $this->lang = se_getLang();
		$this->main = se_getAdmin();
		if ($this->order_id) {
			$query = se_db_query("SELECT so.id_author, so.payment_type,so.date_order,
				so.date_payee, so.discount, so.commentary,
				(SELECT sa.account FROM shop_account sa WHERE sa.id_order=so.id LIMIT 1) as account,
				so.curr, so.status, so.delivery_payee, 
				(SELECT dl.name FROM shop_deliverytype dl WHERE dl.id=so.delivery_type) as delivery_name, 
				so.delivery_status, so.delivery_date,
				(SELECT SUM((st.price) * st.count) FROM shop_tovarorder st WHERE st.id_order=so.id) AS `price_tovar`
			FROM `shop_order` so
			WHERE so.id = '{$this->order_id}'");

			$this->ORDER = se_db_fetch_assoc($query);
			$this->user_id = $this->ORDER['id_author'];
		}
	}

	public function summtostring($summa){
		$nf = array('zero','one','two','three','four','five','six','seven','eight','nine');
		$sel = "zero,one,two,three,four,five,six,seven,eight,nine";
		$reg = array('edin','dec','des','sot','mel','thou','mill','wh','fr');
		foreach($reg as $r){
			$d[$r]=se_db_fields_item('spr_numbers',"registr='$r'",$sel);
		}
		$summa = str_replace(array(' ', ','),  array('', '.'),  $summa);
		$des = explode('.', $summa);

		$c = utf8_strlen($des[0]);
		for ($i=1; $i<=$c; $i++) {
			$nums[$i] = utf8_substr($des[0], $c-$i, 1);
		}
		$rez = '';
		if ($nums[7] != '') $rez .= $d['mill'][$nums[7]]. ' ';
		if ($nums[6] != '') $rez .= $d['sot'][$nums[6]]. ' ';
		if ($nums[5] != '' && $nums[5] != 1) $rez .= $d['dec'][$nums[5]].' ';
		if ($nums[5] != '' && $nums[5] == 1) $rez .= $d['des'][$nums[4]] . ' ' . $d['thou'][0]." ";
		if ($nums[4] != '' && $nums[5] != 1) $rez .= $d['mel'][$nums[4]].' '.$d['thou'][$nums[4]]." ";
    
	
		if ($nums[3] != '') $rez .= $d['sot'][$nums[3]]." ";
		if ($nums[2] != '' && $nums[2] != 1) $rez.=$d['dec'][$nums[2]]." ";
		if ($nums[2] != '' &&  $nums[2] == 1) $rez.=$d['des'][$nums[1]]." ";
		if ($nums[1] != '' &&  $nums[2] != 1) $rez.=$d['edin'][$nums[1]]." ";
		if (!empty($rez)) $rez = $rez.$d['wh'][0]." ";
		$kop = $des[1];

		while (utf8_strlen($kop) < 2) $kop.="0";
		$rez.=$kop." ".$d['fr'][0];

		$rez='<span style="Text-transform:uppercase;">'.utf8_substr($rez,0,1).'</span>' . utf8_substr($rez, 1, utf8_strlen($rez) - 1);
		return($rez);
	}

	public function getMonth($m) {
		if ($this->lang=='rus' || $this->lang=='blr')
			$smonth = array('января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');
		else
			$smonth = array('January',' February','March','April','May','June','July','August','September','October','November','December');
		return $smonth[$m - 1];
	}

	private function parseOrder($text){
		if ($this->order_id) {
			if (!$this->payment_id) $this->payment_id = $this->ORDER['payment_type'];
			if (!empty($this->ORDER))
			foreach ($this->ORDER as $k => $v) {
				$text = str_replace("[ORDER.".strtoupper($k)."]", trim($v), $text);
			}
			$query = se_db_query("SELECT telnumber,email,calltime,address,postindex FROM shop_delivery WHERE id_order='{$this->order_id}'");
			$ORDERADDR = se_db_fetch_assoc($query);
			if (!empty($ORDERADDR))
			foreach ($ORDERADDR as $k => $v) {
					$text = str_replace("[ORDER.".strtoupper($k)."]", trim($v), $text);
			}

			$query = se_db_query("SELECT `count`,`price`,`discount` FROM  shop_tovarorder WHERE id_order='{$this->order_id}';");
			$discount = 0;
			$rozn = 0;
			if (!empty($query))
			while ($res = se_db_fetch_assoc($query)) {
				$discount += round(se_MoneyConvert($res['discount'], $this->ORDER['curr'], $this->curr),2) * $res['count'];
			}
			$summ = round(se_MoneyConvert($this->ORDER['price_tovar'] + $this->ORDER['delivery_payee']- $this->ORDER['discount'], $this->ORDER['curr'], $this->curr), 2) - $discount; //-$ORDER['discount']
			$this->summ = $summ;
			$fullsumm = round(se_MoneyConvert($this->ORDER['price_tovar'], $this->ORDER['curr'], $this->curr), 2);
			$delivery = round(se_MoneyConvert($this->ORDER['delivery_payee'], $this->ORDER['curr'],$this->curr), 2);

                    $discount += round(se_money_convert($this->ORDER['discount'], $this->ORDER['curr'], $this->curr), 2);
                    $modsumma = explode('.', str_replace(',','.',$summ));
                    if (!isset($modsumma[1])) $modsumma[1] = 0;

                    $array_change = array('ORDER_DISCOUNT' => se_formatMoney($discount, $this->curr),
                    'SHOP_ORDER_DISCOUNT'=>se_formatMoney($discount, $this->curr),
                    'ORDER_SUMMA'=>se_formatMoney($summ, $this->curr),
                    'SHOP_ORDER_SUMM'=>se_formatMoney($fullsumm, $this->curr),
                    'ORDER_DELIVERY'=>$delivery,
                    'SHOP_ORDER_DEVILERY'=>se_formatMoney($delivery, $this->curr),
                    'SHOP_ORDER_DELIVERY'=>se_formatMoney($delivery, $this->curr),
                    'SHOP_ORDER_TOTAL'=>se_formatMoney($summ + $delivery, $this->curr),
                    'ORDER_SUMM_NOTAX'=>se_formatMoney($summ - $this->main['nds']/(100 + $this->main['nds']) * $summ, $this->curr),
                    'ORDER.SUMM_NOTAX'=>str_replace(',','.',($summ - $this->main['nds']/(100 + $this->main['nds']) * $summ)),
                    'ORDER_SUMM_WITH_TAX'=>se_formatMoney($summ + $this->main['nds']/100 * $summ, $this->curr),
                    'ORDER.SUMM_WITH_TAX'=>str_replace(',','.',($summ + $this->main['nds']/100 * $summ)),
                    'ORDER_SUMM_TAX_EXT'=>se_formatMoney($this->main['nds']/100 * $summ, $this->curr),
                    'ORDER.SUMM_TAX_EXT'=>str_replace(',','.',($this->main['nds']/100 * $summ)),
                    'ORDER.SUMMA'=> str_replace(',','.',$summ),
                    'ORDER.SUMMA_WHOL'=>$modsumma[0],
                    'ORDER.SUMMA_FRAC'=>$modsumma[1],				
                    'ORDER.AMOUNT'=>round($summ,2) * 100,
                    'ORDER_SUMMNDS'=>se_formatMoney($this->main['nds']/(100 + $this->main['nds'])*$summ, $this->curr),
                    'ORDER_SUMM_TAX'=>se_formatMoney($this->main['nds']/(100 + $this->main['nds']) * $summ, $this->curr),
                    'ORDER_TAX'=>round($this->main['nds']),
                    'ORDER.SUMM_TAX'=>str_replace(',','.', $this->main['nds']/(100 + $this->main['nds']) * $summ),
                    'ORDER.ID'=>$this->order_id,
                    'SHOP_ORDER_NUM'=>$this->order_id,
                    'ORDER.SID'=>md5($this->order_id.'se56Re2')
			);
			$this->user_id = $this->ORDER['id_author'];
			if (!empty($array_change))
			foreach ($array_change as $k => $v){
				while (preg_match("/\[".$k."]/", $text)){
					$text = str_replace("[{$k}]", $v, $text);
				}
			}

			// Список доставки
			if (preg_match("/\<DELIVERY\>([\w\W]{1,})\<\/DELIVERY\>/i", $text, $res_math)){
				if (!($this->ORDER['delivery_payee'] > 0)) $res_math[1]='';
				$text = str_replace($res_math[0], $res_math[1], $text);
			}
			
			if (strpos($text, '[SHOP_ORDER_VALUE_LIST]')!==false){
				$value_list = '<table border=0 cellpadding=3 cellspacing=1>';
				$value_list .= '<tr><td>Num</td><td>Picture</td><td>Name</td><td>Price</td><td>Discount</td><td>Count</td><td>Total</td>';
				$value_list .= '</tr><SHOPLIST><tr>';
				$value_list .= '<td>[SHOPLIST.IMG]</td>';
				$value_list .= '<td>[SHOPLIST.ITEM]</td><td>[SHOPLIST.NAME]</td>';
				$value_list .= '<td>[SHOPLIST.PRICE]</td><td>[SHOPLIST.DISCOUNT]</td>';
				$value_list .= '<td>[SHOPLIST.COUNT]</td><td>[SHOPLIST.SUMMA]</td>';
				$value_list .= '</tr></SHOPLIST></table>';
				$text = str_replace('[SHOP_ORDER_VALUE_LIST]', $value_list, $text);
			}

            //  модификации
            if ((strpos($text, '[SHOPLIST.PARAMS]')!==false) || (strpos($text, '[SHOPLIST.PARAM.')!==false)) {
                $query = se_db_query("SELECT st.modifications, sp.name, sp.article, st.price, st.discount, st.count
                                        FROM shop_tovarorder st
                                        LEFT OUTER JOIN shop_price sp ON (sp.id = st.id_price)
                                        WHERE (`id_order`='{$this->order_id}')");
                $modifications = $headers = array();
                $counter = 0;
                while($res = se_db_fetch_assoc($query)) {
                    $modifications[$counter]['name'] = $res['name'];
                    $modifications[$counter]['price'] = $res['price'];
                    $modifications[$counter]['discount'] = $res['discount'];
                    $modifications[$counter]['count'] = $res['count'];
                    $modifications[$counter]['article'] = $res['article'];

                    if ($res['modifications']) {
                        $shop_modifications = new seTable('shop_modifications', 'sm');
                        $shop_modifications->select('GROUP_CONCAT(CONCAT(sf.id,"<->",sf.name) SEPARATOR "||") as ids,
                                                    GROUP_CONCAT(CONCAT(sf.name,"<->",sfvl.value,"<->",sf.id) SEPARATOR "||") AS paramsname');
                        $shop_modifications->innerjoin('shop_modifications_feature smf', 'sm.id=smf.id_modification');
                        $shop_modifications->innerjoin('shop_feature sf', 'smf.id_feature=sf.id');
                        $shop_modifications->innerjoin('shop_feature_value_list sfvl', 'smf.id_value=sfvl.id');
                        $shop_modifications->where('sm.id IN (?)', $res['modifications']);
                        $result = $shop_modifications->fetchOne();
                        $modificationsList = explode("||",$result['paramsname']);
                        $modificationsHeaders = explode("||",$result['ids']);

                        //  назначение модификации отдельно
                        if (!empty($modificationsList)) {
                            foreach($modificationsList as $line) {
                                list($modKey, $modVal) = explode("<->", $line);
                                $text = str_replace('[SHOPLIST.PARAM.'.mb_strtoupper($modKey).']', $modVal, $text);
                                unset($modKey, $modVal);
                            }
                        }

                        //  заголовки таблицы
                        foreach($modificationsHeaders as $line) {
                            if($line != ''){
                                list($key, $value) = explode("<->", $line);
                                $headers[$key] = $value;
                            }
                        }

                        //  значения параметров для таблицы
                        foreach($modificationsList as $line) {
                            if($line != '') {
                                list($key, $value, $id) = explode("<->", $line);
                                $modifications[$counter]['params'][$id] = $value;
                                unset($key, $value, $id);
                            }
                        }
                    }
                    $counter++;
                }
                unset($result, $shop_modifications, $modificationsList, $modificationsHeaders);

                $shopListParams = '<table border="0" class="shopTable" style="width: 100%; padding: 20px;" cellpadding="0" cellspacing="0">';
                $shopListParams .= '<tr>';
                //  формируем шапку
                $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">Наименование</td>';
                $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">Артикул</td>';
                foreach($headers as $line) {
                    $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">'.$line.'</td>';
                }
                $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">Кол-во</td>';
                $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">Цена за ед.</td>';
                $shopListParams .= '<td style="border-bottom-style: solid; border-width: 1px;">Общая цена</td>';
                $shopListParams .= '</tr>';

                //  заполняем таблицу
                $counter = 1;
                foreach($modifications as $line) {
                    $shopListParams .= '<tr >';
                    $shopListParams .= '<td style="padding-top: 10px;">'.$counter.'.  '.$line['name'].'</td>';
                    $shopListParams .= '<td style="padding-top: 10px;">'.$line['article'].'</td>';

                    //  значения параметров
                    foreach($headers as $tmpKey=>$tmpVal) {
                        if (array_key_exists($tmpKey, $line['params']))
                            $shopListParams .= '<td style="padding-top: 10px;">'.$line['params'][$tmpKey].'</td>';
                        else
                            $shopListParams .= '<td style="padding-top: 10px;">&nbsp;</td>';
                    }
                    $shopListParams .= '<td style="padding-top: 10px;">'.floatval($line['count']).'</td>';
                    $shopListParams .= '<td style="padding-top: 10px;">'.floatval($line['price']-$line['discount']).'</td>';
                    $shopListParams .= '<td style="padding-top: 10px;">'.floatval(($line['price']-$line['discount'])*$line['count']).'</td>';
                    $shopListParams .= '</tr>';

                    $counter++;
                }
                $shopListParams .= '</table>';
                $text = str_replace('[SHOPLIST.PARAMS]', $shopListParams, $text);
            }


            if (preg_match("/\<SHOPLIST\>([\w\W]{1,})\<\/SHOPLIST\>/i", $text, $res_math)){
				$SHOPLIST="";
				$query=se_db_query("SELECT sp.name, st.`nameitem`, st.count,st.discount,st.price, sp.img
					FROM shop_tovarorder st
					LEFT OUTER JOIN shop_price sp ON (sp.id = st.id_price)
					WHERE (`id_order`='{$this->order_id}')");
				$it = 0;
				while ($res=se_db_fetch_assoc($query)){
					$LISTIT = $res_math[1];
					$it++;
					$LISTIT = str_replace("[SHOPLIST.ITEM]", $it, $LISTIT);
					if (!empty($res['nameitem'])) $res['name'] = $res['nameitem'];
					if (!empty($res['price'])) $res['price'] = se_MoneyConvert($res['price'], $this->ORDER['curr'], $this->curr);
					$res['discount'] = $discount = number_format(se_MoneyConvert($res['discount'], $this->ORDER['curr'], $this->curr),2, '.', '');
					$res['imgurl'] = '';
					if($res['img']){
					    $res['imgurl'] = /*'http://'.$_SERVER['HOST_NAME']*/_HOST_.'/images/'.$this->lang.'/shopprice/'.$res['img'];
					    $res['img'] = '<img src="'.$res['imgurl'].'" width="100">';
					}

					if (!empty($res['discount'])) 
					$res['fdiscount'] = se_formatMoney($discount, $this->curr);
					$res['fsumma'] = se_formatMoney(round($res['price'] - $discount, 2)*$res['count'], $this->curr);
					$res['fprice'] = se_formatMoney($res['price'], $this->curr);
					$res['summa'] = number_format(round($res['price'], 2) * $res['count'], 2, '.', '');
					$res['summa_total'] = number_format(round($res['price'] - $discount, 2) * $res['count'], 2, '.', '');
					//$res['price'] = se_formatMoney($res['price'];
					foreach ($res as $k => $v) {
						$LISTIT = str_replace("[SHOPLIST.".strtoupper($k)."]",  $v, $LISTIT);
					}
					$SHOPLIST .= $this->execute($LISTIT);
				}
				if ($this->ORDER['delivery_payee'] > 0) $it ++;

				$text = str_replace("[ORDER.ITEMCOUNT]", $it, $text);
				$text = str_replace($res_math[0], $SHOPLIST, $text);
			}	
			if (strpos($text, "[CONTRACT]") !== false) {
				$query = se_db_query("SELECT * FROM `shop_contract` WHERE id_order = {'$this->order_id'}");
				$contract = se_db_fetch_assoc($query);
				if (!empty($contract)) {
					$dcontr = explode('-',$contract['date']);
					$dcontr = $dcontr[1].$dcontr[2]. substr($dcontr[0], 2, 2).'/'.$contract['number'];
					$text = str_replace("[CONTRACT]", $dcontr, $text);
				}
			}
			
			//скидка по купону
			if (strpos($text, '[COUPON.') !== false) {
				$coupon_changes = array();
				$coupon = new seTable('shop_coupons_history', 'sch');
				$coupon->select('sch.id_coupon, sch.code_coupon, sch.discount, (SELECT sc.discount FROM shop_coupons sc WHERE sc.id=sch.id_coupon AND sc.type="p") AS percent');
				
				$coupon->where('id_order = ?', $this->order_id);
				$coupon->fetchOne();
				if ($coupon->isFind()) {
					$coupon_discount = round(se_MoneyConvert($coupon->discount, $this->ORDER['curr'], $this->curr), 2);
					$coupon_percent = $coupon->percent;
					if (empty($coupon_percent))
						$coupon_percent = $coupon_discount / ($summ - $delivery + $coupon_discount) * 100;
					
					$coupon_changes = array(
						'[COUPON.ID]' => $coupon->id_coupon,
						'[COUPON.CODE]' => $coupon->code_coupon,
						'[COUPON.DISCOUNT]' => se_formatMoney($coupon_discount, $this->curr),
						'[COUPON.PERCENT]' => round($coupon_percent, 2)
					);
				}
				if (!empty($coupon_changes))
					$text = strtr($text, $coupon_changes);
				$text = preg_replace('/\[COUPON\.(.+?)\]/i', '', $text);
			}

        }
		return $text;
	}
	
	private function parseUser($text){
		$array_change = array();
		$user = se_db_fields_item("person","id={$this->user_id}",
			"`reg_date` as `regdate`,`last_name` as `lastname`,`doc_ser`,`doc_num`,`doc_registr`, `addr` as `fizadres`,
			`first_name` as `firstname`,`sec_name` as `secname`, `id`, `email` as `useremail`, `phone`");
		if (!empty($user))
		foreach ($user as $k => $v){
			$text = str_replace('[USER.'.strtoupper($k).']', trim(stripslashes($v)), $text);
		}
	
		$text = str_replace(array('[CLIENTNAME]','[NAMECLIENT]', '[USERNAME]'),
			trim($user['lastname'].' '.$user['firstname'].' '.$user['secname']), $text);

		$query = se_db_query("SELECT `rekv_code`,`value` FROM user_rekv
			WHERE (id_author={$this->user_id}) AND (lang='{$this->lang}')");
		if (!empty($query))
		while ($line = se_db_fetch_assoc($query)){
			$text = str_replace('[USER.'.strtoupper($line['rekv_code']).']', $line['value'], $text);
		}
		unset($user);
		// Таблица user_urid
		$user = se_db_fields_item("user_urid","id={$this->user_id}", "company,director,posthead,bookkeeper,uradres,tel,fax");
		if (!empty($user)) 
			foreach ($user as $k => $v){ 
				$text = str_replace('[USER.'.strtoupper($k).']', stripslashes($v), $text);
			}
		if (strpos($text, '[USERLOGIN]') !== false ){
			$text = str_replace('[USERLOGIN]', se_db_fields_item('se_user', 'id='.$this->user_id, 'username'), $text);
		}
		if (strpos($text, '[MEMBER_HASH]') !== false ){
			$tmp_text = se_db_fields_item('se_user', 'id='.$this->user_id, 'username').se_db_fields_item('se_user', 'id='.$this->user_id, 'password').'USymlQpSK';
			$text = str_replace('[MEMBER_HASH]', md5($tmp_text), $text);
		}
							
		$text = preg_replace("/\[USER\.(.+?)\]/i", '', $text);
		return $text;
	}
	
	private function evalPhp($textphp){
			ob_start();
			eval($textphp);
			$result = ob_get_contents();
			ob_end_clean();
			return $result;
	}

	private function parseFunction($text){
		while (preg_match("/\bSUM\((.+?)\)/i", $text, $res_math)){
			$res_=explode(',', $res_math[1]);
			$sumres=0;
			if (!empty($res_))
			foreach($res_ as $sumres_) {
				$sumres += str_replace('"','',$sumres_);
			}
			if ($this->execute($res_[0]) == $this->execute($res_[1])) $res_=1; else $res_=0;
			$text = preg_replace("/\bSUM\((.+?)\)/i", $sumres, $text);
			//$text = $this->execute($text);
		}
		while (preg_match("/\[FORMATDATE\,([^\,]+?)\,(.+?)\]/s",$text, $res_math)){
			if (!empty($res_math[1])) {
			$res_ = explode('-', $res_math[1]);
			$res = str_replace("'",'',$res_math[2]);
			if (strpos($res,'ms')!==false) {
				$month= $this->getMonth(round($res_[1]));
				$res = str_replace('ms', $month, $res);
			}
			$res = str_replace(array('m','d','y','Y'), array($res_[1],$res_[2], substr($res_[0], 2, 2),$res_[0]), $res);
			} else $res = '';
			$text = str_replace($res_math[0], $res, $text);
		}

		while (preg_match("/\[STR_SUMM\,(.+?)\]/i",$text, $math)){
			$math[1] = str_replace("'", '', $math[1]);
			$text = preg_replace("/\[STR_SUMM\,(.+?)\]/i", $this->summtostring($math[1]), $text);
		}

		while (preg_match("/MD5\(\"(.+?)\"\)/iu", $text, $res_math)){
			$res_= $res_math[1];
			$text = str_replace($res_math[0], md5($this->execute($res_)), $text);
			//$text = $this->execute($text);
		}

		while (preg_match("/DECODE\(\"(.+?)\"\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0], $this->execute(urldecode($res_math[1])), $text);
			//$text = $this->execute($text);
		}

		while (preg_match("/DECODE_CP1251\(\"(.+?)\"\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0], iconv('CP1251','UTF-8', $this->execute(urldecode($res_math[1]))), $text);
			//$text = $this->execute($text);
		}


		while (preg_match("/BASE64ENCODE\((.+?)\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0],  base64_encode($res_math[1]), $text);
			//$text = $this->execute($text);
		}

		while (preg_match("/BASE64DECODE\((.+?)\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0],  $this->execute(base64_decode($res_math[1])), $text);
			//$text = $this->execute($text);
		}

		while (preg_match("/ENCODE\(\"(.+?)\"\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0],urlencode($res_math[1]), $text);
			//$text = $this->execute($text);
		}

		while (preg_match("/FNUM\((.+?)\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0],number_format($res_math[1], 2, '.', ''), $text);
			$text = $this->execute($text);
		}

		while (preg_match("/ENCODE_CP1251\(\"(.+?)\"\)/iu", $text, $res_math)){
			$text = str_replace($res_math[0],urlencode(iconv('UTF-8', 'CP1251', $res_math[1])), $text);
		}

		while (preg_match("/<noempty:([^>]+)?>(.*)<\/noempty>/iu", $text, $res_math)){
			if (!$res_math[1]) {
			    $res_math[2] = '';
			}
			$text = str_replace($res_math[0],$res_math[2], $text);
		}


		while (preg_match("/SAMETEXT\(\"(.+?)\"\)/i", $text, $res_math)){
			$res_=explode('","', $res_math[1]);
			if (mb_strtoupper($this->execute($res_[0]), 'UTF-8') == mb_strtoupper($this->execute($res_[1]), 'UTF-8')){
				$res_ = 1;
			} else {
				$res_ = 0;
			}
			$text = str_replace($res_math[0], $res_, $text);
			//$text = $this->execute($text);
		}


		while (strpos($text, '<php>')!==false){
			list(,$res) = explode('<php>', $text);
			if (strpos($res, '</php>')===false) break;
			list($res) = explode('</php>', $res);
			$res_ = $this->evalPhp($res);
			$text = str_replace('<php>'.$res.'</php>',$res_, $text);
		}
		while (preg_match("/\<\?php(.+?)\?\>/i", $text, $res_math)){
			$res_ = $this->evalPhp($res_math[1]);
			$text = str_replace($res_math[0], $res_, $text);
		}

		while (preg_match("/@if\((.*?)\)\{(.+?)\}/s",$text,$mach)) {
			if ((trim($mach[1])=='') or ($mach[1]=='0') or ($mach[1]=='false') or ($mach[1]=='no')) $mach[2]='';
			if (strpos($mach[1],'==')) { $rr=explode('==',$mach[1]); if ($rr[0]!=$rr[1]) $mach[2]=''; }
			if (strpos($mach[1],'!=')) { $rr=explode('!=',$mach[1]); if ($rr[0]==$rr[1]) $mach[2]=''; }
			$text= preg_replace("/@if\((.*?)\)\{(.+?)\}/s",$mach[2], $text);
		}
		while (preg_match("/@notif\((.*?)\)\{(.+?)\}/s",$text, $mach)){
			if ((trim($mach[1])!='') or ($mach[1]=='1') or ($mach[1]=='true') or ($mach[1]=='yes')) $mach[2]='';
			if (strpos($mach[1],'!=')) { $rr=explode('!=',$mach[1]); if ($rr[0]==$rr[1]) $mach[2]=''; }
			if (strpos($mach[1],'==')) { $rr=explode('==',$mach[1]); if ($rr[0]!=$rr[1]) $mach[2]=''; }
			$text = preg_replace("/@notif\((.*?)\)\{(.+?)\}/s",$mach[2], $text);
		}

		return $text;
	}
	
	private function parseCurr($text) {
		// Парсим пост выбора валюты с дефолными данными
		$res_ = '';
		while (preg_match("/\[POST\.(\w{1,}\:\w{1,})\]/i", $text, $res_math)){
			$res_ = $res_math[1];
			$def = explode(':', $res_);
			if (isset($_POST[strtolower($def[0])])){ 
				$res_ = htmlspecialchars(stripslashes(@$_POST[strtolower($def[0])]));
			} else if (!empty($def[1])) {
				$res_ = $def[1];
			}
			$text = str_replace($res_math[0], strtoupper($res_), $text);
		}
		// Парсим команду SELECTED
		while (preg_match("/\[SELECTED\:(\w{1,})\]/i", $text, $res_math)){
			if (strtolower($res_) == strtolower($res_math[1])){
				$text = str_replace($res_math[0],"selected", $text);
			} else {
				$text = str_replace($res_math[0], '', $text);
			}
		}
		// Парсим команду IF
		while (preg_match("/\[IF\((.+?)\)\]/m", $text, $res_math)){
			list($def, $res) = explode(':',$res_math[1]);
			$sel = explode(',',$def);
			foreach ($sel as $if) {
				$if = explode('=', $if);
				if (strtolower($res_) == strtolower($if[1])) $res=$if[0];
			}
			$text = str_replace($res_math[0], $res, $text);
		}

		// Парсим команду выбор валюты и запиь ее в сессию
		while (preg_match("/\[SETCURRENCY\:(\w{1,})\]/m", $text, $res_math)){
			if (isset($res_math[1])) {
				$this->curr = $res_math[1];
				$_SESSION['THISCURR'] = $this->curr;
			}
			$text = str_replace($res_math[0], '', $text);
		}
		// Парсим запросы
		while (preg_match("/\[POST\.(\w{1,})\]/i", $text, $res_math)){
			if (isset($_POST[$res_math[1]])){
				$res_ = htmlspecialchars(stripslashes($_POST[$res_math[1]])); 
			} else {
				$res_= '';
			}
			$text = str_replace($res_math[0], $res_, $text);
		}

		while (preg_match("/\[GET\.(\w{1,})\]/i", $text, $res_math)){
			$res_ = $res_math[1];
			if (isset($_GET[$res_math[1]])) {
				$res_ = htmlspecialchars(stripslashes($_GET[$res_math[1]]));
			} else $res_ = '';
			$text = str_replace($res_math[0], $res_, $text);
		}
		$text = str_replace('[CURDATE]', date('Y-m-d'), $text);
		return $text;
	}


	private function parsePayment($text) {
		if (strpos($text, '[PAYMENT.')!==false) {
			if ($this->payment_id){
				$fpid = se_db_fields_item("shop_payment","id={$this->payment_id}",'name_payment');
				$text = str_replace('[PAYMENT.NAME]', $fpid, $text);
			} else {
				$array_change['PAYMENT.NAME'] = 'Лицевой счет'; //Personal account';
			}
			$text = str_replace('[PAYMENT.CURR]', $this->curr, $text);
			$text = str_replace('[PAYMENT.ID]', $this->payment_id, $text);
			$query = se_db_query("select codename,value FROM bank_accounts WHERE id_payment IN (SELECT id FROM shop_payment WHERE shop_payment.lang='{$this->lang}');");
			if (!empty($query))
			while ($payment=se_db_fetch_assoc($query)){
				$text = str_replace('[PAYMENT.'.strtoupper($payment['codename']).']', $payment['value'], $text);
			}
		}
		return $text;
	}
	
	private function parseMain($text){
		if (!empty($this->main) && (strpos($text, '[ADMIN')!==false || strpos($text, '[MAIN.')!==false)) {
			foreach ($this->main as $k => $v){
				$v = trim($v);
				if ($k == 'esales'){
					$text = str_replace("[ADMIN_MAIL_SALES]", $v, $text);
				} else
				if ($k == 'esupport'){
					$text = str_replace("[ADMIN_MAIL_SUPPORT]", $v, $text);
				} 
				$k = strtoupper($k);
				$text = str_replace(array('[MAIN.'.$k.']', '[ADMIN_'.$k."]"),  $v, $text);
			}
		}
		return $text;
	}
	
	public function execute($text){
		// Парсим запросы и базовые функции валюты
		$text = $this->parseCurr($text);

		// Парсим заказы
		$text = $this->parseOrder($text);

		// Таблица MAIN
		$text = $this->parseMain($text);
		
		// Парсим пользовательские переменные
		if ($this->user_id) {
			$text = $this->parseUser($text);
		}

		// Парсим платежные системы
		$text = $this->parsePayment($text);
		// Парсим функции
		$text = $this->parseFunction($text);
		// Чистилка неопределенных запросов
		$text = preg_replace("/\[(.+?)\]/i","", $text);

		return $text;
	}
	
	public function getUserId(){
		return $this->user_id;
	}

	public function getCurr(){
		return $this->curr;
	}
	
	public function getSumm(){
		return $this->summ;
	}	
}