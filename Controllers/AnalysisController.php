<?php

/**
 * Created by PhpStorm.
 * User: USER
 * Date: 09.10.2014
 * Time: 15:33
 */
class AnalysisController extends AbstractController
{
  const DAY_IN_SECONDS = 86400;
  const WEEK_IN_SECONDS = 604800;
  const MONTH_COUNT = 20;
  /** @var RetentionManager */
  private $retentionManager;

  public function initialize()
  {
    $this->retentionManager = new RetentionManager();
  }

  public function indexAction()
  {
    /**
     * @var $db Phalcon\Db\Adapter\Pdo\Mysql
     */
    $weeks = $this->request->getPost('period_start', 'int', 12);
    $include_trials = $this->getParam('include_trials', 1);
//    print_die($include_trials);
    $periodInterval = self::WEEK_IN_SECONDS;
    $nowTime = time();

    $nowTimeTrimmed = $nowTime - self::DAY_IN_SECONDS * ((int)date('N', time()) - 1);
    $period_start = date('Y-m-d 00:00:00', $nowTimeTrimmed - $periodInterval * $weeks);
    $period_end = date('Y-m-d 00:00:00', $nowTime);

    $startPeriod = floor(($nowTimeTrimmed - strtotime($period_start)) / $periodInterval);
    $endPeriod = floor(($nowTime - strtotime($period_end)) / $periodInterval);

    $plans = array(
      'free',
      'lite',
      'starter',
      'professional',
      'enterprise',
      'enterprise2',
      'enterprise3',
      'free',
      'lite-pro'
    );

    $chartData = array();
    $max = 0;
    for ($packageId = 0; $packageId < 9; $packageId++) {
      if (in_array($packageId, [5, 6, 7])) continue;
      $planStats = array();
      for ($periodOffset = $startPeriod; $periodOffset >= $endPeriod; $periodOffset--) {
        $sTime = $nowTimeTrimmed - $periodInterval * $periodOffset;
        $eTime = $nowTimeTrimmed - $periodInterval * ($periodOffset - 1);
        $eTime = $eTime > strtotime($period_end) ? strtotime($period_end) : $eTime;
        $startDate = date('Y-m-d H:i:s', $sTime);
        $endDate = date('Y-m-d H:i:s', $eTime);
//        print_arr($startDate . ' - ' . $endDate);
        if ($sTime == $eTime) continue;
        $index = date('d', $sTime) . date('-d M', $eTime);
        $planStats[$index] = SswSubscriptions::getPlanCountForPeriod($packageId ? $packageId : 1, $startDate, $endDate, in_array($packageId, [0, 7]), $include_trials);
        $sum = $planStats[$index];
        $max = $max < $sum ? $sum : $max;
      }
      $chartData[$plans[$packageId]] = $planStats;
    }
//    die;
    $this->view->setVars(array(
      'period_start' => $period_start,
      'period_end' => $period_end,
      'chartData' => $chartData,
      'weeks' => $weeks,
      'include_trials' => $include_trials,
      'max' => $max
    ));
  }

  public function focusAction()
  {
    $totalRevenue = SswTestModes::getTotalSum(80);
    $testModes = SswTestModes::find();
    $client_ids = array();
    $newEpic = array(1465, 1979, 1982, 1992, 1993, 2070, 2097, 2102);

    foreach ($testModes as $row) {
      $row->trial = (strtotime($row->trial_ends_on) > time());
      $row->new = (in_array($row->client_id, $newEpic));
      $epicList[$row->client_id] = $row;
      $client_ids[] = $row->client_id;
    }

    $params = $this->_getAllParams();
    $params['sswclient_ids'] = $client_ids;
    $params['limit'] = 100;
    $params['order'] = 'status';
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
    $packages = array('-');
    foreach ($packageList as $package) {
      $packages[$package['package_id']] = $package['title'];
    }

    $sql = "SELECT name, title FROM shopify_ssw_app";
    $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $apps = array();
    foreach ($appList as $appInfo) {
      $apps[$appInfo['name']] = $appInfo['title'];
    }

    $this->view->setVars(array(
      'shops' => $shops,
      'params' => $params,
      'tickets' => $shopTickets,
      'clientApps' => $clientApps,
      'packages' => $packages,
      'apps' => $apps,
      'totalRevenue' => $totalRevenue,
      'epicList' => $epicList,
    ));
  }

  public function testAction()
  {
    print_die(55555);
  }

  public function reportsVolumeAction()
  {
    $reportsTab = $this->request->get('volume');
    $week = $this->request->get('week');

    $all = null;
    $thisDay = date('d');
    if ($week == 7) {
      if ($reportsTab != 1) {
        $getPostFromDB = $this->getPostFromTheDB($week);
        $all = $this->ReportsItem(7, 7, $week, $reportsTab, $getPostFromDB);
      } else {
        $all = $this->reportsConversations($week, 7);
      }
    }
    if ($week == 1) {
      if ($thisDay > 1) {
        $selectWeek = $thisDay - 1;
      } else {
        $selectWeek = 30 + 1;
      }
      if ($reportsTab != 1) {
        $check = $selectWeek;
        $getPostFromDB = $this->getPostFromTheDB($selectWeek);
        $all = $this->ReportsItem($selectWeek, $check, $week, $reportsTab, $getPostFromDB);
      } else {
        $all = $this->reportsConversations($week, $selectWeek);
      }
    }
    if ($week == 28) {
      if ($reportsTab != 1) {
        $getPostFromDB = $this->getPostFromTheDB($week);
        $all = $this->ReportsItem(28, 4, $week, $reportsTab, $getPostFromDB);
      } else {
        $all = $this->reportsConversations($week, 28);
      }
    }
    if ($week == 84) {
      if ($reportsTab != 1) {
        $getPostFromDB = $this->getPostFromTheDB($week);
        $all = $this->ReportsItem(84, 12, $week, $reportsTab, $getPostFromDB);
      } else {
        $all = $this->reportsConversations($week, 84);
      }
    }

    return json_encode($all);
  }


  public function getPostCount1($ticket_id)
  {
    $postCounts[$ticket_id] = Post::count("ticket_id = " . $ticket_id);
    return $postCounts[$ticket_id];
  }

  public function reportsAction()
  {

  }

