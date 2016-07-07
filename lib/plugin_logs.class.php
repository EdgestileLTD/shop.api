<?php
/**
 *  Логирование
 */


class plugin_logs {
    private $serial = '';
    private $action = '';
    private $data = array();
    private $table_list = array(
        'person_change',
        'se_user',
        'se_group',
        'se_user_group'
    );

    public function __construct($method = '', $table = '', $data = array()){
        global $CONFIG, $json;

        //  проверка на пустоту входных данных
        if(empty($method) || empty($table) || empty($data)) return;

        //  проверка на нужную таблицу
        if(!in_array($table, $this->table_list)) return;

        //  серийник
        if(isset($CONFIG['DBSerial']))
            $this->serial = $CONFIG['DBSerial'];
        else if(isset($json->serial))
            $this->serial = $json->serial;
        else return;

        //  кому отдать на обработку
        switch($method) {
            case 'del': $this->delete($table, $data); break;
            case 'ins': $this->insert($table, $data); break;
            case 'upd': $this->update($table, $data); break;
            default: return;

        }

        if(!empty($this->action) && !empty($this->data))
            $this->logs_set();

        return ;
    }

    private  function logs_set() {
        $param = json_encode($this->data);
        $post_data = array (
            "serial" => $this->serial,
            "action" => $this->action,
            "data"   => $param,
        );
        if($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, 'http://upload.beget.edgestile.net/sendsay_set_log.php');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER,false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
            curl_exec($curl);
            curl_close($curl);
        }

        return;
    }

    private function delete($table, $data) {
        switch($table) {
            case 'se_user':
            case 'person':
                $this->action = 'user_delete';
                $this->data = array('id'=>$data);
                break;
            case 'se_group':
                $this->action = 'group_delete';
                $this->data = array('id'=>$data);
                break;
            case 'se_user_group':
                $this->action = 'user_group_delete';
                $this->data = $data;
                break;
            default:
                $this->action = '';
                $this->data = '';
        }

        return ;
    }

    private function insert($table, $data) {
        switch($table) {
            case 'se_user_group':
                $this->action = 'user_group_insert';
                $this->data = $data;
                break;
            case 'se_user':
                $this->action = 'user_insert';
                $this->data = array('id' => $data);
                break;
            default:
                $this->action = '';
                $this->data = '';
        }

        return ;
    }

    private function update($table, $data) {
        switch($table) {
            case 'person_change':
                $this->action = 'user_update';
                $this->data = array('id' => $data, 'field' => 'email');
                break;
            default:
                $this->action = '';
                $this->data = '';
        }

        return ;
    }

}