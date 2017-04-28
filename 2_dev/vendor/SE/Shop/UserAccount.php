<?php

namespace SE\Shop;

class UserAccount extends Base
{
    protected $tableName = "se_user_account";
    protected $groupBy = "p.id";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'p.id, sua.operation, SUM(IF(sua.in_payee IS NULL,0,sua.in_payee)) in_pay, SUM(IF(sua.out_payee IS NULL,0,sua.out_payee)) out_pay,
                (SUM(IF(sua.in_payee IS NULL,0,sua.in_payee)) - SUM(IF(sua.out_payee IS NULL,0,sua.out_payee))) balanse,
                CONCAT_WS(" ",p.last_name,p.first_name) as name',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'person p',
                    "condition" => 'p.id = sua.user_id'
                ),
            )
        );
    }

    public function fetch()
    {
        parent::fetch();
        foreach($this->result['items'] as $fld=>$item){
            $this->result['items'][$fld]['inPay'] = round($this->result['items'][$fld]['inPay']);
            $this->result['items'][$fld]['outPay'] = round($this->result['items'][$fld]['outPay']);
            $this->result['items'][$fld]['balanse'] = round($this->result['items'][$fld]['balanse']);
        }
    }

}