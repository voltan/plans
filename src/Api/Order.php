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
namespace Module\Plans\Api;

use Pi;
use Pi\Application\Api\AbstractApi;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Json\Json;

/*
 * Pi::api('order', 'plans')->getProductDetails($product);
 * Pi::api('order', 'plans')->postPaymentUpdate($order, $basket);
 * Pi::api('order', 'plans')->getOrder($id);
 * Pi::api('order', 'plans')->canonizeOrder($order);
 */

class Order extends AbstractApi
{
    public function getProductDetails($product)
    {
        return Pi::api('plans', 'plans')->getPlan($product);
    }

    public function postPaymentUpdate($order, $basket)
    {
        // Update time
        if ($order['module_item'] > 0) {
            // Get plan
            $plan = Pi::api('plans', 'plans')->getPlan($order['module_item']);
            $setting = Json::decode($plan['setting'], true);
            // Save order
            $values = array(
                'uid' => $order['uid'],
                'plan' => $plan['id'],
                'order_id' => $order['id'],
                'price' => $order['product_price'],
                'vat'=> $order['vat_price'],
                'total'=> $order['total_price'],
                'time_order' => time(),
                'time_start' => time(),
                'time_end' => time() + ($plan['time_period'] * 86400),
                'status' => 1,
                'extra' => Json::encode($setting['action']),
            );
            $row = Pi::model('order', $this->getModule())->createRow();
            $row->assign($values);
            $row->save();
            // Set url
            $url = Pi::url(Pi::service('url')->assemble('order', array(
                'module' => 'order',
                'controller' => 'detail',
                'action' => 'index',
                'id' => $order['id'],
            )));
            // Update by type
            switch ($plan['type']) {
                case 'credit':
                    // Check user module
                    if (Pi::service('module')->isActive('user')) {
                        // Get user
                        $user = Pi::user()->get($order['uid'], array('id', 'credit'));
                        // Update user credit
                        $credit = $user['credit'] + $plan['price'];
                        Pi::model('profile', 'user')->update(
                            array('credit' => $credit),
                            array('uid' => $order['uid'])
                        );
                    }
                    break;

                case 'role':
                    // Update role
                    Pi::service('user')->setRole($order['uid'], $plan['role']);
                    // Set url
                    $url = Pi::url(Pi::service('url')->assemble('plans', array(
                        'module' => $this->getModule(),
                        'controller' => 'order',
                        'action' => 'finish',
                        'id' => $row->id,
                    )));
                    break;

                case 'module':
                    if (!empty($plan['module'])) {
                        switch ($plan['module']) {
                            case 'video':
                                $access = array(
                                    'time_start' => time(),
                                    'time_end' => intval(time() + ($plan['time_period'] * 24 * 60 * 60)),
                                    'item_key' => sprintf('video-package-%s-%s', $plan['id'], $order['uid']),
                                    'order' => $order['id'],
                                    'status' => 1,
                                );
                                Pi::api('access', 'order')->setAccess($access);
                                break;

                            case 'guide':

                                break;
                        }
                    }
                    break;
            }
            // Send notification
            //Pi::api('notification', 'plans')->newPlan($order, $plan);
            // Set back url
            return $url;
        }
    }

    public function getOrder($parameter, $field = 'id')
    {
        // Check for order module request
        if ($field == 'order') {
            $field = 'order_id';
        }
        // Get order
        $order = Pi::model('order', $this->getModule())->find($parameter, $field);
        $order = $this->canonizeOrder($order);
        return $order;
    }

    public function canonizeOrder($order)
    {
        // Check
        if (empty($order)) {
            return '';
        }
        // Order to array
        $order = $order->toArray();
        // Set time
        $order['time_order_view'] = _date($order['time_order']);
        $order['time_start_view'] = _date($order['time_start']);
        $order['time_end_view'] = _date($order['time_end']);
        // Set price
        if (Pi::service('module')->isActive('order')) {
            $order['price_view'] = Pi::api('api', 'order')->viewPrice($order['price']);
            $order['vat_view'] = Pi::api('api', 'order')->viewPrice($order['vat']);
            $order['total_view'] = Pi::api('api', 'order')->viewPrice($order['total']);
        } else {
            $order['price_view'] = _currency($order['price']);
            $order['vat_view'] = _currency($order['vat']);
            $order['total_view'] = _currency($order['total']);
        }
        // return
        return $order;
    }
}