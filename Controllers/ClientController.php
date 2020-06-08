<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 18.07.14
 * Time: 12:51
 */


class ClientController extends AbstractController
{
    public function indexAction()
    {
        $this->view->setVar("pageTitle", "CRM Clients");
        $params = $this->_getAllParams();
        $params['limit'] = isset($params['limit']) ? $params['limit'] : 15;
        $params['page'] = isset($params['page']) ? $params['page'] : 1;

        $db = Phalcon\DI::getDefault()->get('ssw_database');

        if (isset($params['package_id']) && $params['package_id'] && is_numeric($params['package_id'])) {
            $sql = "SELECT s1.client_id FROM shopify_ssw_subscription s1
LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
WHERE s2.subscription_id IS NULL AND s1.package_id = {$params['package_id']} AND s1.status <> 'pending'";
            $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
            $client_ids = array();
            foreach ($rows as $row) {
                $client_ids[] = $row['client_id'];
            }
            $params['sswclient_ids'] = $client_ids;
        }

        $items = Client::getClientList($params);
        $clientIds = [];
        foreach ($items as $item){
            $clientIds[] = $item->client_id;
        }
        $clientList = Client::getClientsByIds($clientIds, 'client_id');
        $paginator = new stdClass();
        $paginator->total_items = Client::getClientListTotal($params);
        $paginator->total_pages = ceil($paginator->total_items / $params['limit']);
        $paginator->current = $params['page'];
        $paginator->last = $paginator->total_pages;
        $paginator->items = $items;

        $sql = "SELECT package_id, title, app FROM shopify_ssw_package";
        $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
        $packages = array();
        foreach ($packageList as $package) {
            $packages[$package['package_id']] = array(
                'package_id' => $package['package_id'],
                'title' => $package['title'],
                'app' => $package['app']
            );
        }

        $this->view->setVars(array(
            'clients' => $paginator,
            'params' => $params,
            'clientList' => $clientList,
            'packages' => $packages
        ));

    }

    public function profileAction()
    {
        $client_id = $this->getParam('id');
        $client = Client::findFirst(['conditions' => "client_id = ?0", 'bind' => [$client_id]]);
        if (!$client) {
            return $this->response->redirect('/', true);
        }
        $this->view->setVar("pageTitle", "CRM Client - " . $client->name);
        $staffShops = Shopstaffs::getStaffShops($client_id);

        /*Добавил возможность увидеть количество ордеров в профиле ЭНТЕРПРАЙЗ клиента*/
        $count_orders=0;
        $viewer_id = Core::getViewerId();
        $sites = $client->getShops();
        foreach ($sites as $site) {
          $shop = Shops::findFirst('shop_id=' . $site->shop_id);
          $db = $this->di->get('ssw_database');
          $lastPlans = array();
          $sql = "SELECT s1.app, p.title FROM shopify_ssw_subscription s1
            LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
            INNER JOIN shopify_ssw_package p ON s1.package_id = p.package_id
            WHERE s1.status <> 'pending' AND s1.client_id = {$shop->sswclient_id}";
          $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
          foreach ($rows as $row) {
            $lastPlans[$row['app']] = $row['title'];
          }
          $plans = $lastPlans['default'];
          if (($viewer_id == '48' or $viewer_id == '63') and ($plans == 'Enterprise' or $plans == 'Growth')) {
            $shop_for_orders = Shops::findFirst('shop_id=' . $site->shop_id);
            $client_for_orders = SswClients::findFirst($shop_for_orders->sswclient_id);
            $db_id = $client_for_orders->db_id;
            $db = $this->di->get('ssw_database' . $db_id);
            try {
              $result = $db->fetchAll('SELECT COUNT(*) FROM ' . $shop_for_orders->sswclient_id . '_shopify_product_order WHERE financial_status="Paid"', Phalcon\Db::FETCH_ASSOC);
              $count_orders += $result[0]['COUNT(*)'];
            } catch (Exception $e) {}
          }
        }
        $this->view->setVar('count_orders', $count_orders);
        /*------------------------------------------------*/
        $tickets = Ticket::getTicketsPaginator(array('client_id' => $client_id, 'ipp' => 15, 'page' => $this->getParam('page', 1)));
        $this->view->setVar('tickets', $tickets);
        $this->view->setVar('staffShops', $staffShops);
        $this->view->setVar('client', $client);
        $config = $this->getDI()->get('config');
        $status = $client->sendDataToSendySync('api/subscribers/subscription-status.php', array(
            'api_key' => $config->sendy->api_key,
            'list_id' => $config->sendy->ssw_edu,
            'email' => $client->email
        ));

        $this->view->setVar('sendy_status', $status);
    }

    public function editAction()
    {
//    $this->checkAuth();

        $params = $this->dispatcher->getParams();
        if (!isset($params[0]) || !$params[0]) {
            return $this->response->redirect('/', true);
        }

        $client_id = $params[0];

        $client = Client::findFirst('client_id=' . $client_id);

        if (!$client) {
            return $this->response->redirect('/', true);
        }

        $shops = $client->getShops();

        $this->view->setVar('client', $client);
        $this->view->setVar('shops', $shops);

        if (!$this->request->isPost()) {
            return;
        }

        $data = $this->request->getPost();

        foreach ($data as $key => $value)
            $client->$key = $value;
        $client->save();

        if (isset($data['shops_type']) && is_array($data['shops_type'])) {
            //print_die($data['shops_type']);
            foreach ($data['shops_type'] as $shop_id => $type) {
                $shop_id = (int)$shop_id;
                $shopstaffs = Shopstaffs::findFirst("shop_id = '{$shop_id}' AND client_id = '{$client->client_id}'");
                if ($shopstaffs) {
                    $shopstaffs->type = $type;
                    $shopstaffs->save();
                }
            }
        }
        return $this->response->redirect('/client/' . $client_id, true);
    }

