<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */

namespace Module\Plans\Controller\Admin;

use Pi;
use Pi\Mvc\Controller\ActionController;

class OrderController extends ActionController
{
    public function indexAction()
    {
        // Get list of plans
        $plans = Pi::api('plans', 'plans')->getPlansLight();
        // Get info
        $list = array();
        $order = array('id DESC');
        $select = $this->getModel('order')->select()->order($order);
        $rowset = $this->getModel('order')->selectWith($select);
        // Make list
        foreach ($rowset as $row) {
            $list[$row->id] = Pi::api('order', 'plans')->canonizeOrder($row);
            $list[$row->id]['user'] = Pi::user()->get($row->uid, array('id', 'identity', 'name', 'email'));
            $list[$row->id]['plan'] = $plans[$row->plan];
        }
        // Set view
        $this->view()->setTemplate('order-index');
        $this->view()->assign('list', $list);
    }
}