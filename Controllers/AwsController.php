<?php

use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;

class AwsController extends AbstractController
{
  public function indexAction()
  {
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('myaws');
    $tracks = $collection->find();
    $trackedDays = [];
    foreach ($tracks as $track) {
      /**
       * @var $track Phalcon\Db\Adapter\MongoDB\Model\BSONDocument

       */
      $trackInfo = $track->getArrayCopy();
      if ($trackInfo['key'] == 'gantts_diagram') {
        continue;
      }
      unset($trackInfo['waste']);
      $trackedDays[$trackInfo['key']] = $trackInfo;
    }

    $this->view->servers = $this->getDbServers();
    $this->view->trackDays = $trackedDays;
  }

  public function checkDeletedTablesAction()
  {
    $lastClient = $this->getParam('lastClient', 0);
    /**
     * @var $db DbAdapter
     */
    $db = $this->getDI()->get('ssw_database');

    $sql = "SELECT c.db_id, c.client_id FROM shopify_ssw_client AS c
INNER JOIN shopify_ssw_clientapp AS a
ON (c.client_id = a.client_id AND a.new = 0)
WHERE c.new = 1 AND c.client_id > :lastClient AND c.db_id IN (21,22)
ORDER BY c.db_id, c.client_id
LIMIT 20";
    $clients = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC, ['lastClient' => $lastClient]);
    $dbs = [1 => $db];
    foreach ($clients as $client) {
      if (!isset($dbs[$client['db_id']])) {
        $dbs[$client['db_id']] = $db = $this->getDI()->get('ssw_database' . $client['db_id']);
      }
      $db = $dbs[$client['db_id']];
      $subkey = '_key_' . $client['client_id'];
      $sql = "SHOW TABLES LIKE '{$client['client_id']}\_%'";
      $tables = $db->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC);
      if ($tables === false) {
        $apps = SswClientApp::find([
          'conditions' => 'client_id = ?0 AND new = 0',
          'bind' => [$client['client_id']]
        ]);
        foreach ($apps as $app) {
          $app->new = 1;
          $app->save();
        }
        CacheApi::_()->unsetCache($subkey . 'client_packages');
        CacheApi::_()->unsetCaches($subkey . 'ssw_client_package_');

      } else {
        $clientItem = SswClients::findFirst([
          'conditions' => 'client_id = ?0',
          'bind' => [$client['client_id']]
        ]);
        $clientItem->new = 0;
        $clientItem->save();
      }
    }

    if (!$clients) {
      echo json_encode([
        'result' => true,
        'finished' => true,
      ]);
      exit();
    }

    echo json_encode([
      'result' => true,
      'finished' => false,
      'lastClient' => (int)$client['client_id']
    ]);

    exit();
  }

  public function checkDeletedClientsAction()
  {

    $lastClient = $this->getParam('lastClient', 0);
    /**
     * @var $db DbAdapter
     */
    $db = $this->getDI()->get('ssw_database');

    $sql = "SELECT c.db_id, c.client_id FROM shopify_ssw_client AS c
INNER JOIN shopify_ssw_clientapp AS a
ON (c.client_id = a.client_id AND a.new = 1 AND a.status = 0)
WHERE c.new = 1 AND c.status = 0 AND c.client_id > :lastClient  AND c.db_id IN (1,2,3,4)
ORDER BY c.db_id, c.client_id
LIMIT 20";
    $clients = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC, ['lastClient' => $lastClient]);
    $dbs = [1 => $db];
    $problems = [];
    foreach ($clients as $client) {
      if (!isset($dbs[$client['db_id']])) {
        $dbs[$client['db_id']] = $db = $this->getDI()->get('ssw_database' . $client['db_id']);
      }
      $db = $dbs[$client['db_id']];
//      $subkey = '_key_' . $client['client_id'];
      $sql = "SHOW TABLES LIKE '{$client['client_id']}\_%'";
      $tables = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
      if ($tables) {
        $problems[] = $client['client_id'];
      }
//      if ($tables === false) {
//        $apps = SswClientApp::find([
//          'conditions' => 'client_id = ?0 AND new = 0',
//          'bind' => [$client['client_id']]
//        ]);
//        foreach ($apps as $app) {
//          $app->new = 1;
//          $app->save();
//        }
//        CacheApi::_()->unsetCache($subkey . 'client_packages');
//        CacheApi::_()->unsetCaches($subkey . 'ssw_client_package_');

//      } else {
//        $clientItem = SswClients::findFirst([
//          'conditions' => 'client_id = ?0',
//          'bind' => [$client['client_id']]
//        ]);
//        $clientItem->new = 0;
//        $clientItem->save();
//        CacheApi::_()->unsetCache($subkey . 'client_packages');
//        CacheApi::_()->unsetCaches($subkey . 'ssw_client_package_');
//        CacheApi::_()->unsetCache('ssw_shop_' . md5($clientItem->shop));
//      }
    }

    if (!$clients) {
      echo json_encode([
        'result' => true,
        'finished' => true,
        'problems' => $problems,
      ]);
      exit();
    }

    echo json_encode([
      'result' => true,
      'finished' => false,
      'problems' => $problems,
      'lastClient' => (int)$client['client_id']
    ]);

    exit();
  }

  public function checkAllAction()
  {
    return $this->checkAllNew();

    $key = date('d_m_Y');
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('myaws');

    $currentTrack = $collection->findOne(['key' => $key]);
    if (!$currentTrack) {
      $currentTrack = [
        'key' => $key,
        'lastClient' => 0,
        'ssw' => 0,
        'freeInsta' => 0,
        'free' => 0,
        'insta' => 0,
      ];
      $dbServers = $this->getDbServers();
      $dbIds = array_keys($dbServers);
      foreach ($dbIds as $dbId) {
        $currentTrack['db' . $dbId] = 0;
      }
      $collection->insertOne($currentTrack);
      $currentTrack = $collection->findOne(['key' => $key]);
    }

    $lastClient = $currentTrack['lastClient'];
    $clients = SswClients::find([
      'columns' => 'client_id,db_id',
      'conditions' => 'client_id > ?0 AND new = 0',
      'bind' => [$lastClient],
      'limit' => 3,
      'order' => 'client_id'
    ]);

    $dbs = [];
    $problems = [];
    $sswTables = ['_shopify_core_comment', '_shopify_core_mailcontent', '_shopify_core_mailmenu', '_shopify_core_mailtemplate', '_shopify_core_module', '_shopify_core_notification', '_shopify_core_notificationsetting', '_shopify_core_setting', '_shopify_core_tagmaps', '_shopify_core_tags', '_shopify_core_task', '_shopify_core_widgets', '_shopify_feed_follow', '_shopify_feed_imgsize', '_shopify_feed_item', '_shopify_feed_report', '_shopify_feed_stats', '_shopify_feed_tag', '_shopify_feed_wall', '_shopify_mail_abandons', '_shopify_mail_checkout', '_shopify_mail_checkoutprods', '_shopify_mail_income', '_shopify_mail_links', '_shopify_mail_mail', '_shopify_mail_orders', '_shopify_mail_queue', '_shopify_mail_reviewtoken', '_shopify_mail_sent', '_shopify_mail_statistics', '_shopify_product_cart', '_shopify_product_cartproduct', '_shopify_product_collection', '_shopify_product_favelist', '_shopify_product_faves', '_shopify_product_order', '_shopify_product_orderproduct', '_shopify_product_prodcollection', '_shopify_product_product', '_shopify_product_queue', '_shopify_recommendation_queue', '_shopify_recommendation_recommend', '_shopify_reward_activity', '_shopify_service_campaign', '_shopify_service_discount', '_shopify_service_gallery', '_shopify_service_galleryimg', '_shopify_service_image', '_shopify_service_imgstat', '_shopify_service_instatag', '_shopify_service_orderprods', '_shopify_service_orders', '_shopify_service_tag', '_shopify_service_tagimg', '_shopify_statistics_graphs', '_shopify_statistics_referrals', '_shopify_statistics_userreferral', '_shopify_statistics_users', '_shopify_storage_files', '_shopify_storage_image', '_shopify_user_banned', '_shopify_user_service', '_shopify_user_socialsharing', '_shopify_user_user'];
    $freeInstaTables = ['_shopify_core_module', '_shopify_core_setting', '_shopify_core_task', '_shopify_product_cart', '_shopify_product_faves', '_shopify_product_order', '_shopify_product_product', '_shopify_recommendation_recommend', '_shopify_service_gallery', '_shopify_service_galleryimg', '_shopify_service_image', '_shopify_service_imgstat', '_shopify_service_instatag', '_shopify_service_tag', '_shopify_service_tagimg', '_shopify_statistics_users', '_shopify_user_service', '_shopify_user_user'];
    $instaTables = ['_shopify_core_module', '_shopify_core_setting', '_shopify_core_task', '_shopify_product_cart', '_shopify_product_order', '_shopify_service_gallery', '_shopify_service_galleryimg', '_shopify_service_image', '_shopify_service_imgstat', '_shopify_service_instatag', '_shopify_service_tag', '_shopify_service_tagimg', '_shopify_statistics_users'];
    $freeTables = ['_shopify_core_module', '_shopify_core_setting', '_shopify_core_task', '_shopify_product_faves', '_shopify_product_product', '_shopify_recommendation_recommend', '_shopify_user_service', '_shopify_user_user'];
    foreach ($clients as $client) {
      $client = $client->toArray();
      if (!isset($dbs[$client['db_id']])) {
        $dbAdapterKey = 'ssw_database' . (($client['db_id'] == 1) ? '' : $client['db_id']);
        $dbs[$client['db_id']] = $db = $this->getDI()->get($dbAdapterKey);
      }
      $db = $dbs[$client['db_id']];
      $sql = "SHOW TABLES LIKE '{$client['client_id']}\_%'";
      $tables = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);
      if ($tables) {
        $clientTables = [];
        foreach ($tables as $table) {
          $tableName = array_values($table);
          $clientTables[] = str_replace($client['client_id'], '', $tableName[0]);
        }
        $tablesCount = count($tables);
        if ($tablesCount >= 63) {
          // socialshopwave
          $tableDiffMissing = array_diff($sswTables, $clientTables);
          $tableDiffWaste = array_diff($clientTables, $sswTables);
          $currentTrack['ssw'] += 1;
        } elseif ($tablesCount >= 18) {
          // freeInsta
          $tableDiffMissing = array_diff($freeInstaTables, $clientTables);
          $tableDiffWaste = array_diff($clientTables, $freeInstaTables);
          $currentTrack['freeInsta'] += 1;
        } elseif ($tablesCount >= 13) {
          // instagram
          $tableDiffMissing = array_diff($instaTables, $clientTables);
          $tableDiffWaste = array_diff($clientTables, $instaTables);
          $currentTrack['insta'] += 1;
        } else {
          // free
          $tableDiffMissing = array_diff($freeTables, $clientTables);
          $tableDiffWaste = array_diff($clientTables, $freeTables);
          $currentTrack['insta'] += 1;
        }

//        if (count($tableDiffMissing) > 0) {
//          $currentTrack['missing'][$client['client_id']] = $tableDiffMissing;
//        }
//        if (count($tableDiffWaste) > 0) {
//          $currentTrack['waste'][$client['client_id']] = $tableDiffWaste;
//        }
        $currentTrack['lastClient'] = $client['client_id'];
        $currentTrack['db' . $client['db_id']] += $tablesCount;
      }
    }
    $collection->updateOne(['key' => $key], ['$set' => $currentTrack]);

    $leftCount = SswClients::count([
      'conditions' => 'client_id > ?0 AND new = 0',
      'bind' => [$currentTrack['lastClient']]
    ]);

    echo json_encode([
      'left' => $leftCount,
      'lastClient' => $currentTrack['lastClient']
    ]);
    exit();
  }

  private function checkAllNew()
  {
    /**
     * @var $db DbAdapter
     */
    $db = $this->getDI()->get('ssw_database');
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');


    $sql = "SELECT 
	db.db_id,
	IF (sswClients.shops, sswClients.shops, 0) AS ssw,
	IF (freeInstaClients.shops, freeInstaClients.shops, 0) AS freeInsta,
	IF (instaClients.shops, instaClients.shops, 0) AS insta,
	IF (freeClients.shops, freeClients.shops, 0) AS free
FROM 
(
	SELECT DISTINCT c.db_id FROM shopify_ssw_client AS c ORDER BY c.db_id
) AS db
LEFT JOIN
(
	SELECT c.db_id, COUNT(c.client_id) AS shops FROM shopify_ssw_client AS c
	INNER JOIN shopify_ssw_clientapp AS a 
		ON (c.client_id = a.client_id AND a.app = 'default' AND a.new = 0)
	INNER JOIN (
		SELECT DISTINCT client_id, app, package_id FROM shopify_ssw_subscription WHERE package_id <> 7 AND app = 'default'
	) AS s
		ON (c.client_id = s.client_id)
	GROUP BY c.db_id
) AS sswClients ON (db.db_id = sswClients.db_id)
LEFT JOIN 
(
	SELECT c.db_id, COUNT(c.client_id) AS shops FROM shopify_ssw_client AS c
	INNER JOIN shopify_ssw_clientapp AS a 
		ON (c.client_id = a.client_id AND a.app = 'default' AND a.new = 0 AND a.package_id = 7)
	INNER JOIN shopify_ssw_clientapp AS ai 
		ON (c.client_id = ai.client_id AND ai.app = 'instagram' AND ai.new = 0)	
	LEFT JOIN shopify_ssw_subscription AS s
		ON (c.client_id = s.client_id AND s.app = 'default' AND s.package_id <> 7)
	WHERE db_id >= 25 AND s.subscription_id IS NULL
	GROUP BY c.db_id
) AS freeInstaClients ON (db.db_id = freeInstaClients.db_id)
LEFT JOIN 
(
	SELECT c.db_id, COUNT(c.client_id) AS shops FROM shopify_ssw_client AS c
	INNER JOIN shopify_ssw_clientapp AS a 
		ON (c.client_id = a.client_id AND a.app = 'instagram' AND a.new = 0)
	LEFT JOIN shopify_ssw_clientapp AS e 
		ON (c.client_id = e.client_id AND e.app = 'default' AND e.new = 0)
	WHERE e.clientapp_id IS NULL		
	GROUP BY c.db_id
) AS instaClients ON (db.db_id = instaClients.db_id)
LEFT JOIN 
(
	SELECT c.db_id, COUNT(c.client_id) AS shops FROM shopify_ssw_client AS c
	INNER JOIN shopify_ssw_clientapp AS a 
		ON (c.client_id = a.client_id AND a.app = 'default' AND a.new = 0 AND a.package_id = 7)
	LEFT JOIN shopify_ssw_subscription AS s
		ON (c.client_id = s.client_id AND s.app = 'default' AND s.package_id <> 7)
  LEFT JOIN shopify_ssw_clientapp AS e 
		ON (c.client_id = e.client_id AND e.app = 'instagram' AND e.new = 0)	
	WHERE e.clientapp_id IS NULL AND s.subscription_id IS NULL
	GROUP BY c.db_id
) AS freeClients ON (db.db_id = freeClients.db_id)";
    $db_clients = $db->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);

    $key = date('d_m_Y');
    // Select users collection
    $collection = $mongodb->selectCollection('myaws');

    $currentTrack = $collection->findOne(['key' => $key]);
    if (!$currentTrack) {
      $currentTrack = [
        'key' => $key,
        'lastClient' => 0,
        'ssw' => 0,
        'freeInsta' => 0,
        'free' => 0,
        'insta' => 0,
      ];
      $dbServers = $this->getDbServers();
      $dbIds = array_keys($dbServers);
      foreach ($dbIds as $dbId) {
        $currentTrack['db' . $dbId] = 0;
      }
      $collection->insertOne($currentTrack);
      $currentTrack = $collection->findOne(['key' => $key]);
    }

    $sswTablesCount = 64;
    $freeInstaTablesCount = 18;
    $instaTablesCount = 13;
    $freeTablesCount = 8;

    foreach ($db_clients as $db_client) {
      $tablesCount = 0;
      if (isset($db_client['ssw']) && $db_client['ssw']) {
        $currentTrack['ssw'] = $db_client['ssw'];
        $tablesCount += $sswTablesCount * $db_client['ssw'];
      }
      if (isset($db_client['freeInsta']) && $db_client['freeInsta']) {
        $currentTrack['freeInsta'] = $db_client['freeInsta'];
        $tablesCount += $freeInstaTablesCount * $db_client['freeInsta'];
      }
      if (isset($db_client['insta']) && $db_client['insta']) {
        $currentTrack['insta'] = $db_client['insta'];
        $tablesCount += $instaTablesCount * $db_client['insta'];
      }
      if (isset($db_client['free']) && $db_client['free']) {
        $currentTrack['free'] = $db_client['free'];
        $tablesCount += $freeTablesCount * $db_client['free'];
      }

      $currentTrack['db' . $db_client['db_id']] = $tablesCount;
    }

    $collection->updateOne(['key' => $key], ['$set' => $currentTrack]);

    echo json_encode([
      'left' => 0,
      'lastClient' => 0
    ]);
    exit();
  }

  public function correctTablesAction()
  {
    $key = date('d_m_Y');
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('myaws');
    $currentTrack = $collection->findOne(['key' => $key]);
    if (isset($currentTrack['missing']) && $currentTrack['missing']) {
      print_die($currentTrack['missing']);
    }

    if (isset($currentTrack['waste']) && $currentTrack['waste']) {
      $clientIds = [];
      $allTables = [];
      $tableTypes = [];
      print_die($currentTrack['waste']);
      foreach ($currentTrack['waste'] as $clientId => $tables) {
        $clientIds[] = $clientId;
        foreach ($tables as $table) {
          if (!in_array($table, $tableTypes)) {
            $tableTypes[] = $table;
          }
          $allTables[$clientId][] = $clientId . $table;
        }
      }
      print_die($tableTypes);
      $clientList = SswClients::query()
        ->columns('client_id,db_id')
        ->inWhere('client_id', $clientIds)
        ->execute();
      print_die($clientList->toArray());
    }

    print_die(111);
  }

  public function connectionsAction()
  {
    /**
     * @var DbAdapter $db
     */
    $db = $this->di->get('ssw_database101');
    $items = $db->fetchAll("SHOW FULL PROCESSLIST");
    $commands = [];
    $ids = [];
    foreach ($items as $item) {
      if ($item['Command'] !== 'Sleep' && $item['Info'] !== 'SHOW FULL PROCESSLIST' && $item['db'] === 'shopify_rds2') {
        if (strstr($item['Info'], '39363_shopify_recommendation_recommend')) {
          $commands[] = $item;
          $ids[] = "KILL {$item['Id']};";
        }
      }
    }
    print_arr($commands);
    print_arr(implode("\n\r", $ids));

    print_die(55555);
  }

  public function hahaAction()
  {
    print_arr((isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ? 'https' : 'http');
    print_arr($_SERVER);
    print_die(2222);
  }

  public function taskAction()
  {
    /**
     * @var DbAdapter $db
     */
    $db = $this->di->get('ssw_database101');
    $sql = "SELECT * FROM shopify_rds1.shopify_core_tasklogs 
WHERE start_date BETWEEN UNIX_TIMESTAMP('2018-05-30 00:00:00') AND UNIX_TIMESTAMP('2018-05-30 23:59:59')
UNION 
SELECT * FROM shopify_rds2.shopify_core_tasklogs 
WHERE start_date BETWEEN UNIX_TIMESTAMP('2018-05-30 00:00:00') AND UNIX_TIMESTAMP('2018-05-30 23:59:59')
UNION 
SELECT * FROM shopify_rds3.shopify_core_tasklogs 
WHERE start_date BETWEEN UNIX_TIMESTAMP('2018-05-30 00:00:00') AND UNIX_TIMESTAMP('2018-05-30 23:59:59')";
    $items = $db->fetchAll($sql);
    $timeGroup = array_fill(0, 24, 0);
    foreach ($items as $item) {
      $hour = date('G', $item['start_date']);
      $timeGroup[$hour] += 1;
    }

    print_die($timeGroup);

    print_die(5555);
  }

  public function ganttAction()
  {
    $users = User::find(['status = 1 AND trello_id IS NOT NULL', 'columns' => 'user_id,full_name,trello_id,team_lead']);
    $memberList = [];
    foreach ($users as $user) {
      $memberList[$user->trello_id] = ['user_id' => (int)$user->user_id, 'name' => $user->full_name, 'team' => $user->team_lead];
    }

    $trelloBoardId = '59c3a9fce14da34feebc072b';
    $trelloBoardIdMods = '57da7f53159b4f96129c36f8';
    $trelloBoardIdLogs = '582d25732d82e235fea941b0';
    $monday = new \DateTime('monday this week');
    $start_date = $monday->sub(new \DateInterval('P1W'));
    $friday = new \DateTime('friday this week');
    $end_date = $friday->add(new \DateInterval('P2W'));
    $days = [];
    while ($start_date <= $end_date) {
      $week_day = $start_date->format('D');
      if ($week_day == 'Sat') {
        $start_date->add(new \DateInterval('P2D'));
        $week_day = $start_date->format('D');
      }
      $days[$start_date->format('Y-m-d')] = $week_day;
      $start_date->add(new \DateInterval('P1D'));
    }

    $this->view->setLayout('hd');
    $this->view->days = $days;
    $this->view->points = $this->getPointsData();
    $this->view->timeLines = $this->getTimeLinesData();
    $this->view->trelloBoardId = $trelloBoardId;
    $this->view->trelloBoardIdMods = $trelloBoardIdMods;
    $this->view->trelloBoardIdLogs = $trelloBoardIdLogs;
    $this->view->trelloMembers = $memberList;
  }

  public function timeLinesAction()
  {
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('time-lines');
    $params = $this->getParam('time-line', []);
    $result['status'] = false;

    if (count($params) > 0) {
      $collection->updateOne([
        'member_id' => $params['member_id'],
        'card_id' => $params['card_id']
      ], ['$set' => [
        'member_id' => $params['member_id'],
        'card_id' => $params['card_id'],
        'time_line' => $params['selected_days']
      ]], ['upsert' => true]);

      $result['status'] = true;
      $result['point_data'] = json_decode($this->getTimeLinesData());
    }

    exit(json_encode(['result' => $result]));
  }

  public function updatePointAction()
  {
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('points-data');
    $params = $this->getParam('point-data', []);
    $result['status'] = false;

    if (count($params) > 0) {
      $collection->updateOne([
        'member_id' => $params['member_id'],
        'card_id' => $params['card_id']
      ], ['$set' => [
        'member_id' => $params['member_id'],
        'card_id' => $params['card_id'],
        'point' => $params['point']
      ]], ['upsert' => true]);

      $result['status'] = true;
      $result['point_data'] = json_decode($this->getPointsData());
    }

    exit(json_encode($result));
  }

  public function ganttDevelopersAction()
  {
    if ($this->getParam('operation', false) === 'developers') {
      $users = User::find(['status = 1 AND department = "development" AND trello_id IS NULL', 'columns' => 'user_id,full_name']);
      $memberList = [];
      foreach ($users as $user) {
        $memberList[] = ['id' => $user->user_id, 'name' => $user->full_name];
      }
      echo json_encode($memberList);
      exit();
    }

    if ($this->getParam('operation', false) === 'save_developers') {
      $userId = $this->getParam('user_id');
      $trelloId = $this->getParam('trello_id');
      $user = User::findFirst($userId);
      $user->trello_id = $trelloId;

      echo json_encode(['result' => $user->save()]);
      exit();
    }

    echo json_encode(['result' => 1]);
    exit();
  }

  public function editorAction()
  {
  }

  protected function getPointsData()
  {
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('points-data');
    $pointsData = $collection->find([]);
    $groupedData = [];

    foreach ($pointsData as $pointData) {
      $groupedData[$pointData['member_id']][$pointData['card_id']] = $pointData['point'];
    }

    return json_encode($groupedData);
  }

  protected function getTimeLinesData()
  {
    /**
     * @var $mongodb \Phalcon\Db\Adapter\MongoDB\Database
     */
    $mongodb = $this->getDI()->get('mongodbTracker');
    $collection = $mongodb->selectCollection('time-lines');
    $timeLines = $collection->find([]);
    $groupedData = [];

    foreach ($timeLines as $timeLine) {
      $groupedData[$timeLine['member_id']][$timeLine['card_id']] = (array)$timeLine['time_line'];
    }

    return json_encode($groupedData);
  }

  private function getDbServers()
  {
    $config = $this->getDI()->get('config');
    $serverNum = 0;
    $dbServers = [];
    $currentServer = false;
    foreach ($config as $key => $value) {
      if (substr($key, 0, 12) !== 'ssw_database' || $key == 'ssw_database') {
        continue;
      }
      if ($currentServer != $value->host) {
        $serverNum++;
        $currentServer = $value->host;
      }
      $dbId = substr($key, 12) != '' ? substr($key, 12) : 1;
      $dbServers[$dbId] = $serverNum;
    }

    return $dbServers;
  }
}