    public function deleteAction()
    {
        $params = $this->dispatcher->getParams();
        if (!isset($params[0]) || !$params[0]) {
            return $this->response->redirect('/', true);
        }

        $client_id = $params[0];

        $client = Client::findFirst('client_id=' . $client_id);

        if (!$client || $client->type == 'owner') {
            return $this->response->redirect('/client', true);
        }
        $client->delete();

        return $this->response->redirect('/client', true);

    }

    public function editDescAction()
    {
        if ($this->request->isPost()) {
            $clientId = $this->request->getPost('client_id', null, 0);
            $client = Client::findFirst('client_id = ' . $clientId);
            if ($client && $this->request->getPost('desc', null, false)) {
                $client->note = $this->request->getPost('desc');
                $client->save();
                exit(json_encode(true));
            }
        }
    }

    public function setFollowUpAction()
    {
        if ($this->request->isPost() && $this->request->getPost('client_id', null, false) && $this->request->getPost('days', null, false)) {
            $client = Client::findFirst('client_id = ' . $this->request->getPost('client_id'));
            if ($client) {
                $date = new DateTime();
                $date->add(new DateInterval('P' . ($this->request->getPost('days')) . 'D'));
                $client->follow_up = $date->format('Y-m-d');
                $client->follow_acted = 0;
                $client->follow_up_note = $this->request->getPost('note', null, '');
                $client->save();

                if ($this->request->getPost('render', null, false)) {
                    $this->view->client = $client;
                    exit($this->view->partial('partials/followup'));
                }
                exit(json_encode(true));
            }
        }
    }

    public function setActedAction()
    {
        if ($this->request->isPost() && $this->request->getPost('client_id', null, false)) {
            $client = Client::findFirst('client_id = ' . $this->request->getPost('client_id'));
            if ($client) {
                $client->follow_up = null;
                $client->follow_acted = null;
                $client->follow_up_note = '';
                $client->save();

                if ($this->request->getPost('render', null, false)) {
                    $this->view->client = $client;
                    exit($this->view->partial('partials/followup'));
                }
                exit(json_encode(true));
            }
        }
    }

    public function subscribeStatusAction()
    {
        $success = false;
        $response_status = false;
        $message = 'Invalid request method';
        if ($this->request->isPost()) {
            $email = $this->request->getPost('email', 'email');
            $subscribe_status = $this->request->getPost('status', 'int');
            $message = 'No email selected';
            if ($email) {
                /** @var Client $client */
                $client = Client::findFirst(['conditions' => "email = ?0", 'bind' => [$email]]);
                $config = $this->getDI()->get('config');
                $actions = [
                    'unsubscribe',
                    'subscribe'
                ];
                $message = 'No client found with this email';
                if ($client && isset($actions[$subscribe_status]) && $actions[$subscribe_status]) {
                    $response = $client->sendDataToSendySync($actions[$subscribe_status], array(
                        'api_key' => $config->sendy->api_key,
                        'list' => $config->sendy->ssw_edu,
                        'name' => $client->name,
                        'email' => $client->email,
                        'boolean' => "true"
                    ));
                    if ($response == 1) {
                        $message = ucfirst($actions[$subscribe_status]) . ' success!';
                        $response_status = ucfirst($actions[$subscribe_status]) . 'd';
                        $success = true;
                    } else {
                        $message = $response;
                    }
                }
            }
        }
        exit(json_encode([
            'success' => $success,
            'status' => $response_status,
            'message' => $message,
        ]));
    }

    /**
     * @throws \Phalcon\Mvc\Collection\Exception
     */
    public function oneTimeChargeAction()
    {
        $response = [
            'success' => false,
        ];
        if ($this->request->isPost()) {
            $charge = $this->request->getPost('charge');
            $discount_data = $this->request->getPost('discount');
            if ($discount_data && isset($discount_data['code']) && $charge && isset($charge['price'])) {
                try {
                    $total_months = ($discount_data['charge_months'] + $discount_data['free_months']);
                    $discount = new SswDiscount([
                        'code' => $discount_data['code'],
                        'trial_days' => $total_months == 12 ? 365 : round($total_months * 30.4375),
                        'app' => $charge['app'],
                        'expiration_date' => date('Y-m-d H:i:s', strtotime('+ 1 year')),
                        'min_package' => $charge['package_id'],
                        'count' => 1,
                        'note' => 'One time charge auto generated: ' . $charge['name']
                    ]);
                    if (!$discount->save()) {
                        throw  new \Exception($discount->getMessages()[0]->getMessage());
                    }
                    $response['discount'] = $discount->toArray();
                    $otc = new OneTimeCharges();
                    $otc->shop = $charge['shop'];
                    $otc->price = $charge['price'];
                    $otc->app = $charge['app'];
                    $otc->package_id = $charge['package_id'];
                    $otc->name = $charge['name'];
                    $otc->discount_code = $discount_data['code'];
                    $otc->save();
                    $response['charge'] = $otc->toArray();
                    $response['success'] = true;
                } catch (\Exception $e) {
                    $response['success'] = false;
                    $response['messsage'] = $e->getMessage();
                }
            }
        }
        exit(json_encode($response));
    }

}