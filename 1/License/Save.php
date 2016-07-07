<?php

namespace Edgestile\Api\Model\Settings\License;
use Edgestile\Api\Model\ApiDatabaseModel;
use Edgestile\Framework\Register\Register;
use Edgestile\Api\Model\SEUtils;

class Data
{
    public $id;
}

class Save extends ApiDatabaseModel
{
    protected function process()
    {
        $state = new Register();

        $id = $this->state['id'];

        $u = $this->db->getQuery(array('shop_license', 'sl'));

        if ($id) {
            $u->where('id=?', $id);
            $u->find();
        }

        if (!empty($this->state['serial']))
            $u->__set('serial', $this->state['serial']);

        if (!empty($this->state['regKey']))
            $u->__set('regkey', $this->state['regKey']);

        if (!empty($this->state['dateReg']))
            $u->__set('datereg', $this->state['dateReg']);


        $id = (int) $u->save(true);

        if ($id) {
            $data = new Data();
            $data->id = (int) $id;
            $state->set('status','ok');
            $state->set('data', $data);
        }

        $this->state = $state;
    }
}