<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 19.07.14
 * Time: 16:01
 */

require_once(__DIR__ . '/../library/htmlSql/snoopy.class.php');
require_once(__DIR__ . '/../library/htmlSql/htmlsql.class.php');

class ShopifyAppsController extends AbstractController {

  public function indexAction()
  {
    $full_info = isset($_GET['full_info']);

    date_default_timezone_set("Asia/Bishkek");
    $this->view->setVar("pageTitle", "CRM Shopify Apps");

    $apps = Shopifyapplications::find();
    $appList = array();
    foreach ($apps as $app) {
      $appList[$app->getIdentity()] = $app->toArray();
    }

    $rates = Shopifyapplicationrates::find(array("order" => "updated_time ASC, position ASC"));
    $rateList = array();
    foreach ($rates as $rate) {
      $rateList[$rate->updated_time][$rate->app_id] = $rate->toArray();
    }

    $timeList = array_keys($rateList);
    if (!$full_info) {
      $timeList = array_slice($timeList, -8, 8);
    }

    $this->view->setVar('appList', $appList);
    $this->view->setVar('rateList', $rateList);
    $this->view->setVar('timeList', $timeList);
  }

  public function updateAction()
  {
    $this->_checkApps();

    $this->view->disable();
    return $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'shopify-apps', 'action' => 'index')), true);
  }

  public function statsAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $params['group'] = 'day';
    $params['period_select'] = '';
    $params['group_by_shop'] = 0;
    $params['type'] = 0;

    if (isset($_POST['period_start'])) {
      $params['start'] = $_POST['period_start'];
      $params['end'] = $_POST['period_end'];
      $params['group'] = $_POST['group'];
      $params['period_select'] = $_POST['period_select'];
      $params['group_by_shop'] = $_POST['group_by_shop'];
      $params['type'] = 'page_views';
    } else {
      $params['start'] = date("Y-m-d 00:00:00", strtotime("-1 month"));
      $params['end'] = date("Y-m-d H:i:s", gmmktime(gmdate("G"), 0, 0));
    }

    if ($params['group'] == 'day') {
      $format = "d M";
    } else if ($params['group'] == 'week') {
      $format = "d M";
    } else {
      $format = "M Y";
    }

    $installations = SswEvents::getEvents($params, 'install');
    $fresh_shops = SswEvents::getEvents($params, 'install', true);
    $uninstalls = SswEvents::getEvents($params, 'uninstall');
    $uniq_uninstalls = SswEvents::getEvents($params, 'uninstall', true);
    $our_uninstalls = SswEvents::getEvents($params, 'uninstall', true, 1);

    $data = array();
    $data[] = array('Time', 'Installations', 'Unique Installations', 'Uninstalls', 'Unique Uninstalls', 'Our Removes');

    $period_start = '';
    $period_end = '';
    foreach ($installations as $key => $site) {
      $period_start = ($period_start == '') ? $site->date : $period_start;
      $period_end = $site->date;

      $data[] = array(
        date($format, strtotime($site->date)),
        (int)$site->count,
        (isset($fresh_shops[$key]->count) ? (int)$fresh_shops[$key]->count : 0),
        (isset($uninstalls[$key]->count) ? (int)$uninstalls[$key]->count : 0),
        (isset($uniq_uninstalls[$key]->count) ? (int)$uniq_uninstalls[$key]->count : 0),
        (isset($our_uninstalls[$key]->count) ? (int)$our_uninstalls[$key]->count : 0)
      );
    }

    $total_installations = SswEvents::getTotal($params, 'install');
    $total_uninstalls = SswEvents::getTotal($params, 'uninstall');
//    $total_removes = SswEvents::getTotal($params, 'uninstall', 1);

    $total_data = array(array('Event', 'Count'));
    $total_data[] = array('Installations', (int)$total_installations);
    $total_data[] = array('Uninstalls', (int)$total_uninstalls);
