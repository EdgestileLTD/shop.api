<?php

namespace Edgestile\Api\Model\Settings\License;
use Edgestile\Api\Model\ApiDatabaseModel;
use Edgestile\Framework\Database\ConnectorInterface;
use Edgestile\Framework\Register\Register;

class License
{
    public $id;
    public $serial;
    public $regKey;
    public $dateReg;
}

class Info extends ApiDatabaseModel
{

    protected function process()
    {

        $state = new Register();

        $u = $this->db->getQuery(array('shop_license','sl'));
        $u->select('sl.*');
        $u->where('sl.id=?i',$this->state['id']);
        $u->find();

        if ($u->id) {
            $state->set('status','ok');

            $license = new License();
            $license->id = (int) $u->id;
            $license->serial = $u->serial;
            $license->regKey = $u->regkey;
            $license->dateReg = $u->datereg;
            $state->set('data', $license);
        }
        else
        {
            $state->set('status','error');
            $state->set('message','Invalid User Data');
        }

        $this->state = $state;
    }
}