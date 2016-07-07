<?php

namespace Edgestile\Api\Model\Settings\License;
use Edgestile\Api\Model\ApiDatabaseModel;
use Edgestile\Framework\Register\Register;

class Delete extends ApiDatabaseModel
{
    protected function process()
    {
        $state = new Register();

        $u = $this->db->getQuery(array('shop_license','sl'));
        $ids = $this->state['ids'];
        if ($ids) {
            $u->where('id in (?)', $ids);
            $u->delete();
            $state->set('status','ok');
        }

        $this->state = $state;
    }
}