  public function paymentAction()
  {
    $history = CacheApi::_()->getCache('crm_payment_history', self::DAY_IN_SECONDS);
    $columns = CacheApi::_()->getCache('crm_payment_columns', self::DAY_IN_SECONDS);

    if (is_null($history) && is_null($columns)) {
      $db = Phalcon\DI::getDefault()->get('db');
      $query = "SELECT DATE_FORMAT(p.payment_date, '%Y %M') AS period, COUNT(*) AS paid_clients,
        ROUND(SUM(p.share), 2) AS total, p.app FROM crm_payment AS p
        WHERE 1 AND (app = 'default' OR app = 'instagram' OR app = 'service')
        GROUP BY CONCAT(DATE_FORMAT(p.payment_date, '%Y %b'), p.app)
        ORDER BY p.payment_date";

      $payments = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $columns = array('default' => 1, 'instagram' => 2, 'service' => 3, 'Total' => 4, 'Growth' => 5);
      $paymentList = array();
      foreach ($payments as $payment) {
        if (!isset($paymentList[$payment['period']]))
          $paymentList[$payment['period']] = array($payment['period'], 0, 0, 0, 0);
        $paymentList[$payment['period']][$columns[$payment['app']]] = floatval($payment['total']);
        $paymentList[$payment['period']][$columns['Total']] += floatval($payment['total']);
      }

      $history = array(array('Period', 'SocialShopWave', 'Instagram', 'Service', 'Total', 'Growth'));
      $prevTotal = 0;
      $prevSSW = 0;
      $prevINSTA = 0;
      foreach ($paymentList as $payment) {
        $payment[$columns['Growth']] = ($prevTotal) ? round(100 * $payment[$columns['Total']] / $prevTotal, 2) - 100 : 100;
        $payment[$columns['Growth']] = ($payment[$columns['Growth']] >= 0)
          ? "<span class='payment_plus'><b>{$payment[$columns['Total']]}</b>+{$payment[$columns['Growth']]}% <i class='glyphicon glyphicon-arrow-up'></i></span>"
          : "<span class='payment_minus'><b>{$payment[$columns['Total']]}</b>{$payment[$columns['Growth']]}% <i class='glyphicon glyphicon-arrow-down'></i></span>";

        $sswGrowth = ($prevSSW) ? round(100 * $payment[$columns['default']] / $prevSSW, 2) - 100 : 100;
        $payment[$columns['Growth']] .= ($sswGrowth >= 0)
          ? "<span class='payment_plus' style='color:#337ab7'><b>SSW</b>+{$sswGrowth}% <i class='glyphicon glyphicon-arrow-up'></i></span>"
          : "<span class='payment_minus'style='color:#337ab7'><b>SSW</b>{$sswGrowth}% <i class='glyphicon glyphicon-arrow-down'></i></span>";

        $instaGrowth = ($prevINSTA) ? round(100 * $payment[$columns['instagram']] / $prevINSTA, 2) - 100 : 100;
        $payment[$columns['Growth']] .= ($instaGrowth >= 0)
          ? "<span class='payment_plus' style='color:#f39c12'><b>INSTA</b>+{$instaGrowth}% <i class='glyphicon glyphicon-arrow-up'></i></span>"
          : "<span class='payment_minus'style='color:#f39c12'><b>INSTA</b>{$instaGrowth}% <i class='glyphicon glyphicon-arrow-down'></i></span>";

        $prevTotal = $payment[$columns['Total']];
        $prevSSW = $payment[$columns['default']];
        $prevINSTA = $payment[$columns['instagram']];
        $history[] = $payment;
      }

      CacheApi::_()->setCache('crm_payment_columns', $columns, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('crm_payment_history', $history, self::DAY_IN_SECONDS);
    }


    $comparePayments = CacheApi::_()->getCache('crm_payment_comparePayments', self::DAY_IN_SECONDS);
    $top = CacheApi::_()->getCache('crm_payment_top', self::DAY_IN_SECONDS);
    $shopsInfo = CacheApi::_()->getCache('crm_payment_shopsInfo', self::DAY_IN_SECONDS);

    if (is_null($comparePayments) && is_null($top) && is_null($shopsInfo)) {
      $comparePayments = Payment::getPaymentComparing();

      // Top clients
      $top = Payment::getTopClients();
      $sswClientIds = array();
      foreach ($top as $client) {
        $sswClientIds[] = $client['sswclient_id'];
      }

      $paymentInfo = Payment::getClientsPackages($sswClientIds);
      $shops = Shops::getShopsByClientIds($sswClientIds);
      $shopsInfo = array();
      foreach ($shops as $shop) {
        $clientApp = $paymentInfo['clientApps'][$shop->sswclient_id];

        $shopsInfo[$shop->sswclient_id] = array(
          'shop_id' => $shop->shop_id,
          'url' => $shop->url,
          'status' => $shop->status,
          'app' => ($clientApp->app == 'default') ? 'SSW' : $clientApp->app,
          'package' => ($clientApp->subscription_id) ? $paymentInfo['packages'][$clientApp->package_id] : 0,
        );
      }
      CacheApi::_()->setCache('crm_payment_comparePayments', $comparePayments, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('crm_payment_top', $top, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('crm_payment_shopsInfo', $shopsInfo, self::DAY_IN_SECONDS);
    }

//    $this->predictionAction();
    $this->view->columns = $columns;
    $this->view->history = $history;
    $this->view->comparePayments = $comparePayments;
    $this->view->top = $top;
    $this->view->shopsInfo = $shopsInfo;
  }


  public function retentionAction()
  {
    $handledRetention = CacheApi::_()->getCache('сrm_retention_handledRetention', self::DAY_IN_SECONDS);
    $months = CacheApi::_()->getCache('сrm_retention_months', self::DAY_IN_SECONDS);

    if (is_null($handledRetention) && is_null($months)) {
      $months = $this->_getMonths();
      $retention = $this->retentionManager->getRetention($months, self::MONTH_COUNT);
      $handledRetention = $this->retentionManager->handleDataForView($retention);

      CacheApi::_()->setCache('сrm_retention_handledRetention', $handledRetention, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('сrm_retention_months', $months, self::DAY_IN_SECONDS);
    }

    $this->view->retention = $handledRetention;
    $this->view->months = $months;
  }

  public function _getMonths()
  {
    $lastMonths = array();
    for ($i = 1; $i < self::MONTH_COUNT; $i++) {
      $lastMonths[] = date("Y-m-01", strtotime('-' . (self::MONTH_COUNT - $i) . 'month -2day'));
    }

    return $lastMonths;
  }


  public function trialAction()
  {
    /**
     * @var $db Phalcon\Db\Adapter\Pdo\Mysql
     */
    $db = Phalcon\DI::getDefault()->get('ssw_database');

    $mainSql = "SELECT c.client_id, a.app, a.admin_deleted, s.expiration_date, s.package_id
        FROM shopify_ssw_client AS c
        INNER JOIN shopify_ssw_clientapp AS a
          ON (c.client_id = a.client_id AND a.status = 1)
        LEFT JOIN crm_epic_testmode AS t
          ON (a.client_id = t.client_id AND a.app = t.app)
        INNER JOIN shopify_ssw_subscription AS s
          ON (a.client_id = s.client_id AND a.app = s.app AND s.active = 1 AND char_length(s.charge_id) >= 7 AND s.charge_id != 1111111)
        WHERE c.email NOT IN ('farside312@gmail.com', 'ulanproger@gmail.com', 'burya1988@gmail.com', 'ermekcs@gmail.com', 'eldarbox@gmail.com', 'test.assurence@gmail.com', 'jazgul114@gmail.com')
          AND t.charge_id IS NULL
          AND c.unavailable = 0";

    $trialSql = $mainSql . " AND s.expiration_date > NOW() ORDER BY s.expiration_date";
    $trials = $db->fetchAll($trialSql, Phalcon\Db::FETCH_ASSOC);

    $paidSql = $mainSql . " AND s.expiration_date < NOW() ORDER BY c.client_id DESC";
    $paids = $db->fetchAll($paidSql, Phalcon\Db::FETCH_ASSOC);

    $sql = "SELECT package_id, title FROM shopify_ssw_package";
    $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $packages = array();
    foreach ($packageList as $package) {
      $packages[$package['package_id']] = $package['title'];
    }

    $clientIds = array();
    $today = new DateTime();
    $today->modify('-1 day');
    foreach ($trials as &$trial) {
      $expirationDate = new DateTime($trial['expiration_date']);
      $diff = $expirationDate->diff($today);
      $trial['date'] = $diff->format('%a');
      if ($trial['date'] <= 5) {
        $trial['class'] = 'success';
      } else if ($trial['date'] <= 10) {
        $trial['class'] = 'info';
      } else if ($trial['date'] <= 15) {
        $trial['class'] = 'warning';
      } else {
        $trial['class'] = 'danger';
      }

      $clientIds[] = $trial['client_id'];
    }
    $paidIds = array();
    foreach ($paids as $paid) {
      $clientIds[] = $paid['client_id'];
      $paidIds[] = $paid['client_id'];
    }

    $paidIdStr = implode(',', $paidIds);
    $paymentSql = "SELECT p.sswclient_id, p.app, COUNT(*) AS months, ROUND(SUM(p.share), 2) AS total FROM crm_payment AS p
        WHERE p.sswclient_id IN ({$paidIdStr})
        GROUP BY CONCAT(p.sswclient_id, '_', p.app)";
    $crmDB = Phalcon\DI::getDefault()->get('db');
    $payments = $crmDB->fetchAll($paymentSql, Phalcon\Db::FETCH_ASSOC);
    $paymentList = array();
    foreach ($payments as $payment) {
      $key = $payment['sswclient_id'] . $payment['app'];
      $paymentList[$key] = $payment;
    }

    $loosingSql = "SELECT a.client_id, a.admin_deleted, sub.package_id, main.*, t.client_id AS test_mode FROM
        (
            SELECT e.shop_id, e.event, e.our_remove, e.app, e.event_id, e.date FROM shopify_ssw_events AS e
              WHERE 1
              ORDER BY e.event_id DESC
        ) AS main
        INNER JOIN shopify_ssw_clientapp AS a ON (main.shop_id = a.client_id AND main.app = a.app)
	      INNER JOIN shopify_ssw_subscription AS sub ON (sub.client_id = a.client_id)
        LEFT JOIN crm_epic_testmode AS t ON (a.client_id = t.client_id AND a.app = t.app)
	      WHERE a.status = 0 OR a.admin_deleted = 1
        GROUP BY CONCAT(main.shop_id, main.app)
        HAVING main.event = 'uninstall'
        ORDER BY main.event_id DESC
        LIMIT 50;";

    $loosing = $db->fetchAll($loosingSql, Phalcon\Db::FETCH_ASSOC);
    foreach ($loosing as $item) {
      $clientIds[] = $item['client_id'];
    }

    $clientIds = array_unique($clientIds);
    $shops = Shops::getShopsByClientIds($clientIds, true);
    if (count($clientIds) != count($shops)) {
      Core::addMissingShops();
      $shops = Shops::getShopsByClientIds($clientIds, true);
    }

    $emails = array();
    foreach ($shops as $shop) {
      $emails[] = ($shop->primary_email) ? $shop->primary_email : $shop->owner;
    }
    if (count($clientIds) != count($emails)) {
      print_slack('CRM - Shop Problem!!!', 'pd');
    }

    $owners = Client::getClientsByEmails($emails, 'email');

    $this->view->trials = $trials;
    $this->view->paids = $paids;
    $this->view->loosing = $loosing;
    $this->view->paymentList = $paymentList;
    $this->view->shops = $shops;
    $this->view->packages = $packages;
    $this->view->owners = $owners;
  }

  public function predictionAction()
  {


    $allApps = CacheApi::_()->getCache('crm_payment_allApps', self::DAY_IN_SECONDS);
    $prediction = CacheApi::_()->getCache('crm_payment_prediction', self::DAY_IN_SECONDS);
    $prediction_future = CacheApi::_()->getCache('crm_payment_prediction_future', self::DAY_IN_SECONDS);

    if (is_null($allApps) && is_null($prediction) && is_null($prediction_future)) {

      /**
       * @var $crmDB Phalcon\Db\Adapter\Pdo\Mysql
       * @var $db Phalcon\Db\Adapter\Pdo\Mysql
       */
      $crmDB = Phalcon\DI::getDefault()->get('db');
      $db = Phalcon\DI::getDefault()->get('ssw_database');

      $query = "SELECT p.* FROM crm_prediction AS p
            LEFT JOIN crm_prediction AS n ON (p.sswclient_id = n.sswclient_id AND p.app = n.app AND p.prediction_id <> n.prediction_id AND p.billing_on < n.billing_on)
            INNER JOIN crm_shops AS s ON (p.sswclient_id = s.sswclient_id AND s.`status` IN ('active', 'installed'))
            WHERE p.test = 0 AND n.prediction_id IS NULL";
      $allPredictions = $crmDB->fetchAll($query, Phalcon\Db::FETCH_OBJ);
      $charge_ids = array(0);
      $toUpdateChargeIds = array(0);
      foreach ($allPredictions as $prediction) {
        if ($prediction->test != 1
          && strtotime($prediction->updated_at) < strtotime("-1 month")
          && strtotime($prediction->billing_on) < strtotime("-5 day")
          && strtotime($prediction->trial_ends_on) < time()) {
          $toUpdateChargeIds[] = $prediction->charge_id;
        }
        $charge_ids[] = $prediction->charge_id;
      }

      $charge_ids = implode(',', $charge_ids);
      $toUpdateChargeIds = implode(',', $toUpdateChargeIds);

      $mainSql = "SELECT c.client_id, c.shop, a.app, s.package_id, s.charge_id
              FROM shopify_ssw_client AS c
            INNER JOIN shopify_ssw_clientapp AS a
              ON (c.client_id = a.client_id AND a.status = 1)
            LEFT JOIN crm_epic_testmode AS t
              ON (a.client_id = t.client_id AND a.app = t.app)
            INNER JOIN shopify_ssw_subscription AS s
              ON (a.client_id = s.client_id AND a.app = s.app AND s.active = 1 AND char_length(s.charge_id) >= 7 AND s.charge_id != 1111111)
            WHERE c.email NOT IN ('farside312@gmail.com', 'ulanproger@gmail.com', 'burya1988@gmail.com', 'ermekcs@gmail.com', 'eldarbox@gmail.com', 'test.assurence@gmail.com', 'jazgul114@gmail.com', 'justin.whalley@shopify.com')
             AND t.charge_id IS NULL
             AND c.unavailable = 0 AND (s.charge_id IN ($toUpdateChargeIds) OR s.charge_id NOT IN ($charge_ids))";
      $allApps = $db->fetchAll($mainSql, Phalcon\Db::FETCH_ASSOC);

      $prediction = Prediction::getFinancialProjections();
      $prediction_future = Prediction::getFinancialProjections(date("Y-m-d", strtotime('+1 month')));

      CacheApi::_()->setCache('crm_payment_allApps', $allApps, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('crm_payment_prediction', $prediction, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('crm_payment_prediction_future', $prediction_future, self::DAY_IN_SECONDS);
    }


    $this->view->allApps = $allApps;
    $this->view->prediction = $prediction;
    $this->view->prediction_future = $prediction_future;
  }

  public function predictionSaveAction()
  {
    $data = isset($_REQUEST['data']) ? $_REQUEST['data'] : false;
    foreach ($data as $shop_info) {
      $sswclient_id = isset($shop_info['shop']['client_id']) ? $shop_info['shop']['client_id'] : 0;
      if (!$sswclient_id) continue;

      $updated_at = date("Y-m-d H:i:s", strtotime($shop_info['charge']['updated_at']));
      $prediction = Prediction::findFirst(array(
        "conditions" => "sswclient_id = ?0 AND updated_at = ?1 AND app = ?2",
        "bind" => array($shop_info['shop']['client_id'], $updated_at, $shop_info['shop']['app'])
      ));
      if (!$prediction) {
        $prediction = new Prediction();
      }
      $prediction->sswclient_id = $shop_info['shop']['client_id'];
      $prediction->shop = $shop_info['shop']['shop'];
      $prediction->app = $shop_info['shop']['app'];
      $prediction->charge_id = $shop_info['shop']['charge_id'];
      $prediction->package_id = $shop_info['shop']['package_id'];
      $prediction->price = $shop_info['charge']['price'];
      $prediction->status = $shop_info['charge']['status'];
      $prediction->billing_on = $shop_info['charge']['billing_on'];
      $prediction->activated_on = $shop_info['charge']['activated_on'];
      $prediction->trial_ends_on = $shop_info['charge']['trial_ends_on'];
      $prediction->updated_at = $updated_at;
      $prediction->trial_days = $shop_info['charge']['trial_days'];
      $prediction->test = (int)($shop_info['charge']['test'] == 'true');

      $result = $prediction->save();
      if (!$result) {
        $errors = array();
        foreach ($prediction->getMessages() as $message) {
          $errors[] = $message->getField() . ' - ' . $message->getMessage();
        }
        if (!$errors) {
          exit(json_encode($errors));
        }
      }
    }
    exit();
  }

  public function instagramAction()
  {
    /**
     * @var $db Phalcon\Db\Adapter\Pdo\Mysql
     */
    $weeks = $this->request->getPost('period_start', 'int', 12);
    $include_trials = $this->getParam('include_trials', 1);
//    print_die($include_trials);
    $periodInterval = 604800; // One week
    $oneDay = self::DAY_IN_SECONDS; // One day
    $nowTime = time();
    $nowTimeTrimmed = $nowTime - $oneDay * ((int)date('N', time()) - 1);
    $period_start = date('Y-m-d 00:00:00', $nowTimeTrimmed - $periodInterval * $weeks);
    $period_end = date('Y-m-d 00:00:00', $nowTime);

    $startPeriod = floor(($nowTimeTrimmed - strtotime($period_start)) / $periodInterval);
    $endPeriod = floor(($nowTime - strtotime($period_end)) / $periodInterval);

    $plans = array(
      19 => 'free',
      11 => 'starter',
      12 => 'professional'
    );

    $chartData = array();
    $max = 0;
    foreach ($plans as $packageId => $plan) {
      $planStats = array();
      for ($periodOffset = $startPeriod; $periodOffset >= $endPeriod; $periodOffset--) {
        $sTime = $nowTimeTrimmed - $periodInterval * $periodOffset;
        $eTime = $nowTimeTrimmed - $periodInterval * ($periodOffset - 1);
        $eTime = $eTime > strtotime($period_end) ? strtotime($period_end) : $eTime;
        $startDate = date('Y-m-d H:i:s', $sTime);
        $endDate = date('Y-m-d H:i:s', $eTime);
//        print_arr($startDate . ' - ' . $endDate);
        if ($sTime == $eTime) continue;
        $index = date('d', $sTime) . date('-d M', $eTime);
        $planStats[$index] = SswSubscriptions::getPlanCountForPeriod($packageId ? $packageId : 1, $startDate, $endDate, ($plan == 'free'), $include_trials, 'instagram');
        $sum = $planStats[$index];
        $max = $max < $sum ? $sum : $max;
      }
      $chartData[$plans[$packageId]] = $planStats;
    }

    $this->view->setVars(array(
      'period_start' => $period_start,
      'period_end' => $period_end,
      'chartData' => $chartData,
      'weeks' => $weeks,
      'include_trials' => $include_trials,
      'max' => $max
    ));
  }

  public function instaRetentionAction()
  {
    $newRetention = CacheApi::_()->getCache('сrm_instaRetention_newRetention', 3600);
    $lastMonths = CacheApi::_()->getCache('сrm_instaRetention_lastMonths', 3600);

    if (is_null($newRetention) && is_null($lastMonths)) {

      $monthCount = 9;
      $lastMonth = date("Y-m-01", strtotime('1 month'));
      $lastMonths = array();
      for ($i = 1; $i <= ($monthCount); $i++) {
        $lastMonths[] = date("Y-m-01", strtotime('-' . ($monthCount - $i) . 'month'));
      }

      $retention = array();
      foreach ($lastMonths as $key => $month) {
        $clientIds = SswEvents::getRetentionClientIds($month, 'instagram');
        $unavailableShops = SswEvents::getRetentionUnavailable($clientIds, $month, $lastMonth);
        $monthInfo = array();
        while ($key < $monthCount) {
          $key++;
          $nextMonth = date("Y-m-01", strtotime('-' . ($monthCount - $key - 1) . 'month'));
          $loosing = SswEvents::getRetentionLoosing($clientIds, $month, $nextMonth, 'instagram');
          foreach ($unavailableShops as $shop) {
            if (strtotime($shop['unavailable_date']) > strtotime($nextMonth)) {
              break;
            }
            if (!in_array($shop['client_id'], $loosing['admin_deleted']) && !in_array($shop['client_id'], $loosing['uninstall'])) {
              $loosing['unavailable'][] = $shop['client_id'];
            }
          }
          $monthInfo[$nextMonth] = $loosing;
        }

        $retention[$month]['all'] = $clientIds;
        $retention[$month]['months'] = $monthInfo;
      }

      $newRetention = array();
      foreach ($retention as $startMonth => $monthOverall) {
        $info = array();
        $info['all'] = count($monthOverall['all']);
        $info['months'] = array();
        $unavailable = 0;
        foreach ($monthOverall['months'] as $month => $left) {
          $report = array();

          $report['period'] = date("Y F", strtotime($month) - 10);
          $report['uninstall'] = isset($left['uninstall']) ? count($left['uninstall']) : 0;
          $report['admin_deleted'] = isset($left['admin_deleted']) ? count($left['admin_deleted']) : 0;
          $report['unavailable'] = isset($left['unavailable']) ? count($left['unavailable']) : 0;
          $report['active'] = $info['all'] - $report['uninstall'] - $report['admin_deleted'] - $report['unavailable'];
          $report['active_percentage'] = round(100 * $report['active'] / $info['all'], 2);
          $info['months'][$month] = $report;
        }

        $startMonth = date("Y-M", strtotime($startMonth));
        $newRetention[$startMonth] = $info;
      }


      CacheApi::_()->setCache('сrm_instaRetention_newRetention', $newRetention, 3600);
      CacheApi::_()->setCache('сrm_instaRetention_lastMonths', $lastMonths, 3600);
    }

    $this->view->retention = $newRetention;
    $this->view->lastMonths = $lastMonths;
  }

  public function ReportsItem($selectWeek, $check, $week, $reportsTab, $getPostFromDB)
  {
    $thisDate = date('d.M.Y');
    $pastWeekArray = null;
    $support = array();
    $result = array();
    $ticketsId = array();
    $sumPrev = 0;
    $sum = 0;
    $maxUserId = 0;
    $prevText = '';
    $forAmountIteration = null;
    if ($reportsTab == 2) {
      $support = CacheApi::_()->getCache('support_for_' . $week, self::DAY_IN_SECONDS);
      $maxUserId = CacheApi::_()->getCache('maxUserId_' . $week, self::DAY_IN_SECONDS);
      $ticketsId = CacheApi::_()->getCache('ticketsId_' . $week, self::DAY_IN_SECONDS);
      $forAmountIteration = CacheApi::_()->getCache('check_' . $week, self::DAY_IN_SECONDS);
      if (is_null($support) && is_null($ticketsId) && is_null($maxUserId)) {
        if ($week == 84 or $week == 28) {
          $pastWeekArray = $this->week($selectWeek, $reportsTab, $getPostFromDB, $week);
          $support = $pastWeekArray['result'];
          $maxUserId = $pastWeekArray['maxUserId'];
          $ticketsId = $pastWeekArray['ticketsId'];
          $forAmountIteration = $check;
        } else {
          $pastWeekArray = $this->day($selectWeek, $reportsTab, $getPostFromDB, $week);
          $support = $pastWeekArray['result'];
          $maxUserId = $pastWeekArray['maxUserId'];
          $ticketsId = $pastWeekArray['ticketsId'];
          $forAmountIteration = $check;
        }
        CacheApi::_()->setCache('support_for_' . $week, $support, self::DAY_IN_SECONDS);
        CacheApi::_()->setCache('maxUserId_' . $week, $maxUserId, self::DAY_IN_SECONDS);
        CacheApi::_()->setCache('ticketsId_' . $week, $ticketsId, self::DAY_IN_SECONDS);
        CacheApi::_()->setCache('check_' . $week, $forAmountIteration, self::DAY_IN_SECONDS);
      }
    } else {
      $result = CacheApi::_()->getCache('results_' . $week, self::DAY_IN_SECONDS);
      $sum = CacheApi::_()->getCache('sum_' . $week, self::DAY_IN_SECONDS);
      $sumPrev = CacheApi::_()->getCache('sumPrev_' . $week, self::DAY_IN_SECONDS);
      $prevText = CacheApi::_()->getCache('prevText_' . $week, self::DAY_IN_SECONDS);
      $forAmountIteration = CacheApi::_()->getCache('check_' . $week, self::DAY_IN_SECONDS);
      if (is_null($result) && is_null($sum) && is_null($sumPrev) && is_null($prevText)) {
        if ($week == 84 or $week == 28) {
          $pastWeekArray = $this->week($selectWeek, $reportsTab, $getPostFromDB, $week);
          $prevText = 'from previous ' . $check . ' weeks';
        } else {
          $pastWeekArray = $this->day($selectWeek, $reportsTab, $getPostFromDB, $week);
          $prevText = 'from previous ' . $check . ' days';
        }
        $result = $pastWeekArray['result'];
        $sum = $pastWeekArray['sum'];
        $sumPrev = $pastWeekArray['sumPrev'];
        $forAmountIteration = $check;

      }
      CacheApi::_()->setCache('results_' . $week, $result, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('sum_' . $week, $sum, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('sumPrev_' . $week, $sumPrev, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('prevText_' . $week, $prevText, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('check_' . $week, $forAmountIteration, self::DAY_IN_SECONDS);
    }
    $date = date("Y-m-d");
    $date = strtotime($date);
    $date = strtotime("-$selectWeek day", $date);
    $selectDate = date('d.M.Y', $date);
    $thisDate = strtotime($thisDate);
    $thisDate = strtotime("-1 day", $thisDate);
    $thisDate = date('d.M.Y', $thisDate);
    $weekArray = [
      'thisDate' => $thisDate,
      'selectDate' => $selectDate,
    ];
    $all = null;
    if ($reportsTab == 2) {
      $all = [
        'support_for' => $support,
        'check' => $forAmountIteration,
        'maxUserId' => $maxUserId,
        'weekArray' => $weekArray,
        'ticketsId' => $ticketsId,
      ];
    } else {
      $all = [
        'result' => $result,
        'weekArray' => $weekArray,
        'prevText' => $prevText,
        'sumPrev' => $sum - $sumPrev,
        'check' => $forAmountIteration,
        'sum' => $sum,
      ];
    }
    return $all;
  }


  public function day($selectWeek, $reportsTab, $getPostFromDB, $week)
  {
    $sum = 0;
    $sumPrev = 0;
    $result = array();
    for ($l = 1; $l < ($selectWeek * 2) + 1; $l++) {
      $ticketIdSecond = 0;
      $ticketBool = true;
      $date = date("Y-m-d");
      $date = strtotime($date);
      $date = strtotime("-$l day", $date);
      $pastWeek = date('Y-m-d', $date);

      $sortGetPostFromDB = $this->sortPostFromTheDB($getPostFromDB, $pastWeek, $selectWeek, $week);
      $post = json_decode(json_encode($sortGetPostFromDB), FALSE);
      $postDay = $this->forReportPostDay($post, $ticketBool, $ticketIdSecond, $selectWeek, $pastWeek, $l, $reportsTab);

      if ($reportsTab == 2) {
        if ($l <= $selectWeek) {
          $name = date('d-M', strtotime($pastWeek));
          $result[$l] = [
            'support' => $postDay['support'],
            'maxUserId' => $postDay['maxUserId'],
            'thisMonth' => $name,
            'userCounter' => $postDay['userCounter'],
            'ticketsId' => $postDay['ticketsId'],
            'counter' => $postDay['counter'],
          ];
        }
      } else {
        $i = $postDay['i'];
        $hoursDay = $postDay['hoursDay'];

        if ($l > $selectWeek) {
          $sumPrev += $i;
        } else {
          $name = date('d-M', strtotime($pastWeek));
          $sum += $i;
          $result[$l] = [
            'count' => $i,
            'thisMonth' => $name,
            'dayName' => date('l', strtotime($name)),
            'hours' => $hoursDay,
          ];
        }
      }
    }
    if ($reportsTab == 2) {
      $sortSupport = $this->sortSupport($result, $selectWeek, $week);
      $pastWeekArray = [
        'result' => $sortSupport,
        'maxUserId' => $result[1]['maxUserId'],
        'ticketsId' => $result,
      ];
    } else {
      $pastWeekArray = [
        'result' => $result,
        'sum' => $sum,
        'sumPrev' => $sumPrev,
      ];
    }
    return $pastWeekArray;
  }

  function supportImage($user)
  {

    $user_img = 0;
    $file = File::findFirst("parent_id = {$user} AND parent_type = 'user'");
    if ($file) {
      if (strstr($file->path, 'avatars/') !== false) {
        $user_img = 'https://crmgrowave.s3.amazonaws.com' . $file->path;
      } else {
        $user_img = '//' . $_SERVER['HTTP_HOST'] . $file->path;
      }
    } else {
      $user_img = '//' . $_SERVER['HTTP_HOST'] . '/img/default_user_photo.jpg';
    }

    return $user_img;
  }


  public function supportTicketClosed($userFor, $posts, $logs)
  {

    $ticketsClosedId = [
      'user_id' => $userFor->user_id,
      'ticket_id' => $posts->ticket_id,
      'creation_date' => $logs->date,
      'subject' => mb_strimwidth($posts->subject, 0, 30),
      'staff_id' => $posts->staff_id,
      'status' => $posts->status,
      'client_name' => $posts->client_name,
      'type' => $posts->type,
      'client_id' => $posts->client_id,
      'updated_date' => (strtotime(date("Y-m-d H:i:s")) - strtotime($posts->updated_date)) / 60,
      'countPost' => $this->getPostCount1($posts->ticket_id),
    ];

    return $ticketsClosedId;
  }

  public function supportTicket($userFor, $posts)
  {

    $ticketsId = ['user_id' => $userFor->user_id,
      'ticket_id' => $posts->ticket_id,
      'creation_date' => $posts->creation_date,
      'subject' => mb_strimwidth($posts->subject, 0, 30),
      'staff_id' => $posts->staff_id,
      'status' => $posts->status,
      'client_name' => $posts->client_name,
      'type' => $posts->type,
      'client_id' => $posts->client_id,
      'updated_date' => (strtotime(date("Y-m-d H:i:s")) - strtotime($posts->updated_date)) / 60,
      'countPost' => $this->getPostCount1($posts->ticket_id),
    ];

    return $ticketsId;
  }

  public function forReportPostDay($post, $ticketBool, $ticketIdSecond, $selectWeek, $pastWeek, $l, $reportsTab)
  {
    $i = 0;
    $hoursDayCount = array();
    $hoursDay = array();
    $support = array();
    $supportClosed = array();
    $supportClosedCount = array();
    $count = 0;
    $ticketsId = array();
    $ticketsClosedId = array();
    $supportMessageCount = array();
    if (date('l', strtotime($pastWeek)) == 'Sunday') {
      for ($r = 1; $r < 24 + 1; $r++) {
        $hoursDay[$r] = [
          'count' => 0,
        ];
      }
    }
    for ($r = 1; $r < 24 + 1; $r++) {
      $hoursDayCount[$r] = $hoursDayCount[$r] = 0;
    }
    $userQuery = $this->supportQuery();
    $maxUserID = $userQuery['maxUserID'];
    $userCounter = $userQuery['userCounter'];
    for ($o = 0; $o < $maxUserID + 1; $o++) {
      $supportMessageCount[$o] = 0;
      $supportClosedCount[$o] = 0;
    }
    $user = User::find("department='support' AND status=1");
    foreach ($post as $posts) {
      $date = $posts->creation_date;
      $dateFirst = date("d:m:Y", strtotime($date));
      if ($ticketIdSecond == $posts->ticket_id and $dateFirst == $dateTwo) {
        $ticketBool = false;
      }
      if ($ticketBool == true) {

        $dateTwo = date("d:m:Y", strtotime($date));
        $ticketIdSecond = $posts->ticket_id;
        if ($user) {
          foreach ($user as $userFor) {
            if ($userFor->user_id == $posts->staff_id) {
              $i++;
              if ($l <= $selectWeek) {
                $ticket_logs = TicketLogs::find("ticket_id=$posts->ticket_id");
                if ($reportsTab == 2) {
                  $user_img = $this->supportImage($userFor->user_id);
                  $supportMessageCount[$posts->staff_id] += 1;

                  $ticketsId[$i] = $this->supportTicket($userFor, $posts);

                  $support[$posts->staff_id] = [
                    'user_id' => $userFor->user_id,
                    'user_img' => $user_img,
                    'user_name' => $userFor->full_name,
                    'date' => $posts->creation_date,
                    'count' => $supportMessageCount[$posts->staff_id],
                  ];

                  foreach ($ticket_logs as $logs) {
                    $creation_date = date('Y-m-d', strtotime($posts->creation_date));
                    $logs_date = date('Y-m-d', strtotime($logs->date));
                    if ($logs->status_ticket == 'closed' AND $creation_date == $logs_date) {
                      $supportClosedCount[$posts->staff_id] += 1;
                      $ticketsClosedId[$i] = $this->supportTicketClosed($userFor, $posts, $logs);
                      $supportClosed[$posts->staff_id] = [
                        'user_id' => $userFor->user_id,
                        'user_img' => $user_img,
                        'user_name' => $userFor->full_name,
                        'date' => $posts->creation_date,
                        'count' => $supportClosedCount[$posts->staff_id],
                      ];
                      break;
                    }
                  }
                } else {
                  foreach ($ticket_logs as $logs) {
                    if ($logs->status_ticket == 'open') {
                      if (date("d:m:Y", strtotime($logs->date)) == $dateTwo) {
                        for ($r = 1; $r < 24 + 1; $r++) {
                          $time = date("G", strtotime($logs->date));
                          if ($time > 18) {
                            $time = ($time - 24) + 6;
                          } else {
                            $time += 6;
                          }
                          if ($r - 1 == $time) {
                            $hoursDayCount[$r] = $hoursDayCount[$r] + 1;
                          }
                          $hoursDay[$r] = [
                            'count' => $hoursDayCount[$r],
                          ];
                        }
                        $count++;
                      }
                    }
                  }
                }
              }
            }
          }
        }
      } else {
        $ticketBool = true;
      }
    }
    if ($reportsTab == 2) {
      $supportsArray = array();
      $ticketsArrayId = array();
      $supportsArray[1] = $support;
      $supportsArray[2] = $supportClosed;
      $ticketsArrayId[1] = $ticketsId;
      $ticketsArrayId[2] = $ticketsClosedId;

      $postDay = [
        'support' => $supportsArray,
        'maxUserId' => $maxUserID,
        'userCounter' => $userCounter,
        'ticketsId' => $ticketsArrayId,
        'counter' => $i,
      ];
    } else {
      $postDay = [
        'i' => $i,
        'hoursDay' => $hoursDay,
      ];
    }
    return $postDay;
  }

  public function week($selectWeek, $reportsTab, $getPostFromDB, $week)
  {
    $result = array();
    $sum = 0;
    $sumPrev = 0;
    for ($l = 1; $l < (($selectWeek / 7) * 2) + 1; $l++) {
      $ticketIdSecond = 0;
      $ticketBool = true;
      $t = $l * 7;
      $date = date("Y-m-d");
      $date = strtotime($date);
      $date = strtotime("-$t day", $date);
      $pastWeek = date('Y-m-d', $date);
      $pastWeekAfter = $pastWeek;
      $pastWeekAfter = strtotime($pastWeekAfter);
      $pastWeekAfter = strtotime("+7 day", $pastWeekAfter);
      $pastWeekAfter = date('Y-m-d', $pastWeekAfter);

      $sortGetPostFromDB = $this->sortPostFromTheDB($getPostFromDB, $pastWeek, $selectWeek, $week);
      $post = json_decode(json_encode($sortGetPostFromDB), FALSE);
      $postDay = $this->forReportPostWeek($post, $ticketBool, $ticketIdSecond, $selectWeek, $pastWeek, $pastWeekAfter, $l, $reportsTab);
      if ($reportsTab == 2) {
        if ($l <= $selectWeek / 7) {
          $name = date('d M', strtotime($pastWeekAfter));
          $name1 = date('d M', strtotime($pastWeek));
          $nameMonth = $name1 . " - " . $name;
          $nameWeek = $l . '-week';
          $result[$l] = [
            'support' => $postDay['support'],
            'maxUserId' => $postDay['maxUserId'],
            'thisMonth' => $nameMonth,
            'userCounter' => $postDay['userCounter'],
            'nameWeek' => $nameWeek,
            'ticketsId' => $postDay['ticketsId'],
            'counter' => $postDay['counter'],
          ];
        }
      } else {
        $i = $postDay['i'];
        $hoursDate = $postDay['hoursDate'];
        if ($l > $selectWeek / 7) {
          $sumPrev += $i;
        } else {
          $name = date('d M', strtotime($pastWeekAfter));
          $name1 = date('d M', strtotime($pastWeek));
          $nameMonth = $name1 . " - " . $name;
          $sum += $i;
          $result[$l] = [
            'count' => $i,
            'thisMonth' => $nameMonth,
            'dayName' => date('l', strtotime($name)),
            'hours' => $hoursDate,
          ];
        }
      }
    }
    if ($reportsTab == 2) {
      $sortSupport = $this->sortSupport($result, $selectWeek, $week);
      $pastWeekArray = [
        'result' => $sortSupport,
        'maxUserId' => $result[1]['maxUserId'],
        'resultMonth' => $result,
        'ticketsId' => $result,
      ];
    } else {
      $pastWeekArray = [
        'result' => $result,
        'sum' => $sum,
        'sumPrev' => $sumPrev,
      ];
    }
    return $pastWeekArray;
  }

  public function forReportPostWeek($post, $ticketBool, $ticketIdSecond, $selectWeek, $pastWeek, $pastWeekAfter, $l, $reportsTab)
  {
    $support = array();
    $supportMessageCount = array();
    $supportClosed = array();
    $supportClosedCount = array();
    $ticketsClosedId = array();
    $ticketsId = array();
    $hoursDayCount = array();
    $hoursDay = array();
    $count = 0;
    $hoursDate = array();
    if (date('l', strtotime($pastWeek)) == 'Sunday') {
      for ($r = 1; $r < 24 + 1; $r++) {
        $hoursDay[$r] = [
          'count' => 0,
        ];
      }
    }

    for ($r = 1; $r < 24 + 1; $r++) {
      $hoursDayCount[$r] = $hoursDayCount[$r] = 0;
    }

    for ($k = 1; $k < 7 + 1; $k++) {
      $hoursDate[$k] =
        [
          'date' => 0,
          'hoursDay' => 0,
        ];
    }
    $userQuery = $this->supportQuery();
    $maxUserID = $userQuery['maxUserID'];
    $userCounter = $userQuery['userCounter'];

    for ($o = 0; $o < $maxUserID + 1; $o++) {
      $supportMessageCount[$o] = 0;
      $supportClosedCount[$o] = 0;
    }
    $i = 0;
    $user = User::find("department='support' AND status=1");
    foreach ($post as $posts) {
      if ($ticketIdSecond == $posts->ticket_id) {
        $ticketBool = false;
      }

      if ($ticketBool == true) {
        $i++;
        $ticketIdSecond = $posts->ticket_id;
        if ($user) {
          foreach ($user as $userFor) {
            if ($userFor->user_id == $posts->staff_id) {
              $i++;
              if ($l <= $selectWeek / 7) {
                $ticket_logs = TicketLogs::find("ticket_id=$posts->ticket_id ");
                if ($reportsTab == 2) {
                  $user_img = $this->supportImage($userFor->user_id);
                  $supportMessageCount[$posts->staff_id] += 1;
                  $ticketsId[$i] = $this->supportTicket($userFor, $posts);
                  $support[$posts->staff_id] = [
                    'user_id' => $userFor->user_id,
                    'user_img' => $user_img,
                    'user_name' => $userFor->full_name,
                    'date' => $posts->creation_date,
                    'count' => $supportMessageCount[$posts->staff_id],
                  ];
                  foreach ($ticket_logs as $logs) {
                    $creation_date = date('Y-m-d', strtotime($posts->creation_date));
                    $logs_date = date('Y-m-d', strtotime($logs->date));
                    if ($logs->status_ticket == 'closed' AND $creation_date == $logs_date) {
                      {
                        $supportClosedCount[$posts->staff_id] += 1;
                        $ticketsClosedId[$i] = $this->supportTicketClosed($userFor, $posts, $logs);
                        $supportClosed[$posts->staff_id] = [
                          'user_id' => $userFor->user_id,
                          'user_img' => $user_img,
                          'user_name' => $userFor->full_name,
                          'date' => $posts->creation_date,
                          'count' => $supportClosedCount[$posts->staff_id],
                        ];
                        break;
                      }
                    }
                  }
                } else {
                  for ($g = 1; $g < 7 + 1; $g++) {
                    $dateHour = $pastWeek;
                    $dateHour = strtotime($dateHour);
                    $dateHour = strtotime("+$g day", $dateHour);
                    $dateHour = date('Y-m-d', $dateHour);
                    foreach ($ticket_logs as $logs) {
                      if ($logs->status_ticket == 'open') {
                        if ($dateHour == date("Y-m-d", strtotime($logs->date))) {
                          for ($r = 1; $r < 24 + 1; $r++) {
                            $time = date("G", strtotime($logs->date));
                            if ($time > 18) {
                              $time = ($time - 24) + 6;
                            } else {
                              $time += 6;
                            }
                            if ($r - 1 == $time) {
                              $hoursDayCount[$r] = $hoursDayCount[$r] + 1;
                            }
                            $hoursDay[$r] = [
                              'count' => $hoursDayCount[$r],
                            ];
                          }
                          $count++;
                          $dateCount = date('l', strtotime($logs->date));
                          $hoursDate[$g] = [
                            'date' => $dateCount,
                            'ticket_id' => $logs->ticket_id,
                            'hoursDay' => $hoursDay,
                          ];
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }

      } else {
        $ticketBool = true;
      }
    }
    if ($reportsTab == 2) {
      $supportsArray = array();
      $ticketsArrayId = array();
      $supportsArray[1] = $support;
      $supportsArray[2] = $supportClosed;
      $ticketsArrayId[1] = $ticketsId;
      $ticketsArrayId[2] = $ticketsClosedId;
      $postDay = [
        'support' => $supportsArray,
        'maxUserId' => $maxUserID,
        'userCounter' => $userCounter,
        'ticketsId' => $ticketsArrayId,
        'counter' => $i,
      ];
    } else {
      $postDay = [
        'i' => $i,
        'hoursDate' => $hoursDate,
      ];
    }
    return $postDay;
  }

  public function getPostFromTheDB($week)
  {
    $withdrawalForThisDate = $week * 2;
    $date = date("Y-m-d");
    $date = strtotime($date);
    $date = strtotime("-$withdrawalForThisDate day", $date);
    $pastWeek = date('Y-m-d', $date);
    $db = Phalcon\DI::getDefault()->get('db');
    $query = "SELECT b.ticket_id,c.name as client_name,b.client_id,a.creation_date,b.updated_date,b.subject,a.staff_id,b.status,a.`type`
              FROM crm_post a
              INNER JOIN crm_ticket b ON b.ticket_id=a.ticket_id
              INNER JOIN crm_client c ON c.client_id=b.client_id 
              WHERE a.`type`='team' AND a.creation_date > '" . $pastWeek . "' AND b.ticket_type='user_ticket' 
              AND a.staff_id>0
              AND (a.from_intercom IS NULL OR a.from_intercom=0)
              ";
    $ticket = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
    return $ticket;
  }

  public function sortPostFromTheDB($ticket, $pastWeek, $selectWeek, $week)
  {
    $i = 0;
    $ticketArray = array();
    if ($week == 28 or $selectWeek == 84) {
      $pastWeekAfter = $pastWeek;
      $pastWeekAfter = strtotime($pastWeekAfter);
      $pastWeekAfter = strtotime("+7 day", $pastWeekAfter);
      $pastWeekAfter = date('Y-m-d', $pastWeekAfter);
      $ticket = array_reverse($ticket);
      foreach ($ticket as $item) {
        $creation_date = strtotime($item['creation_date']);
        $creation_date = date('Y-m-d', $creation_date);
        if ($creation_date >= $pastWeek and $creation_date < $pastWeekAfter) {
          $ticketArray[$i] = [
            'ticket_id' => $item['ticket_id'],
            'creation_date' => $item['creation_date'],
            'subject' => $item['subject'],
            'staff_id' => $item['staff_id'],
            'status' => $item['status'],
            'client_name' => $item['client_name'],
            'type' => $item['type'],
            'client_id' => $item['client_id'],
            'updated_date' => $item['updated_date'],

          ];
          $i++;
        }
      }
    } else {
      foreach ($ticket as $item) {
        if (preg_match('/' . $pastWeek . '/', $item['creation_date'], $match)) {
          $ticketArray[$i] = [
            'ticket_id' => $item['ticket_id'],
            'creation_date' => $item['creation_date'],
            'subject' => $item['subject'],
            'staff_id' => $item['staff_id'],
            'status' => $item['status'],
            'client_name' => $item['client_name'],
            'type' => $item['type'],
            'client_id' => $item['client_id'],
            'updated_date' => $item['updated_date'],
          ];
          $i++;
        }
      }
    }
    return $ticketArray;
  }

  public function supportQuery()
  {
    $crmDB = Phalcon\DI::getDefault()->get('db');
    $query = "Select a.* from crm_user a where user_id=(select MAX(b.user_id) from crm_user b)";
    $query1 = "select count(*) as count from crm_user where department='support'";
    $maxUserID = $crmDB->fetchAll($query, Phalcon\Db::FETCH_OBJ);
    $userCounter = $crmDB->fetchAll($query1, Phalcon\Db::FETCH_OBJ);

    $queryArray = [
      'maxUserID' => $maxUserID[0]->user_id,
      'userCounter' => $userCounter[0]->count,
    ];
    return $queryArray;
  }

  public function sortSupport($result, $selectWeek, $week)
  {
    $supportArray = array();
    if ($week == 28 or $selectWeek == 84) {
      $selectWeek = $selectWeek / 7;
    }
    for ($s = 1; $s < 3; $s++) {
      for ($o = 0; $o < $result[1]['maxUserId']; $o++) {
        $countSum = 0;
        for ($t = 1; $t < $selectWeek + 1; $t++) {
          for ($r = 1; $r < $result[1]['maxUserId'] + 1; $r++) {
            if (isset($result[$t]['support'][$s][$r]) == true) {
              if ($result[$t]['support'][$s][$r]['user_id'] == $o) {
                $supportArray[$s][$o][$t]['user_id'] = $result[$t]['support'][$s][$r]['user_id'];
                $supportArray[$s][$o][$t]['user_img'] = $result[$t]['support'][$s][$r]['user_img'];
                $supportArray[$s][$o][$t]['user_name'] = $result[$t]['support'][$s][$r]['user_name'];
                $supportArray[$s][$o][$t]['count'] = $result[$t]['support'][$s][$r]['count'];
                $countSum += $result[$t]['support'][$s][$r]['count'];
                $supportArray[$s][$o][$t]['countSum'] = $countSum;
              }
            }
          }
        }
      }
    }
    return $supportArray;
  }

  public function reportsConversations($week, $selectWeek)
  {
    $allPosts = array();
    $postsToTicketID = array();
    $weekCounterDivision = 1;
    $weekCountMessageClient = 0;
    $weekPostsClientAmount = 0;
    $postsTicketArray = array();
    $sum = 0;
    $sumPrev = 0;
    $allPosts = CacheApi::_()->getCache('allPosts_' . $week, self::DAY_IN_SECONDS);
    $sum = CacheApi::_()->getCache('sumAll_' . $week, self::DAY_IN_SECONDS);
    $sumPrev = CacheApi::_()->getCache('sumPrevAll_' . $week, self::DAY_IN_SECONDS);
    $check = CacheApi::_()->getCache('checkAll_' . $week, self::DAY_IN_SECONDS);
    if (is_null($allPosts) && is_null($sum) && is_null($sumPrev) && is_null($check)) {
      $post = $this->getPostConversationFromTheDB($selectWeek);
      for ($l = 1; $l < $selectWeek * 2 + 1; $l++) {
        $date = date("Y-m-d");
        $date = strtotime($date);
        $date = strtotime("-$l day", $date);
        $pastWeek = date('Y-m-d', $date);
        $check = $selectWeek;
        $secondTicketId = 0;
        $postData = array();
        $ticketId = array();
        $postsClientAmount = 0;
        $countMessageClient = 0;

        $ticketIdPostTeam = 0;
        $postDataIteration = 0;
        foreach ($post as $posts) {
          $postsCreationDate = date('Y-m-d', strtotime($posts['creation_date']));
          if ($pastWeek == $postsCreationDate) {
            if ($posts['ticket_id'] != $secondTicketId and $posts['type'] == 'team') {
              $ticketId[$ticketIdPostTeam] = $posts['ticket_id'];
              $secondTicketId = $posts['ticket_id'];
              $ticketIdPostTeam++;
            }
            $postData[$postDataIteration] = [
              'type' => $posts['type'],
              'creation_date' => $posts['creation_date'],
              'post_id' => $posts['post_id'],
              'staff_id' => $posts['staff_id'],
              'ticket_id' => $posts['ticket_id'],
            ];
            $postDataIteration++;
          }
        }

        $f = 0;
        foreach ($ticketId as $tickets) {
          $postsToTicketID[$ticketId[$f]] = 0;
          for ($t = 0; $t < $postDataIteration; $t++) {
            if ($ticketId[$f] == $postData[$t]['ticket_id'] and $postData[$t]['type'] == 'client') {
              if (isset($postsToTicketID[$ticketId[$f]]) == false) {
                $postsToTicketID[$ticketId[$f]] = 0;
              }

              if (isset($postData[$t + 1]) == true and $postData[$t]['type'] != $postData[$t + 1]['type']) {
                $postsToTicketID[$ticketId[$f]] += 1;
              }
              if ($postsToTicketID[$ticketId[$f]] != 0) {
                $countMessageClient++;
                $weekCountMessageClient++;
              }
              $postsClientAmount += $postsToTicketID[$ticketId[$f]];
            }
          }
          $f++;
        }
        if ($postsClientAmount != 0) {
          $postsClientAmount = $postsClientAmount / $countMessageClient;
          $weekPostsClientAmount += $postsClientAmount;
        }

        if ($week == 84 or $week == 28) {
          if ($l / 7 == $weekCounterDivision) {
            if ($l <= $selectWeek) {
              $pastWeekAfter = $pastWeek;
              $pastWeekAfter = strtotime($pastWeekAfter);
              $pastWeekAfter = strtotime("+7 day", $pastWeekAfter);
              $pastWeekAfter = date('Y-m-d', $pastWeekAfter);
              $name = date('d M', strtotime($pastWeekAfter));
              $name1 = date('d M', strtotime($pastWeek));
              $nameMonth = $name1 . " - " . $name;

              $sum += $weekPostsClientAmount / 6;
              $allPosts[$weekCounterDivision] = [
                'postsClientAmount' => round($weekPostsClientAmount / 6, 2),
                'thisMonth' => $nameMonth,
              ];
            } else {
              $sumPrev += $weekPostsClientAmount / 6;
            }
            $weekCounterDivision++;
            $weekPostsClientAmount = 0;
            $weekCountMessageClient = 0;
          }
        } else {
          if ($l <= $selectWeek) {
            $name = date('d-M', strtotime($pastWeek));
            $sum += $postsClientAmount;
            $allPosts[$l] = [
              'postsClientAmount' => round($postsClientAmount, 2),
              'thisMonth' => $name,
            ];
          } else {
            $sumPrev += $postsClientAmount;
          }
        }
      }
      if ($week == 1 or $week == 7) {
        $sum = $sum / ($selectWeek - 1);
        $sumPrev = $sumPrev / ($selectWeek - 1);
      } else {
        $sum = $sum / ($selectWeek / 7);
        $sumPrev = $sumPrev / ($selectWeek / 7);
      }
      CacheApi::_()->setCache('allPosts_' . $week, $allPosts, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('sumAll_' . $week, $sum, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('sumPrevAll_' . $week, $sumPrev, self::DAY_IN_SECONDS);
      CacheApi::_()->setCache('checkAll_' . $week, $check, self::DAY_IN_SECONDS);
    }
    return $result = [
      'allPosts' => $allPosts,
      'sumPrev' => round($sum - $sumPrev, 2),
      'sum' => round($sum, 2),
      'check' => $check,
    ];
  }

  public function getPostConversationFromTheDB($week)
  {
    $withdrawalForThisDate = $week * 2;
    $date = date("Y-m-d");
    $date = strtotime($date);
    $date = strtotime("-$withdrawalForThisDate day", $date);
    $pastWeek = date('Y-m-d', $date);
    $db = Phalcon\DI::getDefault()->get('db');
    $query = "SELECT a.post_id,b.ticket_id,b.client_id,a.creation_date,b.updated_date,b.subject,a.staff_id,b.status,a.`type`
              FROM crm_post a
              INNER JOIN crm_ticket b ON b.ticket_id=a.ticket_id
              WHERE (a.`type`='client' OR a.`type`='team') AND a.creation_date > '" . $pastWeek . "' 
              AND b.ticket_type='user_ticket' 
              AND  
              CASE
              WHEN a.`type`='team' THEN staff_id>0 ELSE staff_id=0
			        END
			        AND b.client_id!=1 AND b.client_id!=50913
              AND (a.from_intercom IS NULL OR a.from_intercom=0)
              ";
    $ticket = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
    return $ticket;
  }
}