//    $total_data[] = array('Our Removes', (int)$total_removes);

    $this->view->setVar('period_start', $period_start);
    $this->view->setVar('period_end', $period_end);
    $this->view->setVar('group', $params['group']);
    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('data_js', json_encode($data));
    $this->view->setVar('total_data_js', json_encode($total_data));
  }

  public function shopsAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $params['group'] = 'day';
    $type = 'page_views';
    $revenue = 'hide';
    $params['period_select'] = '';
    $params['start'] = date("Y-m-d", strtotime('-1 month'));
    $params['end'] = date("Y-m-d", time());

    $ranks_start = isset($_REQUEST['rank_start']) ? $_REQUEST['rank_start'] : array();
    $ranks_end = isset($_REQUEST['rank_end']) ? $_REQUEST['rank_end'] : array();
    $ranks_price = isset($_REQUEST['rank_price']) ? $_REQUEST['rank_price'] : array();

    if (isset($_REQUEST['period_start'])) {
      $params['start'] = $_REQUEST['period_start'];
      $params['end'] = $_REQUEST['period_end'];
      $type = $_REQUEST['type'];
      $revenue = $_REQUEST['revenue'];
      $params['group'] = $_REQUEST['group'];
      $params['period_select'] = $_REQUEST['period_select'];
    }

    $shopCountList = array();
    $totalSum = 0;
    $shop_ids = array();
    foreach ($ranks_start as $key => $start) {
      $end = $ranks_end[$key];
      $price = $ranks_price[$key];
      if ($type == 'page_views') {
        $shops = SswPageviews::getShopCount($params, $start, $end);
      } else {
        $shops = SswVisitors::getShopCount($params, $start, $end);
      }
      $shopList = $shops->toArray();

      foreach ($shopList as $shop) {
        $shop_ids[] = $shop['client_id'];
      }

      $rankPrice = count($shopList) * $price;
      $totalSum += $rankPrice;
      $shopCountList[] = array('start' => $start, 'end' => $end, 'price' => $price, 'total' => $rankPrice, 'shops' => $shopList);
    }

    $shopsRevenue = array();
    if ($revenue == 'show' && $shop_ids) {
      $query = SswClients::query();
      $query->columns(array("client_id", "db_id", "domain"));
      $query->inWhere("client_id", $shop_ids);
      $query->orderBy("db_id");

      $shopIdsDbs = $query->execute()->toArray();
      $shopsRevenue = array();
      foreach ($shopIdsDbs as $shopIdDb) {
        $orders = new SswOrders();
        $db_source = ($shopIdDb['db_id'] == 1) ? 'ssw_database' : 'ssw_database' . $shopIdDb['db_id'];
        $mgr = $orders->getModelsManager();
        $mgr->__destruct();
        $mgr->setModelSource($orders, $db_source);
        $orders->setClientSource($shopIdDb['client_id'], $shopIdDb['db_id']);
        $orders->initialize($shopIdDb['client_id'], $shopIdDb['db_id']);

        $revenue_data = $orders->getClientTotalRevenue($params);
        $revenue_data['shop'] = $shopIdDb['domain'];
        $shopsRevenue[$shopIdDb['client_id']] = $revenue_data;
      }

    }

    $this->view->setVar('shopCountList', $shopCountList);
    $this->view->setVar('group', $params['group']);
    $this->view->setVar('type', $type);
    $this->view->setVar('revenue', $revenue);
    $this->view->setVar('totalSum', $totalSum);
    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('period_start', $params['start']);
    $this->view->setVar('period_end', $params['end']);
    $this->view->setVar('shopsRevenue', $shopsRevenue);
  }

  public function shopEmailsAction()
  {
    $client_ids = array(448,478,807,861,1018,1024,1081,1192,1485,1605,1610,1661,1662,1720,1724);
    $client_ids_str = '448,478,807,861,1018,1024,1081,1192,1485,1605,1610,1661,1662,1720,1724';
    $sql = "
      SELECT
        c.client_id, c.shop, c.email, c.client_id, c.new, c.status, c.db_id, c.admin_deleted, c.package_id, c.ssw,
        d.status AS default_app, i.status AS instagram_app
      FROM shopify_ssw_client AS c
      LEFT JOIN shopify_ssw_clientapp AS d
        ON (c.client_id = d.client_id AND d.app = 'default')
      LEFT JOIN shopify_ssw_clientapp AS i
        ON (c.client_id = i.client_id AND i.app = 'instagram')
      WHERE c.client_id IN ({$client_ids_str})
      ORDER BY c.client_id
    ";

    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $db = $this->getDI()->get('ssw_database');
    $shops = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
    print_die($shops);
    return;
    $client_ids_str = '807, 861, 1018, 1024, 1081, 1192, 1661, 1662';
    $clients = SswClients::find("client_id IN ({$client_ids_str})");

    $current_db = 0;
    $result = array();
    $cool = array();
    foreach ($clients as $client) {
      $client = $client->toArray();
      if ($current_db != $client['db_id']) {
        $database = ($client['db_id'] == 1) ? 'ssw_database' : 'ssw_database' . $client['db_id'];
        /**
         * @var Phalcon\Db\Adapter\Pdo\Mysql $db
         */
        $db = $this->getDI()->get($database);
      }

      $sql = "SELECT * FROM {$client['client_id']}_shopify_core_task WHERE `method` = 'checkSubscriptionChargeStatus'";
      $data = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
      if (!$data) {
        $upgrade_to_19 = "
INSERT IGNORE INTO `#SHOP_ID#_shopify_core_task` (`class`, `method`, `execute_interval`, `executed_date`, `execute_status`, `params`, `status`) VALUES ('Task', 'checkSubscriptionChargeStatus', 604800, 0, 'always', NULL, 'progress');

 ";

        $sql = str_replace('#SHOP_ID#', $client['client_id'], $upgrade_to_19);
        $db->query($sql);
        $result[] = $client['client_id'];
      } else {
        $cool[] = $client['client_id'];
      }
    }
    print_arr($result);
    print_arr($cool);
    print_die(1);
  }

  public function totalAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $params['group'] = 'day';
    $params['period_select'] = '';
    $params['start'] = date("Y-m-d", strtotime('-1 month'));
    $params['end'] = date("Y-m-d", time());

    if (isset($_REQUEST['period_start'])) {
      $params['start'] = $_REQUEST['period_start'];
      $params['end'] = $_REQUEST['period_end'];
      $params['group'] = $_REQUEST['group'];
      $params['period_select'] = $_REQUEST['period_select'];
    }

    $views = SswPageviews::getTotal($params);
    $visits = SswVisitors::getTotal($params);

    $viewList = array();
    foreach ($views as $view) {
      $viewList[$view->date] = (int)$view->rows_count;
    }

    $visitList = array();
    foreach ($visits as $visit) {
      $visitList[$visit->date] = (int)$visit->rows_count;
    }

    $data = array();
    $data[] = array('Date', 'Page Views', 'Visits');
    foreach ($viewList as $date => $view_count) {
      $visit_count = isset($visitList[$date]) ? $visitList[$date] : 0;
      $data[] = array($date, $view_count, $visit_count);
    }

    $this->view->setVar('group', $params['group']);
    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('period_start', $params['start']);
    $this->view->setVar('period_end', $params['end']);
    $this->view->setVar('data_js', json_encode($data));
  }

  public function totalShopsAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $params['group'] = 'day';
    $params['period_select'] = '';
    $params['start'] = date("Y-m-d", strtotime('-1 month'));
    $params['end'] = date("Y-m-d", time());
    $params['limit'] = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 5;

    if (isset($_REQUEST['period_start'])) {
      $params['start'] = $_REQUEST['period_start'];
      $params['end'] = $_REQUEST['period_end'];
      $params['group'] = $_REQUEST['group'];
      $params['period_select'] = $_REQUEST['period_select'];
    }

    $viewed_ids = SswPageviews::mostActiveShopIds($params);
    $views = SswPageviews::getPopularShops($viewed_ids, $params);

    $viewList = array();
    foreach ($views as $view) {
      $viewList[$view->date][$view->client_id] = (int)$view->rows_count;
    }

    $viewData = array(array('Date'));
    foreach ($viewed_ids as $shop_id) {
      $item = SswClients::findFirst($shop_id);
      $viewData[0][] = '#' . $shop_id . ' ' . $item->domain;
    }

    foreach ($viewList as $date => $viewInfo) {
      $item = array($date);
      foreach ($viewed_ids as $shop_id) {
        $item[] = isset($viewInfo[$shop_id]) ? $viewInfo[$shop_id] : 0;
      }
      $viewData[] = $item;
    }

    $visited_ids = SswVisitors::mostActiveShopIds($params);
    $visits = SswVisitors::getPopularShops($visited_ids, $params);

    $visitList = array();
    foreach ($visits as $visit) {
      $visitList[$visit->date][$visit->client_id] = (int)$visit->rows_count;
    }

    $visitData = array(array('Date'));
    foreach ($visited_ids as $shop_id) {
      $item = SswClients::findFirst($shop_id);
      $visitData[0][] = '#' . $shop_id . ' ' . $item->domain;
    }

    foreach ($visitList as $date => $visitInfo) {
      $item = array($date);
      foreach ($visited_ids as $shop_id) {
        $item[] = isset($visitInfo[$shop_id]) ? $visitInfo[$shop_id] : 0;
      }
      $visitData[] = $item;
    }

    $this->view->setVar('group', $params['group']);
    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('period_start', $params['start']);
    $this->view->setVar('period_end', $params['end']);
    $this->view->setVar('views_js', json_encode($viewData));
    $this->view->setVar('visits_js', json_encode($visitData));
  }

  public function installsAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $params['period_select'] = '';
    $params['start'] = date("Y-m-d", strtotime('-1 month'));
    $params['end'] = date("Y-m-d", time());

    if (isset($_REQUEST['period_select'])) {
      $params['start'] = $_REQUEST['period_start'];
      $params['end'] = $_REQUEST['period_end'];
      $params['period_select'] = $_REQUEST['period_select'];
    }

    $activeShops = SswEvents::getInstalledShops($params);

    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('period_start', $params['start']);
    $this->view->setVar('period_end', $params['end']);
    $this->view->setVar('activeShops', $activeShops);
  }

  public function infoAction()
  {
    date_default_timezone_set("Asia/Bishkek");
    $params = array();
    $type = 'page_views';
    $params['group'] = 'day';
    $revenue = 'hide';
    $params['period_select'] = '';
    $params['start'] = date("Y-m-d", strtotime('-1 month'));
    $params['end'] = date("Y-m-d", time());

    $shop_id = $this->getParam(0, 36);
    $shop = SswClients::findFirst($shop_id);

    if (isset($_POST['period_start'])) {
      $params['start'] = $_POST['period_start'];
      $params['end'] = $_POST['period_end'];
      $type = $_POST['type'];
      $revenue = isset($_POST['revenue']) ? $_POST['revenue'] : $revenue;
      $params['group'] = $_POST['group'];
      $params['period_select'] = $_POST['period_select'];
    }

    if ($params['group'] == 'month') {
      $group_sql = "DATE_FORMAT(date, '%Y %m') AS page";
    } else if ($params['group'] == 'week') {
      $group_sql = 'YEARWEEK(date) AS page';
    } else {
      $group_sql = 'date AS page';
    }

    $conditions = "client_id = {$shop_id}";
    if (isset($params['period_select']) && $params['period_select'] != '') {
      if ($params['period_select'] == 'day') {
        $conditions .= " AND date = CURRENT_DATE()";
      } else if ($params['period_select'] == 'week') {
        $conditions .= " AND YEARWEEK(date) = YEARWEEK(NOW())";
      } else if ($params['period_select'] == 'month') {
        $conditions .= " AND DATE_FORMAT(date, '%Y %m') = DATE_FORMAT(NOW(), '%Y %m')";
      } else if ($params['period_select'] == 'year') {
        $conditions .= " AND YEAR(date) = YEAR(NOW())";
      } else if ($params['period_select'] == 'prev_week') {
        $conditions .= " AND YEARWEEK(date) = '" . date("oW", strtotime("-2 week")) . "'";
      } else if ($params['period_select'] == 'prev_month') {
        $conditions .= " AND DATE_FORMAT(date, '%Y %m') = '" . date("o m", strtotime("-1 month")) . "'";
      } else if ($params['period_select'] == 'two_month_ago') {
        $conditions .= " AND DATE_FORMAT(date, '%Y %m') = '" . date("o m", strtotime("-2 month")) . "'";
      } else if ($params['period_select'] == 'three_month_ago') {
        $conditions .= " AND DATE_FORMAT(date, '%Y %m') = '" . date("o m", strtotime("-3 month")) . "'";
      }
    } else {
      $conditions .= " AND date BETWEEN '{$params['start']}' AND '{$params['end']}'";
    }

    if ($type == 'page_views') {
      $events = SswPageviews::find(array($conditions, "order" => "date", "columns" => 'SUM(views_num) AS rows_count, date, ' . $group_sql, "group" => "page"));
    } else {
      $events = SswVisitors::find(array($conditions, "order" => "date", "columns" => 'SUM(visitors_num) AS rows_count, date, ' . $group_sql, "group" => "page"));
    }

    $orders = new SswOrders();
    $db_source = ($shop->db_id == 1) ? 'ssw_database' : 'ssw_database' . $shop->db_id;
    $mgr = $orders->getModelsManager();
    $mgr->__destruct();
    $mgr->setModelSource($orders, $db_source);
    $orders->setClientSource($shop_id, $shop->db_id);
    $orders->initialize($shop_id, $shop->db_id);

    $revenue_data = $orders->getClientTotalRevenue($params);
    if ($revenue == 'show') {
      $orderList = $orders->getClientRevenueList($params);
      $ordersPages = array();
      foreach ($orderList as $item) {
        $ordersPages[$item['page']] = $item;
      }
    }

    $shop_data = array();
    $shop_data[] = ($revenue == 'show') ? array('Time', 'Count', 'Orders', 'Revenue') : array('Time', 'Count');
    $all_data = 0;
    foreach ($events as $key => $event) {
      $count = $event->rows_count;
      $point_info = array($event->page, (int)$count);
      if ($revenue == 'show') {
        $point_info[] = isset($ordersPages[$event->page]['order_count']) ? (int)$ordersPages[$event->page]['order_count'] : 0;
        $point_info[] = isset($ordersPages[$event->page]['total_revenue']) ? (int)$ordersPages[$event->page]['total_revenue'] : 0;
      }

      $shop_data[] = $point_info;
      $all_data += (int)$count;
    }


    $this->view->setVar('data_js', json_encode($shop_data));
    $this->view->setVar('group', $params['group']);
    $this->view->setVar('type', $type);
    $this->view->setVar('shop', $shop);
    $this->view->setVar('all_data', $all_data);
    $this->view->setVar('period_select', $params['period_select']);
    $this->view->setVar('group_by_shop', $params['group_by_shop']);
    $this->view->setVar('period_start', $params['start']);
    $this->view->setVar('period_end', $params['end']);
    $this->view->setVar('revenue', $revenue);
    $this->view->setVar('revenue_data', $revenue_data);
  }

  public function eventAction()
  {
    $limit_count = 10;
    $limit_page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
    $limit_start = ($limit_page - 1) * $limit_count;

    date_default_timezone_set("Asia/Bishkek");
    $this->view->setVar("pageTitle", "CRM Clients");

    $shop_id = $this->getParam('shop', 0);
    $period_start = date("Y-m-d", strtotime('-1 month'));
    $period_end = date("Y-m-d", time());

    if (isset($_POST['period_start'])) {
      $period_start = $_POST['period_start'];
      $period_end = $_POST['period_end'];
    }

    if ($shop_id) {
      $shop_url = str_replace(array('https://', 'http://'), '', $shop_id);
      $shop_url = str_replace('/', '', $shop_url);
      $where_client = is_numeric($shop_id)
        ? 'WHERE c.client_id = ' . $shop_id
        : "WHERE c.shop LIKE '%{$shop_url}%' OR c.domain LIKE '%{$shop_url}%'";
    } else {
      $where_client = "WHERE c.status = 1 AND c.email != 'farside312@gmail.com' AND c.email != 'ermekcs@gmail.com'";
    }

    //social_login - login

    $query = "SELECT
  c.client_id, c.shop, c.db_id,
  logins.event_count AS logins_events, logins.users_count AS logins_users,
  invalid_logins.event_count AS invalid_events, invalid_logins.users_count AS invalid_users,
  faves.event_count AS faves_events, faves.users_count AS faves_users,
  outgoing.event_count AS out_events, outgoing.users_count AS out_users,
  posting.event_count AS post_events, posting.users_count AS post_users
FROM shopify_ssw_client AS c

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
		WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('fave_button', 'fave_icon')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS faves
	ON (c.client_id = faves.client_id)

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('email_login', 'social_login')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS logins
	ON (c.client_id = logins.client_id)

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('invalid_email_login')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS invalid_logins
	ON (c.client_id = invalid_logins.client_id)

/*LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('login_popup_view', 'signup_popup_view')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS popups
	ON (c.client_id = popups.client_id)*/

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('ask_friends', 'invite_via_email', 'invite_via_facebook', 'media_share', 'product_share')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS outgoing
	ON (c.client_id = outgoing.client_id)

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('comment', 'feed_post')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS posting
	ON (c.client_id = posting.client_id)

{$where_client}

ORDER BY logins.event_count DESC LIMIT {$limit_start}, {$limit_count}";

    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $db = $this->getDI()->get('ssw_database');
    $shopList = $db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);

    $shopIds = array();
    foreach($shopList as $shopData) {
      $shopIds[$shopData['db_id']][] = $shopData['client_id'];
    }

    $current_db = 0;
    $addInfo = array();
    foreach ($shopIds as $db_id => $ids) {
      if ($current_db != $db_id) {
        $database = ($db_id == 1) ? 'ssw_database' : 'ssw_database' . $db_id;
        $db = $this->getDI()->get($database);
      }

      $period_end_ts = time();
      $period_start_ts = strtotime('-1 month');

      foreach ($ids as $client_id) {
        $sql = "SELECT COUNT(*) AS `total` FROM {$client_id}_shopify_product_cart AS c
INNER JOIN {$client_id}_shopify_statistics_users AS u
	ON (c.hash_key = u.hash_key and (u.our = 1 OR c.created_at <= u.expiration))
WHERE c.created_at BETWEEN {$period_start_ts} AND {$period_end_ts};";

        $row = $db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
        $addInfo[$client_id]['cart_our'] = $row['total'];

        $sql = "SELECT COUNT(*) AS `total` FROM {$client_id}_shopify_product_cart AS c
        WHERE c.created_at BETWEEN {$period_start_ts} AND {$period_end_ts};";

        $row = $db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
        $addInfo[$client_id]['cart_all'] = $row['total'];

        $sql = "SELECT SUM(total_price_usd) AS `total` FROM {$client_id}_shopify_product_order
WHERE our = 1 AND financial_status = 'paid' AND fulfillment_status = 'fulfilled'
AND created_at BETWEEN '{$period_start}' AND '{$period_end}';";

        $row = $db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
        $addInfo[$client_id]['earned_our'] = round($row['total'], 2);

        $sql = "SELECT SUM(total_price_usd) AS `total` FROM {$client_id}_shopify_product_order
WHERE financial_status = 'paid' AND fulfillment_status = 'fulfilled'
AND created_at BETWEEN '{$period_start}' AND '{$period_end}';";

        $row = $db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
        $addInfo[$client_id]['earned_all'] = round($row['total'], 2);
      }
    }

    $this->view->setVar('addInfo', $addInfo);
    $this->view->setVar('shopList', $shopList);
    $this->view->setVar('period_start', $period_start);
    $this->view->setVar('period_end', $period_end);
  }

  public function reportsAction()
  {
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $last_login_count = isset($_REQUEST['last_login_count']) ? $_REQUEST['last_login_count'] : -1;
    $last_client_id = isset($_REQUEST['last_client_id']) ? $_REQUEST['last_client_id'] : 0;
    $isPartially = isset($_REQUEST['isPartially']) ? $_REQUEST['isPartially'] : 0;
    date_default_timezone_set("Asia/Bishkek");
    $this->view->setVar("pageTitle", "CRM Weekly Reports");

    $period_start = isset($_REQUEST['period_start']) ? $_REQUEST['period_start'] : date("Y-m-d", strtotime('-13 days'));
    $period_end = isset($_REQUEST['period_end']) ? $_REQUEST['period_end'] : date("Y-m-d", time());
    $period_start_timestamp = strtotime($period_start);
    $period_end_timestamp = strtotime($period_end);
    $package_id = isset($_REQUEST['package_id']) ? $_REQUEST['package_id'] : -1;
    $keyword = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';
    $display_mode = isset($_REQUEST['display_mode']) ? $_REQUEST['display_mode'] : 'all';


    $db = $this->getDI()->get('ssw_database');
    $client_db = $db;
    $packageList = $db->fetchAll("SELECT package_id, title FROM shopify_ssw_package WHERE app = 'default'");
    $packages = array('Free');
    foreach($packageList as $package){
      $packages[$package['package_id']] = $package['title'];
    }

//    $where_client = "WHERE c.status = 1 AND c.ssw=1 AND c.admin_deleted = 0 AND c.email != 'farside312@gmail.com' AND c.email != 'ermekcs@gmail.com'";
    $where_client = "WHERE c.status = 1 AND c.ssw=1 AND c.email NOT IN ('farside312@gmail.com', 'ulanproger@gmail.com', 'burya1988@gmail.com', 'ermekcs@gmail.com', 'eldarbox@gmail.com', 'test.assurence@gmail.com')";
    if($package_id != -1){
      if($package_id == 0){
        // All Plans
        $where_client .= " AND s.subscription_id IS NULL AND c.unavailable = 0 AND c.client_id NOT IN (1981, 1677, 537, 351, 539, 2137, 2138, 2139, 2140, 2141, 2142, 1495) AND c.creation_date < '2015-10-07 00:00:00'";
      }elseif($package_id == -2){
        // Paid Plans
        $where_client .= " AND s.subscription_id IS NOT NULL";
      }else{
        $where_client .= " AND IFNULL(s.package_id, 0) = {$package_id}";
      }
    }

    if($keyword){
      if(is_numeric($keyword)){
        $where_client .= " AND (c.client_id = {$keyword} OR c.shop LIKE '%{$keyword}%' OR c.domain LIKE '%{$keyword}%')";
      }else {
        $where_client .= " AND (c.shop LIKE '%{$keyword}%' OR c.domain LIKE '%{$keyword}%')";
      }
    }

    $pagination_where = "";
    if($last_login_count != -1 && $last_client_id){
      $report_order = $last_login_count * 10000 - $last_client_id;
      $pagination_where = " AND (IFNULL(logins.event_count, 0) * 10000 - c.client_id) < {$report_order}";
    }

    $shops = array();
    $addInfo = array();
    do{
      $query = $this->_getReportQuery($period_start, $period_end, $where_client, $pagination_where);
      $shopList = $db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);
      if(count($shopList)){
        $last_client_id = $shopList[count($shopList) - 1]['client_id'];
        $last_login_count = $shopList[count($shopList) - 1]['logins_events'];
        $shopIds = array();
        foreach($shopList as $shopData) {
          $shopIds[$shopData['db_id']][] = $shopData['client_id'];
          $shops[$shopData['client_id']] = $shopData;
        }
        $current_db = 0;
        foreach ($shopIds as $db_id => $ids) {
          if ($current_db != $db_id) {
            $database = ($db_id == 1) ? 'ssw_database' : 'ssw_database' . $db_id;
            $client_db = $this->getDI()->get($database);
          }

          foreach ($ids as $client_id) {
            $sql = "SELECT COUNT(*) AS `total` FROM {$client_id}_shopify_product_faves AS f
WHERE f.date BETWEEN {$period_start_timestamp} AND {$period_end_timestamp};";

            $row = $client_db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
            $addInfo[$client_id]['faves_count'] = $row['total'];

            $sql = "SELECT COUNT(*) AS `total` FROM {$client_id}_shopify_recommendation_recommend AS r
        WHERE r.creation_date BETWEEN {$period_start_timestamp} AND {$period_end_timestamp};";

            $row = $client_db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
            $addInfo[$client_id]['reviews_count'] = $row['total'];

            if($display_mode == 'with_reports' && (int)$shops[$client_id]['logins_events'] < 11 || ((int)$addInfo[$client_id]['faves_count'] < 11 && (int)$addInfo[$client_id]['reviews_count'] < 11)){
              // remove shop from list
              unset($shops[$client_id]);
              unset($addInfo[$client_id]);
            }
            elseif($display_mode == 'without_reports' && ((int)$shops[$client_id]['logins_events'] > 10 && ((int)$addInfo[$client_id]['faves_count'] > 10 || (int)$addInfo[$client_id]['reviews_count'] > 10))){
              // remove shop from list
              unset($shops[$client_id]);
              unset($addInfo[$client_id]);
            }
          }
        }
        $report_order = $last_login_count * 10000 - $last_client_id;
        $pagination_where = " AND (IFNULL(logins.event_count, 0) * 10000 - c.client_id) < {$report_order}";
//        $pagination_where = " AND IFNULL(logins.event_count,0) <= {$last_login_count} AND c.client_id > {$last_client_id}";
      }else{
        break;
      }
    }
    while(count($shops) < 50);

    $dateRanges = array(
      'today' => array(
        'title' => 'Today',
        'date' => date("Y-m-d", strtotime('today'))
      ),
      'yesterday' => array(
        'title' => 'Yesterday',
        'date' => date("Y-m-d", strtotime('yesterday'))
      ),
      'last_week' => array(
        'title' => 'Last 7 days',
        'date' => date("Y-m-d", strtotime('-6 days'))
      ),
      'last_2_week' => array(
        'title' => 'Last 14 days',
        'date' => date("Y-m-d", strtotime('-13 days'))
      ),
      'last_month' => array(
        'title' => 'Last 30 days',
        'date' => date("Y-m-d", strtotime('-29 days'))
      ),
      'last_3month' => array(
        'title' => 'Last 90 days',
        'date' => date("Y-m-d", strtotime('-89 days'))
      )
    );

    $sswClientIDs = ($shops) ? array_keys($shops) : array(0);
    $items = Shops::getShopsByClientIds($sswClientIDs);
    $crmShops = array();
    foreach ($items as $item) {
      $crmShops[$item->sswclient_id] = $item;
    }

    $this->view->setVar('addInfo', $addInfo);
    $this->view->setVar('shopList', $shops);
    $this->view->setVar('dateRanges', $dateRanges);
    $this->view->setVar('packages', $packages);
    $this->view->setVar('package_id', $package_id);
    $this->view->setVar('period_start', $period_start);
    $this->view->setVar('period_end', $period_end);
    $this->view->setVar('isPartially', $isPartially);
    $this->view->setVar('keyword', $keyword);
    $this->view->setVar('display_mode', $display_mode);
    $this->view->setVar('crmShops', $crmShops);
  }

  public function addToSendyAction()
  {
    try{
      /**
       * @var $shop Shops
       * @var $client Client
       */

      $LIST_TYPE = '';

      $shop_id = $this->getParam('shop_id', 0);
      $shop = Shops::findFirst($shop_id);
      $config = $this->getDI()->get('config');
      $client = $shop->getPrimaryOwner();
      if(!$client){
        $client = $shop->getOwner();
      }

      if ($LIST_TYPE == 'low') {
        $data = array(
          'api_key' => $config->sendy->api_key,
          'list' => $this->getParam('list_id'),
          'name' => $client->name,
          'email' => $client->contact_email ? $client->contact_email : $client->email,
          'boolean' => 'true',
        );
        $result = $client->sendDataToSendySync('subscribe', $data);
        if(is_numeric($result)){
          $checkTwice = $client->sendDataToSendySync('api/subscribers/subscription-status.php', array(
            'api_key' => $config->sendy->api_key,
            'list_id' => $this->getParam('list_id'),
            'email' => $client->contact_email ? $client->contact_email : $client->email
          ));
          $this->response->setJsonContent(array('success' => true, 'message' => $checkTwice));
        }else{
          $this->response->setJsonContent(array('success' => false, 'message' => $result));
        }
      }
      else if ($LIST_TYPE == 'free') {
        $discountCode = md5($shop_id . "Ravshan Krutoy Macho" . time());

        $discount = new SswDiscount();
        $discount->save(array(
          'code' => $discountCode,
          'trial_days' => 90,
          'client_id' => 0,
          'app' => 'default',
          'expiration_date' => date('Y-m-d H:i:s', strtotime('+2 month')),
          'used' => 0,
        ));

        $data = array(
          'api_key' => $config->sendy->api_key,
          'list' => $this->getParam('list_id'),
          'name' => $client->name,
          'email' => $client->contact_email ? $client->contact_email : $client->email,
          'discount' => $discountCode,
          'boolean' => 'true',
        );
        $result = $client->sendDataToSendySync('subscribe', $data);
        if(is_numeric($result)){
          $checkTwice = $client->sendDataToSendySync('api/subscribers/subscription-status.php', array(
            'api_key' => $config->sendy->api_key,
            'list_id' => $this->getParam('list_id'),
            'email' => $client->contact_email ? $client->contact_email : $client->email
          ));
          $this->response->setJsonContent(array('success' => true, 'message' => $checkTwice));
        }else{
          $this->response->setJsonContent(array('success' => false, 'message' => $result));
        }
      }
      else
      {
        $url = 'https://growave.io/lite/index/getReportBody?shop=' . $shop->url;
        $params = array(
          'period_start' => $this->getParam('period_start', date("Y-m-d", strtotime('-13 days'))),
          'period_end' => $this->getParam('period_end', date("Y-m-d", time()))
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $report = curl_exec ($ch);
        curl_close ($ch);

        if(trim($report)){
          $data = array(
            'api_key' => $config->sendy->api_key,
            'list' => $this->getParam('list_id'),
            'name' => $client->name,
            'email' => $client->contact_email ? $client->contact_email : $client->email,
            'StoreName' => $shop->getName(),
            'Report' => $report,
            'boolean' => 'true',
          );
          $result = $client->sendDataToSendySync('subscribe', $data);
          if(is_numeric($result)){
            $checkTwice = $client->sendDataToSendySync('api/subscribers/subscription-status.php', array(
              'api_key' => $config->sendy->api_key,
              'list_id' => $this->getParam('list_id'),
              'email' => $client->contact_email ? $client->contact_email : $client->email
            ));
            $this->response->setJsonContent(array('success' => true, 'message' => $checkTwice));
          }else{
            $this->response->setJsonContent(array('success' => false, 'message' => $result));
          }
        }else{
          $this->response->setJsonContent(array('success' => false, 'message' => 'No date for subscribe'));
        }
      }

      return $this->response->send();
    }catch (Exception $e) {
      $this->response->setJsonContent(array('success' => false, 'message' => $e->getMessage(), 'code' => $e->getCode(), 'trace' => $e->getTraceAsString()));
      return $this->response->send();
    }

  }

  public function sendyAction()
  {
/*    $clientIds = array(85,119,130,141,224,276,277,303,308,351,357,359,364,400,410,413,418,461,489,517,522,555,621,633,668,689,701,720,721,760,774,793,794,806,829,850,869,871,891,894,946,949,1006,1047,1056,1091,1162,1182,1184,1229,1285,1317,1332,1341,1420,1459,1474,1552,1612);
    $clientIdsStr = '85,119,130,141,224,276,277,303,308,351,357,359,364,400,410,413,418,461,489,517,522,555,621,633,668,689,701,720,721,760,774,793,794,806,829,850,869,871,891,894,946,949,1006,1047,1056,1091,1162,1182,1184,1229,1285,1317,1332,1341,1420,1459,1474,1552,1612';


    $query = "SELECT
  c.client_id, c.shop, logins.event_count AS logins_events, logins.users_count AS logins_users
  FROM shopify_ssw_client AS c

LEFT JOIN (
	SELECT e.client_id, SUM(e.event_count) AS event_count, SUM(e.users_count) AS users_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '2015-07-28' AND NOW() AND e.`event` IN ('email_login', 'social_login', 'social_signup')
	GROUP BY e.client_id
	ORDER BY event_count DESC, users_count DESC
) AS logins
	ON (c.client_id = logins.client_id)
WHERE c.client_id IN ({$clientIdsStr})
ORDER BY logins.event_count DESC";

    $db = Phalcon\DI::getDefault()->get('ssw_database');
    $shops = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);

    $firstGroup = array();
    $secondGroup = array();
    foreach ($shops as $shop) {
      if (count($firstGroup) < 25) {
        $firstGroup[] = $shop['client_id'];
      } else {
        $secondGroup[] = $shop['client_id'];
      }
    }

    print_arr(implode(',', $firstGroup));
    print_arr(implode(',', $secondGroup));

    print_die($shops);*/

    $this->view->setVar("pageTitle", "CRM Sites");
    $params = $this->_getAllParams();
    $params = ($params) ? $params : array();
//    $params['primary_email'] = 'IS NULL';
    $shops = Shops::getShopsPaginator($params);
    $shops = $shops->getPaginate();
    $shopIds = array(0);
    $clientIds = array(1488);
    $shopTickets = array();
    foreach ($shops->items as $shop) {
      $shopIds[] = $shop->shop_id;
      $clientIds[] = $shop->sswclient_id;
      $shopTickets[$shop->shop_id] = 0;
    }

    // get shop tickets count
    $ticketsData = Ticket::getShopTicketCount($shopIds);
    foreach ($ticketsData as $data) {
      $shopTickets[$data['shop_id']] = $data['tickets'];
    }

    /** @var  $db \Phalcon\Db\Adapter\Pdo\Mysql */
    $db = Phalcon\DI::getDefault()->get('ssw_database');

    $clientIdsStr = implode(',', $clientIds);
    $sql = "SELECT main.*, subscription.subscription_id FROM
      (
        SELECT client_id, app, status, package_id FROM shopify_ssw_clientapp WHERE client_id IN ({$clientIdsStr}) ORDER BY status DESC, IF (app = 'default', 0, 1)
      ) AS main
      LEFT JOIN shopify_ssw_subscription AS subscription
      ON (main.client_id = subscription.client_id AND main.app = subscription.app AND subscription.status = 'active' AND char_length(subscription.charge_id) >= 7)
      GROUP BY client_id";

    $clientAppList = $db->fetchAll($sql, Phalcon\Db::FETCH_OBJ);
    $clientApps = array();
    foreach ($clientAppList as $app) {
      $clientApps[$app->client_id] = $app;
    }

    $sql = "SELECT package_id, title FROM shopify_ssw_package";
    $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $packages = array('Free');
    foreach ($packageList as $package) {
      $packages[$package['package_id']] = $package['title'];
    }

    $sql = "SELECT name, title FROM shopify_ssw_app";
    $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $apps = array();
    foreach ($appList as $appInfo) {
      $apps[$appInfo['name']] = $appInfo['title'];
    }

    Core::addMissingShops();

    /** @var  $crm_db \Phalcon\Db\Adapter\Pdo\Mysql */
    $crm_db = $this->getDI()->get('db');
    $shopStr = implode(',', $shopIds);
    $sql = "SELECT crm_shopstaffs.*, crm_client.client_id, crm_client.email, crm_client.name FROM crm_shopstaffs
      INNER JOIN crm_client ON (crm_shopstaffs.client_id = crm_client.client_id)
      WHERE crm_shopstaffs.shop_id IN ({$shopStr})";
    $shopClients = $crm_db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
    $clients = array();
    $clientIds = array();
    $posts = array();
    $tickets = array();
    $shopClientIds = array();
    foreach ($shopClients as $shopClient) {
      $clientIds[] = $shopClient['client_id'];
      $tickets[$shopClient['client_id']] = 0;
      $posts[$shopClient['client_id']] = 0;
      $shopClientIds[$shopClient['shop_id']][] = $shopClient['client_id'];
      $clients[$shopClient['client_id']] = array('email' => $shopClient['email'], 'name' => $shopClient['name']);
    }

    $clientIds = array_unique($clientIds);
    $clientIdsStr = implode(',', $clientIds);
    $sql = "SELECT client_id, COUNT(*) AS count FROM crm_ticket WHERE client_id IN ({$clientIdsStr})
      AND subject <> :subject AND subject <> :subject_welcome
      GROUP BY client_id";
    $rows = $crm_db->fetchAll(
      $sql,
      \Phalcon\Db::FETCH_ASSOC,
      [
        'subject' => 'Wait... don\'t leave us',
        'subject_welcome' => 'Welcome  to  SocialShopWave  app'
      ]
    );
    foreach ($rows as $row) {
      $tickets[$row['client_id']] = $row['count'];
    }

    $sql = "SELECT t.client_id, COUNT(*) AS count
      FROM crm_ticket AS t
      INNER JOIN crm_post AS p ON (t.ticket_id = p.ticket_id AND p.type = 'client')
      WHERE t.client_id IN ({$clientIdsStr})
      GROUP BY t.client_id";
    $rows = $crm_db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
    foreach ($rows as $row) {
      $posts[$row['client_id']] = $row['count'];
    }

    $this->view->setVar("pageTitle", "CRM Sites");
    $this->view->setVars(array(
        'shops' => $shops,
        'params' => $params,
        'checkTickets' => $shopTickets,
        'clientApps' => $clientApps,
        'packages' => $packages,
        'apps' => $apps,
        'shopClientIds' => $shopClientIds,
        'tickets' => $tickets,
        'posts' => $posts,
        'clients' => $clients,
    ));
  }

  public function widgetsAction()
  {
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $shopIds = '36, 124, 209, 252, 257, 277, 363, 396, 417, 514, 773, 1056, 1057, 1183, 1465';
    $shops = SswClients::find(array("client_id IN ($shopIds)", 'columns' => 'client_id,shop,db_id,status,ssw', 'order' => 'status,client_id'));

    $apps = SswClientApp::find("app = 'default' AND client_id IN ($shopIds)");
    $shopApps = array();
    foreach ($apps as $app) {
      $shopApps[$app->client_id] = array('package_id' => $app->package_id, 'status' => $app->status);
    }

    $current_db = 0;
    $shopList = array();
    foreach ($shops as $shop) {
      if ($current_db != $shop->db_id) {
        $database = ($shop->db_id == 1) ? 'ssw_database' : 'ssw_database' . $shop->db_id;
        $db = $this->getDI()->get($database);
      }

      $shopList[$shop->client_id] = $shop->toArray();
      $shopList[$shop->client_id]['app_status'] = $shopApps[$shop->client_id]['status'];
      $shopList[$shop->client_id]['package_id'] = $shopApps[$shop->client_id]['package_id'];
      $shopList[$shop->client_id]['widgets'] = array();

      $widgetsTbl = $shop->client_id . '_shopify_core_widgets';
      try {
        $rows = $db->fetchAll("SELECT * FROM {$widgetsTbl} WHERE type NOT IN ('faved','reviewed','popular','recent')", \Phalcon\Db::FETCH_ASSOC);
        foreach ($rows as $row) {
          $shopList[$shop->client_id]['widgets'][] = $row;
        }
      } catch (Exception $e) {
      }
    }

    print_arr($shopList);
    print_die(111);
  }

  public function setPrimaryEmailAction()
  {
    $this->view->disable();
    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : false;
    $shop_id = isset($_REQUEST['shop_id']) ? $_REQUEST['shop_id'] : false;

    if (!$email || !$shop_id) {
      $this->response->setJsonContent(array('error' => 'Email or Shop is undefined'));
      return $this->response->send();
    }

    $shop = Shops::findFirst($shop_id);
    $shopStaff = Shopstaffs::getShopStaffs($shop_id);
    if (!$shop || !$shopStaff) {
      $this->response->setJsonContent(array('error' => 'Shop or Client not found'));
      return $this->response->send();
    }

    if ($email == $shop->primary_email) {
      $this->response->setJsonContent(array('error' => 'Email is already primary'));
      return $this->response->send();
    }

    $config = $this->getDI()->get('config');
    $client = new Client();

 /*   //check current email
    $data = array(
      'api_key' => $config->sendy->api_key,
      'list_id' => $config->sendy->all_clients,
      'email' => $email,
    );
    $result = $client->sendDataToSendySync('api/subscribers/subscription-status.php', $data);
    if ($result == 'Unsubscribed') {
      $this->response->setJsonContent(array('error' => 'Email is Unsubscribed'));
      return $this->response->send();
    }

    $data = array(
      'list' => $config->sendy->all_clients,
      'boolean' => 'true'
    );

    foreach ($shopStaff as $staff) {
      if ($email == $staff->email) {
        //subscribe primary
        $data['email'] = $staff->email;
        $data['name'] = $staff->name;
        $client->sendDataToSendy('subscribe', $data);
      } else {
        // unsubscribe duplicates
        $data['email'] = $staff->email;
        $client->sendDataToSendy('unsubscribe', $data);
      }
    }*/

    $shop->primary_email = $email;
    $shop->save();

    $this->response->setJsonContent(array('success' => true));
    return $this->response->send();
  }

  public function sendyListsAction()
  {
    // add missing shops
    Core::addMissingShops();

    $isAjax = $this->getParam('ajax', false);

    $list = $this->getParam('list', false);
    $discount = $this->getParam('discount', false);

    $sendyLists = array(
      'free' => SendySubscribers::FREE_KEY,
      'losing' => SendySubscribers::LOSING_KEY,
      'starter' => SendySubscribers::STARTER_KEY,
      'pro' => SendySubscribers::PRO_KEY,
      'newfree' => SendySubscribers::NEWFREE_KEY,

      'instafree' => SendySubscribers::INSTA_FREE_KEY,
      'instapaid' => SendySubscribers::INSTA_PAID_KEY,
      'instalosing' => SendySubscribers::INSTA_LOSING_KEY,
    );

    $sendyListIds = array(
      'free' => SendySubscribers::FREE_ID,
      'losing' => SendySubscribers::LOSING_ID,
      'starter' => SendySubscribers::STARTER_ID,
      'pro' => SendySubscribers::PRO_ID,
      'newfree' => SendySubscribers::NEWFREE_ID,

      'instafree' => SendySubscribers::INSTA_FREE_ID,
      'instapaid' => SendySubscribers::INSTA_PAID_ID,
      'instalosing' => SendySubscribers::INSTA_LOSING_ID
    );

    if ($list && $discount) {
      SendySubscribers::updateDiscounts($sendyListIds[$list], 60, '2018-02-24 00:00:00', 2);
      $list = false;
    }

    $config = $this->getDI()->get('config');
    $newUnsubscribesCount = 0;
    $newSubscribesCount = 0;
    $newOldSubscribesCount = 0;

    if ($list) {
      $sendySubscribers = SendySubscribers::find("list = {$sendyListIds[$list]}");
      $unsubscribeList = array();
      $sendySubscribersEmails = array();
      foreach ($sendySubscribers as $subscriber) {
        $sendySubscribersEmails[] = $subscriber->email;
        if ($subscriber->unsubscribed) {
          $unsubscribeList[] = $subscriber->email;
        }
      }

      if ($unsubscribeList) {
        $alreadyExists = Unsubscribe::getByEmails($unsubscribeList, 'email');
        foreach ($unsubscribeList as $email) {
          if (isset($alreadyExists[$email])) {
            continue;
          }
          $unsubscribe = new Unsubscribe();
          $unsubscribe->email = $email;
          $unsubscribe->date = date('Y-m-d H:i:s');
          $unsubscribe->save();
          $newUnsubscribesCount++;
        }
      }

      if ($list == 'free') {
        $crmClients = SendySubscribers::getFreeClients();
      } else if ($list == 'losing') {
        $crmClients = SendySubscribers::getLosingClients();
      } else if ($list == 'starter') {
        $crmClients = SendySubscribers::getStarterClients();
      } else if ($list == 'pro') {
        $crmClients = SendySubscribers::getProClients();
      } else if ($list == 'newfree') {
        $crmClients = SendySubscribers::getNewFreeClients();
      } else if ($list == 'instafree') {
        $crmClients = SendySubscribers::getInstaFreeClients();
      } else if ($list == 'instapaid') {
        $crmClients = SendySubscribers::getInstaPaidClients();
      } else if ($list == 'instalosing') {
        $crmClients = SendySubscribers::getInstaLoosingClients();
      }

      $newSubscribers = array();
      $oldSubscribers = $sendySubscribersEmails;
      foreach ($crmClients as $crmClient)  {
        $key = array_search($crmClient['email'], $oldSubscribers);

        if ($key === false) {
          $newSubscribers[] = $crmClient;
        } else {
          unset($oldSubscribers[$key]);
        }
      }

      $data = array(
        'api_key' => $config->sendy->api_key,
        'list' => $sendyLists[$list],
      );

      $client = new Client();
      foreach ($newSubscribers as $subscriber) {
        $data['email'] = $subscriber['email'];
        $data['name'] = $subscriber['name'];

        if ($list == 'free' || $list == 'losing') {
          $discountCode = md5($subscriber['email'] . "Ravshan Krutoy Macho" . time());

          $discount = new SswDiscount();
          $discount->save(array(
            'code' => $discountCode,
            'trial_days' => 90,
            'client_id' => 0,
            'app' => 'default',
            'expiration_date' => date('Y-m-d H:i:s', strtotime('+2 month')),
            'used' => 0,
          ));

          $data['discount'] = $discountCode;
        }

        $client->sendDataToSendy('subscribe', $data);
        $newSubscribesCount++;
      }

      $data = array(
        'api_key' => $config->sendy->api_key,
        'list_id' => $sendyLists[$list],
      );

      foreach ($oldSubscribers as $email) {
        $data['email'] = $email;
        $client->sendDataToSendy('api/subscribers/subscription-delete.php', $data);
        $newOldSubscribesCount++;
      }

    }

    if ($isAjax){
      $this->response->setStatusCode(200, 'OK');
      $this->response->setContentType('application/json', 'UTF-8');
      $this->response->setJsonContent(array(
        'new_unsubscribes' => $newUnsubscribesCount,
        'new_subscribers' => $newSubscribesCount,
        'old_subscribers' => $newOldSubscribesCount,
        'list' => $list,
      ));
      return $this->response->send();
    }

      $this->view->new_unsubscribes = $newUnsubscribesCount;
      $this->view->new_subscribers = $newSubscribesCount;
      $this->view->old_subscribers = $newOldSubscribesCount;

  }

  private function _exportApps()
  {
    $content = file_get_contents(__DIR__ . '/../../public/shopify_apps.log');
//    $content = file_get_contents('http://apps.shopify.com/?sortby=popular');
//    file_put_contents(__DIR__ . '/../../public/shopify_apps.log', $content);

    $apps = explode('<li itemscope itemtype="http://schema.org/Product"', $content);
    array_shift($apps);
    foreach ($apps as $key => $app) {
      $parts = explode('</li>', trim($app));
      array_pop($parts);
      $app_item = '<div ' . implode('</li>', $parts) . '</div>';
      $app_info = $this->_parseAppDetails($app_item);

      $appObject = new Shopifyapplications();
      $appObject->save($app_info);
    }
  }

  private function _checkApps()
  {
//    $content = file_get_contents(__DIR__ . '/../../public/shopify_apps.log');
    $content = file_get_contents('https://apps.shopify.com/?sortby=popular');
    file_put_contents(__DIR__ . '/../../public/shopify_apps.log', $content);

    $apps = explode('<li itemscope itemtype="http://schema.org/Product"', $content);
    array_shift($apps);
    $timestamp = mktime(date('H'), 0, 0);
    foreach ($apps as $position => $app) {
      $parts = explode('</li>', trim($app));
      array_pop($parts);
      $app_item = '<div ' . implode('</li>', $parts) . '</div>';
      $app_info = $this->_parseAppDetails($app_item);

      $appObject = Shopifyapplications::findFirst("key = '{$app_info['key']}'");
      if (!$appObject) {
        $appObject = new Shopifyapplications();
        $appObject->save($app_info);
      }

      $appRate = array(
        'app_id' => $appObject->getIdentity(),
        'updated_time' => $timestamp,
        'position' => $position + 1,
        'reviews' => $app_info['reviews'],
      );

      $appRateObject = new Shopifyapplicationrates();
      $appRateObject->save($appRate);
    }
  }

  private function _parseAppDetails($html)
  {
    $app = array();

    $wsql = new htmlsql();
    $wsql->connect('string', $html);

    $wsql->query('SELECT href FROM a WHERE strstr($class, "appcard-overlay") !== FALSE');
    $tag = $wsql->fetch_array();
    $app['key'] = str_replace('/', '', trim(array_values($tag[0])[0]));

    $wsql->query('SELECT content FROM meta WHERE $itemprop=="image"');
    $tag = $wsql->fetch_array();
    $app['image'] = trim(array_values($tag[0])[0]);

    $wsql->query('SELECT text FROM li WHERE $itemprop=="name"');
    $tag = $wsql->fetch_array();
    $app['name'] = trim(array_values($tag[0])[0]);

    $wsql->query('SELECT text FROM li WHERE $itemprop=="price"');
    $tag = $wsql->fetch_array();
    $app['price'] = strip_tags(trim(array_values($tag[0])[0]));

    $wsql->query('SELECT text FROM li WHERE $itemprop=="description"');
    $tag = $wsql->fetch_array();
    $app['description'] = trim(array_values($tag[0])[0]);

    $wsql->query('SELECT text FROM span WHERE $class=="appcard-rating-reviews"');
    $tag = $wsql->fetch_array();
    $app['reviews'] = (int)trim(array_values($tag[0])[0]);

    return $app;
  }

  private function _getReportQuery($period_start, $period_end, $where_client, $pagination_where)
  {
    $query = "SELECT
  c.client_id, c.shop, c.db_id, c.domain,
  IFNULL(logins.event_count, 0) AS logins_events,
  IFNULL(visits.visits_count, 0) AS visit_count,
  (IFNULL(logins.event_count, 0) * 10000 - c.client_id) AS report_order,
  IFNULL(s.package_id, 0) as package_id
FROM shopify_ssw_client AS c

LEFT JOIN shopify_ssw_subscription AS s
  ON (c.client_id = s.client_id AND s.app = 'default' AND s.status = 'active' AND char_length(s.charge_id) >= 7)

LEFT JOIN (
	SELECT e.client_id, SUM(e.users_count) AS event_count FROM shopify_statistics_events AS e
	WHERE e.`day` BETWEEN '{$period_start}' AND '{$period_end}' AND e.`event` IN ('email_login', 'social_login')
	GROUP BY e.client_id
) AS logins
	ON (c.client_id = logins.client_id)

LEFT JOIN (
	SELECT v.client_id, SUM(v.visitors_num) AS visits_count FROM shopify_statistics_visitors AS v
	WHERE v.`date` BETWEEN '{$period_start}' AND '{$period_end}'
	GROUP BY v.client_id
) AS visits
	ON (c.client_id = visits.client_id)

{$where_client}
{$pagination_where}

ORDER BY report_order DESC LIMIT 50";
    return $query;
  }
}