<?php

/**
 * Created by PhpStorm.
 * User: USER
 * Date: 09.10.2014
 * Time: 15:33
 */
class SqlController extends AbstractController
{
  public function initialize()
  {
    $this->view->setLayout("sql");
  }

  public function dbAction()
  {
    $isAuthorized = Core::isAuthorized('sql');

    $this->view->db_id = $db_id = $this->getParam("db_id", 0);
    $shop_id = $this->getParam("shop_id", 0);

    $client = null;
    if ($shop_id) {
      $shop = Shops::findFirst('shop_id=' . $shop_id);
      if ($shop) {
        $client = SswClients::findFirst($shop->sswclient_id);
        $this->view->sswclient_id = $shop->sswclient_id;
        $this->view->db_id = $db_id = $client->db_id;
      }
    }

    $this->view->tables = $this->getDatabaseCache();

    if (!$isAuthorized) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'auth'));
    }

    $user_id = Core::getViewerId();
    $sqlList = UserSql::find(array('order' => "IF(user_id = {$user_id}, 0, 1), user_id, title"));
    $users = User::find();
    $userList = array();
    foreach ($users as $user) {
      $userList[$user->user_id] = $user;
    }

    $this->view->setVar('value', "");
    $this->view->setVar('sqlList', $sqlList);
    $this->view->setVar('viewer_id', $user_id);
    $this->view->setVar('userList', $userList);
    $this->view->setVar('client', $client);
  }

  public function shopinfoAction()
  {
    $client_id = (int)$this->getParam("client_id", 0);
    $client = SswClients::findFirst($client_id);
    exit(json_encode([
      "shop" => $client ? $client->toArray() : null
    ]));
  }

  public function clientSettingsAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'auth'));
    }
    $client_id = $this->getParam('client_id', 0);
    $filter_text = $this->getParam('filter', "");
    if (!$client_id)
      exit("client_id incorrect");
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */

    $result_errors = array();
    $settings = [];
    $maindb = Phalcon\DI::getDefault()->get('ssw_database');
    $res = $maindb->fetchOne("SELECT * FROM shopify_ssw_client where client_id = {$client_id};");
    if ($res) {
      $dbAdapter = 'ssw_database' . $res['db_id'];
      try {
        $db = Phalcon\DI::getDefault()->get($dbAdapter);
        if (!empty($filter_text)) {
          $filter_text = addslashes($filter_text);
          $sql = "SELECT * from {$client_id}_shopify_core_setting where name like '%{$filter_text}%' OR value like '%{$filter_text}%' limit 200;";
        } else
          $sql = "SELECT * from {$client_id}_shopify_core_setting limit 200;";
        $settings = $db->fetchAll($sql);
        $sql = "SELECT count(*) as count from {$client_id}_shopify_core_setting;";
        $res = $db->fetchOne($sql);
        $this->view->setVar('all_count', $res["count"]);
      } catch (\Exception $e) {
        $result_errors[] = "ERROR '<b>{$e->getCode()}</b>'<br>" . $e->getMessage();
      }
    } else {
      $result_errors[] = "client not found";
    }
    $this->view->setVar('settings', $settings);
    $this->view->setVar('client_id', $client_id);
    $this->view->setVar('stop', count($result_errors));
    $this->view->setVar('filter_text', $filter_text);
    $this->view->errors = $result_errors;
  }

  public function saveSettingAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'auth'));
    }
    $client_id = $this->getParam('client_id', 0);
    $value = $this->getParam('value', "");
    $name = $this->getParam('name', "");
    $old_name = $this->getParam('old_name', "");
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */

    if ($client_id) {
      $result_errors = array();
      $affectedRows = 0;
      $maindb = Phalcon\DI::getDefault()->get('ssw_database');
      $res = $maindb->fetchOne("SELECT * FROM shopify_ssw_client where client_id = {$client_id};");
      $shop = $res['shop'];
      if ($res) {
        $dbAdapter = 'ssw_database' . $res['db_id'];
        try {
          $db = Phalcon\DI::getDefault()->get($dbAdapter);
          $value = $db->escapeString($value);
          $name = str_replace('_', '.', $name);
          $name = $db->escapeString($name);
          $old_name = $db->escapeString($old_name);
          $sql = "SELECT * FROM {$client_id}_shopify_core_setting WHERE name = {$old_name};";
          $setting = $db->fetchOne($sql);
          if ($setting) {
            $sql = "UPDATE {$client_id}_shopify_core_setting SET value = {$value}, name={$name} WHERE name = {$old_name};";
          } else {
            $sql = "INSERT INTO {$client_id}_shopify_core_setting  VALUES({$name},{$value});";
          }
          $db->execute($sql);
          $affectedRows = $db->affectedRows();
          file_get_contents("https://growave.io/lite/test/ermek?shop={$shop}&do=cleanCache&hehe=iamcoolinik");
        } catch (\Exception $e) {
          $result_errors[] = "ERROR 'code:{$e->getCode()} ' message: " . $e->getMessage();
        }
      } else {
        $result_errors[] = "client not found";
      }
    } else
      $result_errors[] = "client_id incorrect";
    exit(json_encode([
      "affectedRows" => $affectedRows,
      "status" => count($result_errors) == 0,
      "errors" => $result_errors,
    ]));
  }

  public function deleteSettingAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'auth'));
    }
    $client_id = $this->getParam('client_id', 0);
    $old_name = $this->getParam('old_name', "");
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */

    if ($client_id) {
      $result_errors = array();
      $affectedRows = 0;
      $maindb = Phalcon\DI::getDefault()->get('ssw_database');
      $res = $maindb->fetchOne("SELECT * FROM shopify_ssw_client where client_id = {$client_id};");
      if ($res) {
        $dbAdapter = 'ssw_database' . $res['db_id'];
        try {
          $db = Phalcon\DI::getDefault()->get($dbAdapter);
          $old_name = $db->escapeString($old_name);
          $sql = "DELETE FROM {$client_id}_shopify_core_setting WHERE name = {$old_name};";
          $db->execute($sql);
          $affectedRows = $db->affectedRows();
        } catch (\Exception $e) {
          $result_errors[] = "ERROR 'code:{$e->getCode()} ' message: " . $e->getMessage();
        }
      } else {
        $result_errors[] = "client not found";
      }
    } else
      $result_errors[] = "client_id incorrect";
    exit(json_encode([
      "affectedRows" => $affectedRows,
      "status" => count($result_errors) == 0,
      "errors" => $result_errors,
    ]));
  }

  public function authAction()
  {
    $this->view->tables = $this->getDatabaseCache();
    $this->view->shop_id = $shop_id = $this->getParam("shop_id", 0);
    $error = false;
    if (Core::isAuthorized('sql')) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'db'));
    }
    if ($this->request->isPost()) {
      if (isset($_POST['secret_key']) && Core::authorize($_POST['secret_key'], 'sql')) {
        return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'db', "shop_id" => $shop_id));
      } else {
        $error = true;
      }
    }
    $this->view->error = $error;
  }

  public function dbResultAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      return $this->dispatcher->forward(array('controller' => 'sql', 'action' => 'auth'));
    }

    $db = $this->getParam('db', 1);
    $sql = $this->getParam('sql', '');

    //delete commented lines
    $lines = explode("\n", $sql);
    $sqlQueries = '';
    foreach ($lines as $line) {
      if (trim($line) == '' || substr(trim($line), 0, 1) == '#') {
        continue;
      }
      $line = trim($line);
      $sqlQueries .= $line . ((substr($line, -1) == ';') ? "ERMEKCS_UNIQ_SQL_SEPARATOR \n" : " \n");
    }

    $stop = false;
    $errors = array();
    $results = array();
    $result_errors = array();
    $sqlList = explode('ERMEKCS_UNIQ_SQL_SEPARATOR', $sqlQueries);

    // IF DUMMY MODE ENABLED
    //check prohibited queries
    /*    $prohibitedWords = array('truncate', 'drop', 'delete');
        foreach ($prohibitedWords as $word) {
          if (stristr($sql, $word) !== false) {
            $stop = true;
            $errors[] = "Prohibited word = '<b>{$word}</b>'";
          }
        }*/

    //check select without where, limit, update and delete without WHERE clause
    foreach ($sqlList as $key => $query) {
      $trimmedQuery = trim($query);
      if ($trimmedQuery == '') {
        unset($sqlList[$key]);
        continue;
      }
      if (stripos($trimmedQuery, 'select') === 0 && stripos($trimmedQuery, 'limit') === false && stripos($trimmedQuery, 'where') === false) {
        $stop = true;
        $errors[] = "Select without limit = '<b>{$trimmedQuery}</b>'";
      }
      if (stripos($trimmedQuery, 'update') === 0 && stripos($trimmedQuery, 'where') === false) {
        $stop = true;
        $errors[] = "Update without where clause = '<b>{$trimmedQuery}</b>'";
      }
      if (stripos($trimmedQuery, 'delete') === 0 && stripos($trimmedQuery, 'where') === false) {
        $stop = true;
        $errors[] = "Delete without where clause = '<b>{$trimmedQuery}</b>'";
      }
    }
    if (!$stop) {
      if ($db == 'crm') {
        $dbAdapter = 'db';
      } else if ($db == 'sendy') {
        $dbAdapter = 'sendy_database';
      } else {
        $dbAdapter = ($db == 1) ? 'ssw_database' : 'ssw_database' . $db;
      }
      /**
       * @var Phalcon\Db\Adapter\Pdo\Mysql $db
       */
      $db = Phalcon\DI::getDefault()->get($dbAdapter);

      foreach ($sqlList as $key => $query) {
        if (strpos(strtolower(trim($query)), 'use ') === 0) {
          $dbName = str_replace(array('use ', 'USE ', "\n"), '', $query);
          $dbAdapter = $this->dbGetDBAdapter($dbName);
          $db = $this->getDI()->get($dbAdapter);
          continue;
        }

        $result = array(
          'query' => $query,
          'columns' => array(),
          'items' => array(),
          'executeTime' => 0
        );
        try {
          $trackQuery = false;
          if (substr(strtolower(trim($query)), 0, 7) == 'update ' || substr(strtolower(trim($query)), 0, 7) == 'delete '
            || substr(strtolower(trim($query)), 0, 7) == 'insert ' || substr(strtolower(trim($query)), 0, 9) == 'truncate '
          ) {
            $startTime = microtime(true);
            $db->execute($query);
            $result['executeTime'] = microtime(true) - $startTime;
            $result['items'][] = array('affectedRows' => (int)$db->affectedRows());
            $trackQuery = true;
          } else if (substr(strtolower(trim($query)), 0, 13) == 'create table ' || substr(strtolower(trim($query)), 0, 12) == 'alter table ') {
            $startTime = microtime(true);
            $db->execute($query);
            $result['executeTime'] = microtime(true) - $startTime;
            $result['items'][] = array('RESULT' => 'Success');
            $trackQuery = false;
          } else {
            $startTime = microtime(true);
            $result['items'] = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
            $result['executeTime'] = microtime(true) - $startTime;
          }

          if ($result['items']) {
            $result['columns'] = array_keys($result['items'][0]);
            if ($trackQuery) {
              $this->trackQuery($query);
            }
          }
        } catch (\Exception $e) {
          $result_errors[$key] = "ERROR IN '<b>{$query}</b>'<br>" . $e->getMessage();
        }

        $results[] = $result;
      }
    }

    $this->view->stop = $stop;
    $this->view->errors = $errors;
    $this->view->results = $results;
    $this->view->result_errors = $result_errors;
  }

  public function dbSaveAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      $goto = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
      header("location:http://{$_SERVER['SERVER_NAME']}/login?authorize=sql&goto=http://{$goto}");
      exit();
    }

    $sql = $this->getParam('sql', '');
    $sql_title = $this->getParam('sql_title', '');
    $sql_id = (int)$this->getParam('sql_id', 0);
    $user_id = Core::getViewerId();

    if (!$sql || !$user_id) {
      exit(0);
    }

    if ($sql_id) {
      $userSql = UserSql::findFirst($sql_id);
    } else {
      $userSql = new UserSql();
      $userSql->user_id = $user_id;
    }

    $userSql->title = $sql_title;
    $userSql->sql = $sql;
    $userSql->save();
    echo $userSql->getIdentity();
    exit();
  }

  public function dbDeleteAction()
  {
    $isAuthorized = Core::isAuthorized('sql');
    if (!$isAuthorized) {
      $goto = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
      header("location:http://{$_SERVER['SERVER_NAME']}/login?authorize=sql&goto=http://{$goto}");
      exit();
    }

    $sql_id = (int)$this->getParam('sql_id', 0);
    $user_id = Core::getViewerId();

    if (!$sql_id || !$user_id) {
      exit(0);
    }

    $userSql = UserSql::findFirst($sql_id);
    $userSql->delete();

    echo 1;
    exit();
  }

  private function getDatabaseCache($new = false, $crm = false, $sendy = false)
  {
    if ($new) {
      /**
       * @var Phalcon\Db\Adapter\Pdo\Mysql $db
       */
      $db = Phalcon\DI::getDefault()->get('ssw_database');
      $query = "SHOW TABLES LIKE 'shopify_%'";
      $tables = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $tableList = array(
        'crm_epic_testmode' => array("charge_id" => null, "name" => null, "shop_url" => null, "shop_id" => null, "client_id" => null, "package_id" => null, "price" => null, "activated_on" => null, "trial_ends_on" => null, "token" => null, "app" => null, "note" => null)
      );
      foreach ($tables as $table) {
        $table = array_values($table);
        $tableName = $table[0];
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'shopify' AND TABLE_NAME = '{$tableName}'";
        $columns = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
        $columnList = array();
        foreach ($columns as $column) {
          $columnList[$column['COLUMN_NAME']] = null;
        }


        $tableList[$tableName] = $columnList;
      }


      $query = "SHOW TABLES LIKE '36\_%'";
      $tables = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $clientTableList = array();
      foreach ($tables as $table) {
        $table = array_values($table);
        $tableName = $table[0];
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'shopify' AND TABLE_NAME = '{$tableName}'";
        $columns = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
        $columnList = array();
        foreach ($columns as $column) {
          $columnList[$column['COLUMN_NAME']] = null;
        }


        $clientTableList[$tableName] = $columnList;
      }

      print_arr(json_encode($tableList));
      print_arr(json_encode($clientTableList));

      print_die(1);
    }

    if ($crm) {
      $db = Phalcon\DI::getDefault()->get('db');
      $query = "SHOW TABLES";
      $tables = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $tableList = array();
      foreach ($tables as $table) {
        $table = array_values($table);
        $tableName = $table[0];
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'crm_ssw' AND TABLE_NAME = '{$tableName}'";
        $columns = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
        $columnList = array();
        foreach ($columns as $column) {
          $columnList[$column['COLUMN_NAME']] = null;
        }


        $tableList[$tableName] = $columnList;
      }

      print_arr(json_encode($tableList));
      print_die(1);
    }

    if ($sendy) {
      $db = Phalcon\DI::getDefault()->get('sendy_database');
      $query = "SHOW TABLES";
      $tables = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $tableList = array();
      foreach ($tables as $table) {
        $table = array_values($table);
        $tableName = $table[0];
        $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'apps_sendy' AND TABLE_NAME = '{$tableName}'";
        $columns = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
        $columnList = array();
        foreach ($columns as $column) {
          $columnList[$column['COLUMN_NAME']] = null;
        }


        $tableList[$tableName] = $columnList;
      }

      print_arr(json_encode($tableList));
      print_die(1);
    }

    $cache = array(
      'all' => '{"crm_epic_testmode":{"charge_id":null,"name":null,"shop_url":null,"shop_id":null,"client_id":null,"package_id":null,"price":null,"activated_on":null,"trial_ends_on":null,"token":null,"app":null,"note":null},"shopify_api_accesstokens":{"access_token":null,"client_id":null,"user_id":null,"expires":null,"scope":null},"shopify_api_authorizationcodes":{"authorization_code":null,"client_id":null,"user_id":null,"redirect_uri":null,"expires":null,"scope":null},"shopify_api_clients":{"client_id":null,"client_secret":null,"redirect_uri":null,"grant_types":null,"scope":null,"user_id":null,"shop_id":null},"shopify_api_refreshtokens":{"refresh_token":null,"client_id":null,"user_id":null,"expires":null,"scope":null},"shopify_api_scopes":{"scope":null,"is_default":null},"shopify_core_followsent":{"id":null,"follower_id":null,"following_id":null,"client_id":null,"sent":null},"shopify_core_maildesign":{"maildesign_id":null,"shop_id":null,"theme_id":null,"logo":null,"title":null,"facebook":null,"facebook_url":null,"twitter":null,"twitter_url":null,"privacy":null,"address":null,"style":null},"shopify_core_mailoriginalcontent":{"mailoriginalcontent_id":null,"mailtemplate_id":null,"mailwidget_id":null,"body":null,"params":null,"order":null},"shopify_core_mailtheme":{"theme_id":null,"title":null,"style":null},"shopify_core_mailwidget":{"mailwidget_id":null,"module":null,"name":null,"static":null,"types":null,"class":null,"label":null,"template_type":null,"editable":null,"deletable":null},"shopify_core_menuitems":{"id":null,"name":null,"label":null,"params":null,"application":null,"menu":null,"icon":null,"order":null},"shopify_core_modulefaqs":{"modulefaqs_id":null,"module_id":null,"title":null,"faq_hash":null},"shopify_core_modulefeatures":{"feature_id":null,"name":null,"module_id":null},"shopify_core_modules":{"module_id":null,"name":null,"title":null,"description":null,"icon":null,"settings":null,"enabled":null,"order":null,"faq_hash":null,"enable_faqs":null},"shopify_core_notificationtype":{"type":null,"body":null,"description":null,"visible":null,"admin_description":null},"shopify_core_tasklogs":{"id":null,"shop_id":null,"task_id":null,"start_date":null,"end_date":null},"shopify_mail_condition":{"condition_id":null,"title":null,"value":null,"params":null,"type":null,"enabled":null},"shopify_ssw_app":{"app_id":null,"title":null,"name":null,"description":null,"icon":null,"settings":null,"order":null,"faq_hash":null,"enable_faq":null,"key":null,"secret":null,"published":null},"shopify_ssw_appfaq":{"appfaq_id":null,"app_name":null,"title":null,"faq_hash":null},"shopify_ssw_client":{"client_id":null,"shop_owner":null,"email":null,"shop":null,"name":null,"token":null,"signature":null,"enabled":null,"creation_date":null,"uninstalled_date":null,"lastlogin_date":null,"lastlogin_ip":null,"new":null,"currency":null,"fbappid":null,"fbsecret":null,"api_count":null,"api_request_date":null,"domain":null,"locale":null,"gplus_client_id":null,"status":null,"db_id":null,"admin_deleted":null,"package_id":null,"ssw":null,"unavailable":null,"unavailable_date":null,"contact_email":null},"shopify_ssw_clientaction":{"action_id":null,"client_id":null,"card_id":null,"action":null},"shopify_ssw_clientapp":{"clientapp_id":null,"client_id":null,"app":null,"token":null,"signature":null,"new":null,"admin_deleted":null,"status":null,"package_id":null,"files":null},"shopify_ssw_discount":{"discount_id":null,"code":null,"trial_days":null,"client_id":null,"app":null,"expiration_date":null,"activated_at":null,"used":null},"shopify_ssw_events":{"event_id":null,"shop_id":null,"event":null,"app":null,"date":null,"shopify_plan":null,"email":null,"our_remove":null},"shopify_ssw_feed":{"card_id":null,"title":null,"description":null,"image_url":null,"condition":null,"type":null,"module":null,"priority":null,"order":null,"params":null,"published":null,"creation_date":null},"shopify_ssw_integration":{"integration_id":null,"client_id":null,"app":null,"type":null,"message":null,"date":null},"shopify_ssw_listener":{"listener_id":null,"client_id":null,"app_id":null,"type":null,"name":null},"shopify_ssw_metatags":{"tag_id":null,"page":null,"value":null,"title":null},"shopify_ssw_package":{"package_id":null,"app":null,"title":null,"description":null,"type":null,"price":null,"trial":null,"enabled":null,"visits":null},"shopify_ssw_page":{"page_id":null,"route":null,"url":null,"content":null,"enabled":null},"shopify_ssw_partner":{"partner_id":null,"name":null,"email":null,"paypal_email":null,"password":null,"url":null,"balance":null,"install":null,"visit":null,"order_count":null},"shopify_ssw_partnerclient":{"id":null,"partner_id":null,"client_id":null},"shopify_ssw_request":{"request_id":null,"displayname":null,"email":null,"website":null,"revenue":null,"message":null,"timestamp":null,"status":null},"shopify_ssw_subscription":{"subscription_id":null,"client_id":null,"app":null,"package_id":null,"charge_id":null,"status":null,"active":null,"creation_date":null,"modified_date":null,"expiration_date":null,"cancellation_date":null,"notes":null},"shopify_ssw_user":{"user_id":null,"email":null,"username":null,"displayname":null,"password":null,"level_id":null,"mobile":null,"enabled":null,"creation_date":null,"modified_date":null,"lastlogin_date":null,"lastlogin_ip":null},"shopify_statistics_events":{"events_id":null,"event":null,"page":null,"prev_page":null,"event_count":null,"users_count":null,"day":null,"resource_id":null,"resource_type":null,"client_id":null},"shopify_statistics_pageviews":{"view_id":null,"date":null,"views_num":null,"client_id":null},"shopify_statistics_share":{"share_id":null,"user_id":null,"type":null,"resource_name":null,"date":null,"hash_key":null,"client_id":null},"shopify_statistics_visitors":{"visitor_id":null,"date":null,"visitors_num":null,"client_id":null}}',
      'client' => '{"36_shopify_core_comment":{"comment_id":null,"user_id":null,"item_id":null,"item_type":null,"parent_id":null,"reply_index":null,"blog_id":null,"status":null,"body":null,"date":null},"36_shopify_core_mailcontent":{"mailcontent_id":null,"mailtemplate_id":null,"mailwidget_id":null,"body":null,"params":null,"order":null},"36_shopify_core_mailmenu":{"mailmenu_id":null,"label":null,"url":null,"order":null},"36_shopify_core_mailtemplate":{"mailtemplate_id":null,"template_type":null,"subject":null,"plain_text":null,"description":null,"original_content":null,"layout":null,"mode":null,"prefixes":null},"36_shopify_core_module":{"module_id":null,"name":null,"enabled":null},"36_shopify_core_notification":{"notification_id":null,"user_id":null,"subject_type":null,"subject_id":null,"object_type":null,"object_id":null,"type":null,"params":null,"read":null,"mitigated":null,"date":null},"36_shopify_core_notificationsetting":{"user_id":null,"type":null,"email":null},"36_shopify_core_setting":{"name":null,"value":null},"36_shopify_core_tagmaps":{"tagmap_id":null,"resource_type":null,"resource_id":null,"tagger_type":null,"tagger_id":null,"tag_id":null,"creation_date":null,"extra":null},"36_shopify_core_tags":{"tag_id":null,"text":null},"36_shopify_core_task":{"task_id":null,"class":null,"method":null,"type":null,"execute_interval":null,"executed_date":null,"execute_status":null,"params":null,"status":null},"36_shopify_core_widgets":{"widgets_id":null,"type":null,"name":null,"collections_enabled":null,"tags_enabled":null,"price_enabled":null,"collections":null,"tags":null,"ipp":null,"paging_enabled":null,"paging_type":null,"code":null,"display_rating":null},"36_shopify_feed_follow":{"follow_id":null,"follower_id":null,"following_id":null,"accepted":null},"36_shopify_feed_imgsize":{"imgsize_id":null,"item_id":null,"dim":null},"36_shopify_feed_item":{"id":null,"object_id":null,"subject_id":null,"action":null,"action_action":null,"timestamp":null,"is_last":null,"item_id":null,"item_subject_id":null,"comment_id":null},"36_shopify_feed_report":{"report_id":null,"object_id":null,"subject_id":null,"creation_date":null,"modified_date":null,"read":null},"36_shopify_feed_stats":{"stats_id":null,"object_id":null,"type":null,"action":null,"cnt":null},"36_shopify_feed_tag":{"tag_id":null,"action_id":null,"action_type":null,"object_id":null,"object_type":null,"user_id":null,"value":null},"36_shopify_feed_wall":{"wall_id":null,"user_id":null,"type":null,"title":null,"description":null,"url":null,"wall_desc":null},"36_shopify_mail_abandons":{"mail_id":null,"checkout_id":null},"36_shopify_mail_checkout":{"checkout_id":null,"email":null,"total_price":null,"currency":null,"checkout_url":null,"date":null},"36_shopify_mail_checkoutprods":{"checkout_id":null,"product_id":null,"variant_id":null,"title":null,"variant_title":null,"quantity":null,"price":null},"36_shopify_mail_income":{"income_id":null,"email":null,"mail_id":null,"date":null},"36_shopify_mail_links":{"links_id":null,"mail_id":null,"link":null,"clicked":null},"36_shopify_mail_mail":{"mail_id":null,"title":null,"description":null,"name":null,"subject":null,"shop_name":null,"reply_to":null,"type":null,"module":null,"min_package":null,"depended_mail_ids":null,"group":null,"creation_date":null,"modified_date":null,"send_date":null,"params":null,"html":null,"sent":null,"template_id":null,"values":null,"activate":null,"max_id":null,"built_in":null},"36_shopify_mail_orders":{"orders_id":null,"income_id":null,"mail_id":null,"order_id":null,"total_price":null,"status":null,"date":null},"36_shopify_mail_queue":{"queue_id":null,"user_id":null,"mail_id":null,"params":null,"send_time":null},"36_shopify_mail_reviewtoken":{"reviewtoken_id":null,"token":null,"user_id":null,"product_id":null,"recommend_id":null},"36_shopify_mail_sent":{"mail_id":null,"user_id":null,"message_id":null,"product_id":null,"params":null,"sent_time":null,"ut":null,"opens":null,"clicks":null,"is_push":null,"pushed":null},"36_shopify_mail_statistics":{"mail_id":null,"recipients":null,"opens":null,"clicks":null,"revenue":null,"bounced":null,"complaint":null,"old_recipients":null,"old_opens":null,"old_clicks":null,"old_bounced":null,"pushed_count":null},"36_shopify_product_cart":{"cart_id":null,"created_at":null,"updated_at":null,"hash_key":null},"36_shopify_product_cartproduct":{"cartproduct_id":null,"cart_id":null,"product_id":null,"variant_id":null,"quantity":null},"36_shopify_product_collection":{"collection_id":null,"title":null,"description":null,"handle":null,"published_date":null,"updated_date":null,"type":null,"synchronized":null,"rules":null,"disjunctive":null,"exists":null},"36_shopify_product_favelist":{"favelist_id":null,"user_id":null,"hash_key":null,"title":null,"parent_id":null,"updated_at":null},"36_shopify_product_faves":{"fave_id":null,"user_id":null,"hash_key":null,"product_id":null,"variant_id":null,"unavailable_options":null,"favelist_id":null,"date":null,"count":null,"selected":null},"36_shopify_product_order":{"order_id":null,"cancel_reason":null,"cancelled_at":null,"cart_token":null,"checkout_token":null,"closed_at":null,"confirmed":null,"created_at":null,"currency":null,"email":null,"financial_status":null,"fulfillment_status":null,"gateway":null,"name":null,"number":null,"subtotal_price":null,"taxes_included":null,"token":null,"total_discounts":null,"total_line_items_price":null,"total_price":null,"total_price_usd":null,"total_tax":null,"total_weight":null,"updated_at":null,"order_number":null,"discount_codes":null,"processing_method":null,"checkout_id":null,"line_items":null,"shipping_lines":null,"payment_details":null,"billing_address":null,"shipping_address":null,"customer":null,"fulfillments":null,"our":null},"36_shopify_product_orderproduct":{"orderproduct_id":null,"user_id":null,"product_id":null,"order_id":null,"variant_id":null,"quantity":null},"36_shopify_product_prodcollection":{"collection_id":null,"product_id":null,"exists":null},"36_shopify_product_product":{"product_id":null,"title":null,"description":null,"image_url":null,"price":null,"compare_price":null,"handle":null,"images":null,"product_type":null,"created_at":null,"published_at":null,"updated_at":null,"tags":null,"variants":null,"options":null,"vendor":null,"synchronized":null,"mobile_show":null},"36_shopify_product_queue":{"product_id":null,"order":null},"36_shopify_recommendation_queue":{"recommend_id":null,"order":null,"pushed":null,"pushed_timestamp":null},"36_shopify_recommendation_recommend":{"recommend_id":null,"product_id":null,"user_id":null,"title":null,"service":null,"service_resource_id":null,"service_resource_type":null,"service_user_id":null,"service_username":null,"body":null,"creation_date":null,"status":null,"rate":null,"new":null,"imported":null,"admin_reply":null,"admin_reply_privacy":null,"vote":null,"unvote":null,"unverified":null,"featured":null,"sync_id":null},"36_shopify_reward_activity":{"activity_id":null,"activity_type":null,"rule_id":null,"user_id":null,"by_user_id":null,"hash_key":null,"order_id":null,"object_type":null,"object_id":null,"earned":null,"spend":null,"creation_time":null,"discount_code":null,"params":null},"36_shopify_service_campaign":{"campaign_id":null,"title":null,"description":null,"type":null,"settings":null,"impression":null,"design":null,"active":null,"creation_date":null,"modified_date":null,"shared":null,"clicks":null,"revenue":null,"orders":null,"coupon_code_type":null},"36_shopify_service_discount":{"discount_id":null,"campaign_id":null,"campaign_ids":null,"code":null,"value":null,"type":null,"starts_at":null,"ends_at":null,"status":null,"usage_limit":null,"minimum_order_amount":null,"applies_to_type":null,"applies_once":null,"applies_to_resource":null,"times_used":null,"times_shown":null,"token":null,"date_creation":null,"title":null,"target_type":null,"allocation_method":null,"customer_selection":null,"target_selection":null,"products_ids":null,"collections_ids":null,"groups_ids":null,"app_type":null,"once_per_customer":null,"prefix":null,"price_rule_id":null,"price_discount_id":null,"unique":null,"unique_amount":null},"36_shopify_service_gallery":{"gallery_id":null,"product_id":null,"title":null,"description":null,"creation_date":null,"auto_approve":null,"new_count":null,"img_count":null,"last_visit":null,"to_update":null,"integrated":null,"display_shop_it":null},"36_shopify_service_galleryimg":{"id":null,"gallery_id":null,"image_id":null},"36_shopify_service_image":{"image_id":null,"low":null,"thumbnail":null,"standard":null,"owner_id":null,"owner_img":null,"owner_username":null,"owner_fullname":null,"object_link":null,"object_type":null,"object_id":null,"likes_count":null,"created_time":null,"comments_count":null,"text":null,"video_url":null,"checked":null,"new":null},"36_shopify_service_imgstat":{"imgstat_id":null,"image_id":null,"view_count":null,"cart_count":null,"time":null},"36_shopify_service_instatag":{"instatag_id":null,"image_id":null,"product_id":null,"x":null,"y":null,"text":null,"link_image_url":null,"link_url":null},"36_shopify_service_orderprods":{"checkout_id":null,"product_id":null,"variant_id":null,"title":null,"variant_title":null,"quantity":null,"price":null,"order_id":null},"36_shopify_service_orders":{"orders_id":null,"order_id":null,"checkout_id":null,"total_price":null,"status":null,"date":null,"email":null,"customer_id":null,"campaign_id":null},"36_shopify_service_tag":{"tag_id":null,"type":null,"id":null,"gallery_id":null,"title":null,"next_url":null,"last_update_time":null,"update_next_url":null,"to_update":null,"has_access":null,"account_id":null,"access_token":null},"36_shopify_service_tagimg":{"id":null,"image_id":null,"tag_id":null,"new":null},"36_shopify_statistics_graphs":{"graph_id":null,"user_id":null,"hash_key":null,"by_user_id":null,"by_hash_key":null,"resource_type":null,"creation_time":null},"36_shopify_statistics_referrals":{"referrals_id":null,"user_id":null,"hash_key":null,"campaign_id":null,"resource_type":null,"resource_name":null,"referral_num":null,"day":null},"36_shopify_statistics_userreferral":{"userreferral_id":null,"user_id":null,"hash_key":null,"resource_type":null,"resource_name":null,"object_type":null,"object_id":null,"views_num":null,"campaign_id":null,"campaign_type":null,"ref_date":null},"36_shopify_statistics_users":{"hash_key":null,"user_id":null,"events":null,"our":null,"expiration":null},"36_shopify_storage_files":{"file_id":null,"parent_id":null,"parent_type":null,"type":null,"user_id":null,"creation_date":null,"modified_date":null,"ext":null,"name":null,"size":null,"hash":null,"path":null},"36_shopify_storage_image":{"image_id":null,"parent_id":null,"parent_type":null,"type":null,"user_id":null,"creation_date":null,"modified_date":null,"ext":null,"name":null,"size":null,"path":null},"36_shopify_user_banned":{"banned_id":null,"customer_id":null,"email":null},"36_shopify_user_service":{"id":null,"user_id":null,"service_user_id":null,"account":null,"access_token":null,"access_secret":null,"name":null,"gender":null,"birthday":null,"url":null,"thumbnail_url":null,"email":null,"location":null,"service":null,"active":null,"timeline":null,"can_fave":null,"can_review":null,"can_purchase":null,"rest_data":null},"36_shopify_user_socialsharing":{"socialsharing_id":null,"label":null,"description":null,"value":null},"36_shopify_user_user":{"user_id":null,"customer_id":null,"email":null,"first_name":null,"last_name":null,"profile_address":null,"birthdate":null,"gender":null,"creation_date":null,"creation_ip":null,"modified_date":null,"lastlogin_date":null,"lastlogin_ip":null,"update_date":null,"password":null,"about":null,"facebook":null,"twitter":null,"instagram":null,"pinterest":null,"tumblr":null,"gplus":null,"yahoo":null,"amazon":null,"privacy":null,"level":null,"featured":null,"show_follow_popup":null,"default_address":null,"share_purchase":null,"banned":null,"discount_login_showed":null,"cart_token":null,"our":null,"state":null,"avatar":null,"last_login_service":null,"vu":null,"vb":null,"multipass_identifier":null,"points":null,"tier_id":null,"refer_link":null,"accepts_marketing":null,"tags":null,"verified":null,"points_expiration_date":null}}',
      'crm' => '{"crm_assigns":{"staff_id":null,"ticket_id":null},"crm_campaign_status":{"status_id":null,"shop_id":null,"campaign_id":null,"status":null,"date":null,"email":null,"unsubscribe_token":null},"crm_campaigns":{"campaign_id":null,"type":null,"subject":null,"body":null,"status":null},"crm_client":{"client_id":null,"email":null,"name":null,"note":null,"creation_date":null,"type":null,"follow_up":null,"follow_acted":null,"follow_up_note":null},"crm_epic_testmode":{"charge_id":null,"name":null,"shop_url":null,"shop_id":null,"client_id":null,"package_id":null,"price":null,"activated_on":null,"trial_ends_on":null,"token":null,"app":null},"crm_epic_testmode2":{"charge_id":null,"name":null,"shop_url":null,"shop_id":null,"client_id":null,"package_id":null,"price":null,"activated_on":null,"trial_ends_on":null,"token":null,"app":null},"crm_featurelabels":{"id":null,"shop_id":null,"password_protected":null,"account_disabled":null,"changed_design":null,"incorrect_uninstall":null,"top_login_bar":null,"native_login":null,"reviews_app":null,"reviews_tab":null,"review_widget_product":null,"wishlist":null,"social_feed":null,"mobile_version":null,"personalized_widgets":null,"listing_enabled":null,"mobile_enabled":null,"ask_friends_enabled":null,"feed_enabled":null,"reviews_enabled":null,"faves_enabled":null,"social_login_enabled":null},"crm_features":{"feature_id":null,"title":null,"label_name":null,"description":null,"params":null},"crm_file":{"file_id":null,"parent_id":null,"parent_type":null,"type":null,"client_id":null,"staff_id":null,"creation_date":null,"updated_date":null,"ext":null,"name":null,"size":null,"hash":null,"path":null},"crm_history":{"history_id":null,"lead_id":null,"type":null,"created":null},"crm_lead":{"lead_id":null,"name":null,"tw_url":null,"fb_url":null,"site_url":null,"list_id":null,"following":null,"followers":null,"status":null,"created":null,"note":null,"last_checked_time":null,"checked_count":null},"crm_modifications":{"id":null,"user_id":null,"shop_id":null,"file":null,"value":null,"old_value":null,"date":null},"crm_notification":{"notification_id":null,"type":null,"recipient_id":null,"ticket_id":null,"params":null,"creation_date":null,"updated_date":null,"email":null,"gtalk":null},"crm_notificationsetting":{"user_id":null,"type":null},"crm_notificationtype":{"key":null,"subject":null,"body":null,"gtalk":null,"vars":null,"description":null},"crm_password_recovery":{"password_recovery_id":null,"user_id":null,"code":null,"timestamp":null},"crm_payment":{"payment_id":null,"sswclient_id":null,"payment_date":null,"creation_date":null,"app":null,"period":null,"domain":null,"share":null,"status":null},"crm_post":{"post_id":null,"ticket_id":null,"text":null,"staff_id":null,"type":null,"message_id":null,"creation_date":null,"message_body":null,"subject":null},"crm_settings":{"name":null,"value":null},"crm_shopifyapp":{"app_id":null,"url":null},"crm_shopifyapplicationrates":{"app_id":null,"updated_time":null,"position":null,"reviews":null},"crm_shopifyapplications":{"app_id":null,"key":null,"name":null,"description":null,"image":null,"price":null,"reviews":null},"crm_shops":{"shop_id":null,"name":null,"note":null,"url":null,"owner":null,"primary_email":null,"domain":null,"access_token":null,"sswclient_id":null,"status":null,"last_update":null,"deleted_date":null,"modified":null,"reviewed":null,"removed":null},"crm_shopstaffs":{"shop_id":null,"client_id":null,"type":null},"crm_template":{"template_id":null,"keyword":null,"body":null},"crm_ticket":{"ticket_id":null,"client_id":null,"subject":null,"status":null,"message_id":null,"creation_date":null,"updated_date":null,"app":null},"crm_tickettemplates":{"template_id":null,"type":null,"app":null,"subject":null,"html":null,"plain":null},"crm_twlist":{"list_id":null,"name":null,"status":null,"count":null,"created":null},"crm_unsubscribe":{"unsubscribe_id":null,"email":null,"date":null},"crm_user":{"user_id":null,"email":null,"password":null,"full_name":null,"department":null,"creation_timestamp":null,"status":null,"update_timestamp":null,"signature":null,"signature_post":null,"signature_html":null,"twitter":null,"slack":null},"crm_user_invite":{"user_invite_id":null,"email":null,"code":null,"timestamp":null},"crm_user_sql":{"sql_id":null,"user_id":null,"title":null,"sql":null}}',
      'logger' => '{"track_shops":{"shop_id":null,"creation_stamp":null,"webhook":null,"task":null,"app":null,"mobile":null,"total":null}}',
      'sendy' => '{"apps":{"id":null,"userID":null,"app_name":null,"from_name":null,"from_email":null,"reply_to":null,"currency":null,"delivery_fee":null,"cost_per_recipient":null,"smtp_host":null,"smtp_port":null,"smtp_ssl":null,"smtp_username":null,"smtp_password":null,"bounce_setup":null,"complaint_setup":null,"app_key":null,"allocated_quota":null,"current_quota":null,"day_of_reset":null,"month_of_next_reset":null,"test_email":null,"brand_logo_filename":null,"allowed_attachments":null},"ares":{"id":null,"name":null,"type":null,"list":null,"custom_field":null},"ares_emails":{"id":null,"ares_id":null,"from_name":null,"from_email":null,"reply_to":null,"title":null,"plain_text":null,"html_text":null,"query_string":null,"time_condition":null,"timezone":null,"created":null,"recipients":null,"opens":null,"wysiwyg":null},"campaigns":{"id":null,"userID":null,"app":null,"from_name":null,"from_email":null,"reply_to":null,"title":null,"label":null,"plain_text":null,"html_text":null,"query_string":null,"sent":null,"to_send":null,"to_send_lists":null,"recipients":null,"timeout_check":null,"opens":null,"wysiwyg":null,"send_date":null,"lists":null,"timezone":null,"errors":null,"bounce_setup":null,"complaint_setup":null},"links":{"id":null,"campaign_id":null,"ares_emails_id":null,"link":null,"clicks":null},"lists":{"id":null,"app":null,"userID":null,"name":null,"opt_in":null,"confirm_url":null,"subscribed_url":null,"unsubscribed_url":null,"thankyou":null,"thankyou_subject":null,"thankyou_message":null,"goodbye":null,"goodbye_subject":null,"goodbye_message":null,"confirmation_subject":null,"confirmation_email":null,"unsubscribe_all_list":null,"custom_fields":null,"prev_count":null,"currently_processing":null,"total_records":null},"login":{"id":null,"name":null,"company":null,"username":null,"password":null,"s3_key":null,"s3_secret":null,"api_key":null,"license":null,"timezone":null,"tied_to":null,"app":null,"paypal":null,"cron":null,"cron_ares":null,"send_rate":null,"language":null,"cron_csv":null,"ses_endpoint":null},"queue":{"id":null,"query_str":null,"campaign_id":null,"subscriber_id":null,"sent":null},"subscribers":{"id":null,"userID":null,"name":null,"email":null,"custom_fields":null,"list":null,"unsubscribed":null,"bounced":null,"bounce_soft":null,"complaint":null,"last_campaign":null,"last_ares":null,"timestamp":null,"join_date":null,"confirmed":null,"messageID":null},"template":{"id":null,"userID":null,"app":null,"template_name":null,"html_text":null}}'
    );

    return $cache;
  }

  private function dbGetDBAdapter($dbName)
  {
    $dbAdapter = 'db';
    $dbName = trim($dbName);
    if (strstr($dbName, 'shopify') !== false) {
      $dbAdapter = str_replace('shopify', 'ssw_database', $dbName);
    } elseif ($dbName == 'crm_ssw' || $dbName == 'crm') {
      $dbAdapter = 'db';
    } elseif ($dbName == 'sendy') {
      $dbAdapter = 'sendy_database';
    }

    return trim($dbAdapter, ';');
  }

  public function tasksAction()
  {
    $show = $this->getParam('show', 'all');
    $client_id = $this->getParam('client_id', null);
    $format = $this->getParam('format', null);
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $db = Phalcon\DI::getDefault()->get('ssw_database');
    $client_condition = "c.new = 0 AND c.status = 1 AND c.unavailable = 0 AND c.admin_deleted = 0 AND c.package_id <> 0";
    $limit = "LIMIT 500;";
    $shop = "";
    if ($client_id) {
      $client = $db->fetchOne("SELECT * FROM shopify_ssw_client where client_id = {$client_id};");
      if ($client) {
        $client_condition = "t.client_id = {$client['client_id']}";
        $shop = $client['shop'];
      }
    }

    $query_where = [
      "all" => "1",
      "in_progress" => "t.execute_status = 'pending' AND (t.execute_interval + 600) > (UNIX_TIMESTAMP() - t.executed_date)",
      "done" => "t.status = 'done'",
      "error" => "t.status = 'error'",
      "in_queue" => "t.execute_interval < (UNIX_TIMESTAMP() - t.executed_date) AND t.execute_status <> 'pending' AND  t.status = 'progress'",
      "stacked" => "(t.execute_interval + 600) < (UNIX_TIMESTAMP() - t.executed_date) AND t.execute_status = 'pending' AND UNIX_TIMESTAMP() > t.executed_date AND t.status = 'progress'",
    ];
    $query_fields = [
      "COUNT(*) as all_count",
      "sum(IF( {$query_where['in_progress']} ,1,0)) as in_progress",
      "sum(IF({$query_where['done']},1,0)) as done",
      "sum(IF({$query_where['error']},1,0)) as error",
      "sum(IF({$query_where['in_queue']},1,0)) as in_queue",
      "sum(IF({$query_where['stacked']},1,0)) as stacked"
    ];

    $fields = implode(',', $query_fields);
    $sql = "SELECT {$fields} FROM shopify_core_task as t INNER JOIN shopify_ssw_client as c ON (t.client_id = c.client_id) WHERE {$client_condition};";
    $baseListSql = "SELECT t.* FROM shopify_core_task t INNER JOIN shopify_ssw_client as c ON (t.client_id = c.client_id)";

    $show_sql = "";
    if (isset($query_where[strtolower($show)])) {
      $where = $query_where[strtolower($show)] . " AND " . $client_condition;
      $show_sql = $baseListSql . " WHERE {$where} $limit";
    }

    $startTime = microtime(true);
    try {
      $result['tasks_info'] = $db->fetchOne($sql, Phalcon\Db::FETCH_ASSOC);
      $result['tasks_info'] = [
        'all_count' => intval($result['tasks_info']['all_count']),
        'in_progress' => intval($result['tasks_info']['in_progress']),
        'done' => intval($result['tasks_info']['done']),
        'error' => intval($result['tasks_info']['error']),
        'in_queue' => intval($result['tasks_info']['in_queue']),
        'stacked' => intval($result['tasks_info']['stacked']),
      ];
    } catch (\Exception $exception) {
      print_die($exception->getMessage());
    }
    $result['execute_time'] = microtime(true) - $startTime;

    if ($format == 'json') {
      exit(json_encode($result));
    }

    $tasks = [];
    if ($show_sql) {
      $tasks = $db->fetchAll($show_sql, Phalcon\Db::FETCH_ASSOC);
    }

    $pathname = explode('?', $_SERVER['REQUEST_URI'])[0];
    $this->view->setLayout("tasks");
    $client_id_params = $client_id ? "&client_id=$client_id" : "";


    $this->view->action_url = "/" . $this->dispatcher->getControllerName() . "/applyAction";

    $this->view->filter_urls = [
      "error" => $pathname . "?show=error" . $client_id_params,
      "stacked" => $pathname . "?show=stacked" . $client_id_params,
      "all" => $pathname . "?show=all" . $client_id_params,
      "done" => $pathname . "?show=done" . $client_id_params,
      "in_queue" => $pathname . "?show=in_queue" . $client_id_params,
      "in_progress" => $pathname . "?show=in_progress" . $client_id_params,
    ];

    $this->view->result = $result;
    $this->view->tasks = $tasks;
    $this->view->count_tasks = count($tasks);
    $this->view->tasks_title = $show;
    $this->view->shop = $shop;
    //print_die("tasks");
  }

  public function applyActionAction()
  {
    if ($this->request->isPost()) {
      $action = $this->getParam('action', 'all');
      $tasks = $this->getParam('tasks', null);
      $affectedRows = 0;
      if ($tasks && is_array($tasks) && count($tasks)) {
        $sql = "";
        $ids = implode(',', $tasks);
        $where = " WHERE task_id IN($ids)";
        switch ($action) {
          case "start":
            $sql = "UPDATE shopify_core_task SET executed_date = 0, params = '', status = 'progress', execute_status='completed'" . $where;
            break;
          case "stop":
            $sql = "UPDATE shopify_core_task SET params = '', status = 'done', execute_status='completed'" . $where;
            break;
          case "delete":
            $sql = "DELETE FROM shopify_core_task" . $where;
            break;
        }
        /**
         * @var Phalcon\Db\Adapter\Pdo\Mysql $db
         */
        $db = Phalcon\DI::getDefault()->get('ssw_database');
        $db->execute($sql);
        $affectedRows = $db->affectedRows();
      }
      exit(json_encode([
        "affectedRows" => $affectedRows
      ]));
    }
  }

  public function debugTaskAction()
  {
    $this->view->setLayout("dev-projects");
    $client_id = intval($this->getParam('client_id', 0));
    $task_id = $this->getParam('task_id', null);
    if (!$client_id) {
      print_die("param client_id required!");
    }
    $db = Phalcon\DI::getDefault()->get('ssw_database');

    $client = $db->fetchOne("SELECT * FROM shopify_ssw_client where client_id = {$client_id};");
    if (!$client) {
      print_die("client_id invalid!");
    }

    $sql = "SELECT * FROM shopify_core_task  WHERE client_id = {$client_id};";
    $this->view->tasks = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $this->view->task_id = $task_id;
    $this->view->shop = $client['shop'];
    $this->view->client_id = $client['client_id'];
  }

  public function debugTaskRunAction()
  {
    $this->view->setLayout("dev-projects");
    $task_id = intval($this->getParam('task_id', 0));
    $project = $this->getParam('project', '');
    $shop = $this->getParam('shop', null);
    $projects = [
      'live' => 'https://growave.io/lite/tasks/singleRun',
      'dev' => 'https://dev.growave.io/lite/tasks/singleRun',
      'off' => 'https://off.growave.io/lite/tasks/singleRun',
    ];
    $data = [
      'code' => generateUniqueCode(),
      'task_id' => $task_id,
      'debug' => 1
    ];
    if (!$task_id || !$shop) {
      print_die("params task_id and shop required!");
    }
    if (!isset($projects[$project])) {
      print_die("param project required!");
    }
    $db = Phalcon\DI::getDefault()->get('ssw_database');
    $task = $db->fetchOne("SELECT * FROM shopify_core_task  WHERE task_id = {$task_id};");

    if (!isset($projects[$project])) {
      print_die("task not found!");
    }

    if ($task['method'] == 'autoresponder') {
      $projects = [
        'live' => 'https://growave.io/lite2/mail/task',
        'dev' => 'https://dev.growave.io/lite2/mail/task',
        'off' => 'https://off.growave.io/lite2/mail/task',
      ];
      $data = [
        "from" => 'hehehe',
        'debug' => 1,
      ];
    }

    $url = $projects[$project] . '?shop=' . $shop;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $task = $db->fetchOne("SELECT * FROM shopify_core_task  WHERE task_id = {$task_id};");

    exit(json_encode([
      "response" => $server_output,
      "code" => $http_code,
      "task_params" => json_encode($task,JSON_PRETTY_PRINT),
    ]));
  }

  public function taskAnalyticsAction()
  {
    $this->view->setLayout("task-analytics");
    $taskLogsCollection = new TaskLogs();
//    $taskLogsCollection->setConnectionService('mongodbTrackerDev');

    $minDate = $this->getParam('min_date', '-3 hours');
    $minTime = strtotime($minDate);
    $maxDate = $this->getParam('max_date', '');
    $maxTime = $maxDate ? strtotime($maxDate) : 0;
    $intervalValue = intval($this->getParam('interval_value', 5));
    $intervalType = $this->getParam('interval_type', 'minutes');
    $selectedTasks = $this->getParam('selected_tasks', []);

    $match = [
      'executedDate' => [
        '$gte' => new \MongoDB\BSON\UTCDateTime($minTime * 1000)
      ]
    ];
    if ($maxTime > 0) {
      $match['executedDate']['$lte'] = new \MongoDB\BSON\UTCDateTime($maxTime * 1000);
    }

    $aggregateInterval = [];
    $groupId = [];
    if ($intervalType == 'minutes') {
      $groupId = [
        'year' => [
          '$year' => '$executedDate'
        ],
        'dayOfYear' => [
          '$dayOfYear' => '$executedDate'
        ],
        'hour' => [
          '$hour' => '$executedDate'
        ],
        'class' => '$class',
        'method' => '$method'
      ];
      $aggregateInterval = [
        '$subtract' => [
          [
            '$minute' => '$executedDate'
          ],
          [
            '$mod' => [
              [
                '$minute' => '$executedDate'
              ],
              $intervalValue
            ]
          ]
        ]
      ];
    } elseif ($intervalType == 'hours') {
      $groupId = [
        'year' => [
          '$year' => '$executedDate'
        ],
        'dayOfYear' => [
          '$dayOfYear' => '$executedDate'
        ],
        'class' => '$class',
        'method' => '$method'
      ];
      $aggregateInterval = [
        '$subtract' => [
          [
            '$hour' => '$executedDate'
          ],
          [
            '$mod' => [
              [
                '$hour' => '$executedDate'
              ],
              $intervalValue
            ]
          ]
        ]
      ];
    }
    $groupId['interval'] = $aggregateInterval;
    $rows = $taskLogsCollection::aggregate([
      [
        '$match' => $match
      ],
      [
        '$group' => [
          '_id' => $groupId,
          "executedDate" => [
            '$first' => '$executedDate'
          ],
          'count' => [
            '$sum' => 1
          ]
        ]
      ],
      [
        '$sort' => ['executedDate' => 1]
      ]
    ])->toArray();

    $tasksForFilter = [
      'Task.importUsers',
      'Task.importProducts',
      'Task.importOrders',
      'Task.checkCollections',
      'Task.checkProducts',
      'Task.synchronizeProducts',
      'Task.importCollections',
      'Task.checkQueue',
      'Task.productMetafields',
      'Task.checkLocale',
      'Task.updateInstagramGallery',
      'Task.checkSubscriptionChargeStatus',
      'Task.recommendationSocialPush',
      'Task.updateInstagramImages',
      'Task.sswEducationEmails',
      'Task.recommendsReport',
      'Task.instagramCheckIntegration',
      'Task.rewardExistingMembers',
      'Task.rewardBirthdayGift',
      'Task.pointsExpiration',
      'Task.pushNotifications',
      'Task.autoresponder',
      'JobSswAppUsage.checkAndCharge',
      'JobFaves.removeOldWishlistByQuests'
    ];


    $tasks = [
      'All'
    ];

    if (count($selectedTasks) > 0) {
      $tasks = array_merge($tasks, $selectedTasks);
      $tasks[] = 'Other';
    }

    $tasksExecutedCounts = [];
    foreach ($tasks as $task) {
      $tasksExecutedCounts[] = [
        'name' => $task,
        'data' => []
      ];
    }
    $tasksExecutedDates = [];

    if ($intervalType == 'minutes') {
      $minTimeMinutes = intval(date('i', $minTime));
      $modOfInterval = $minTimeMinutes % $intervalValue;
      if ($modOfInterval > 0) {
        $tasksExecutedDates[] = date('Y-m-d H:i', $minTime + 21600); // 21600 - Our Bishkek timezone
        foreach ($tasksExecutedCounts as $index => $countItem) {
          $tasksExecutedCounts[$index]['data'][] = 0;
        }
        $minTime += ($intervalValue - ($minTimeMinutes % $intervalValue)) * 60;
      }
      $now = time();
      for ($i = $minTime; $i < $now; $i += ($intervalValue * 60)) {
        $tasksExecutedDates[] = date('Y-m-d H:i', $i + 21600); // 21600 - Our Bishkek timezone
        foreach ($tasksExecutedCounts as $index => $countItem) {
          $tasksExecutedCounts[$index]['data'][] = 0;
        }
      }
    } elseif ($intervalType == 'hours') {
      $minTimeMinutes = intval(date('i', $minTime));
      $tasksExecutedDates[] = date('Y-m-d H:i', $minTime + 21600); // 21600 - Our Bishkek timezone
      foreach ($tasksExecutedCounts as $index => $countItem) {
        $tasksExecutedCounts[$index]['data'][] = 0;
      }
      if ($minTimeMinutes > 0) {
        $startTime = $minTime - ($minTimeMinutes * 60) + ($intervalValue * 3600);
      } else {
        $startTime = $minTime + ($intervalValue * 3600);
      }
      $now = time();
      for ($i = $startTime; $i <= $now; $i += ($intervalValue * 3600)) {
        $tasksExecutedDates[] = date('Y-m-d H:i', $i + 21600); // 21600 - Our Bishkek timezone
        foreach ($tasksExecutedCounts as $index => $countItem) {
          $tasksExecutedCounts[$index]['data'][] = 0;
        }
      }
    }

    $tasksCount = count($tasks);
    foreach ($rows as $row) {
      $datetime = $row['executedDate']->toDateTime();
      $datetime->modify('+ 6 hours');
      $tasksExecutedDate = $datetime->format('Y-m-d H:i');
      $tasksExecutedDateIndex = array_search($tasksExecutedDate, $tasksExecutedDates);
      if ($tasksCount == 1) {
        $taskName = 'All';
        $taskIndex = array_search($taskName, $tasks);
      } else {
        $class = $row['_id']->{'class'};
        $method = $row['_id']->{'method'};
        $taskName = "{$class}.{$method}";
        $taskIndex = array_search($taskName, $tasks);
        if (is_null($taskIndex) || $taskIndex === false) {
          $taskName = 'Other';
          $taskIndex = array_search($taskName, $tasks);
        }
      }
      if ($tasksExecutedDateIndex !== null && $tasksExecutedDateIndex !== false && $taskIndex !== null && $taskIndex !== false) {
        $tasksExecutedDates[$tasksExecutedDateIndex] = $tasksExecutedDate;
        $tasksExecutedCounts[$taskIndex]['data'][$tasksExecutedDateIndex] += $row['count'];
      }
    }

    // Calculate all statistics (sum)
    if ($tasksCount > 1) {
      for ($i = 0; $i < count($tasksExecutedCounts[0]['data']); $i++) {
        $allTaskExecutedCount = 0;
        for ($j = 1; $j < count($tasksExecutedCounts); $j++) {
          $allTaskExecutedCount += $tasksExecutedCounts[$j]['data'][$i];
        }
        $tasksExecutedCounts[0]['data'][$i] = $allTaskExecutedCount;
      }
    }

    // Calculate total counts
    for ($i = 0; $i < count($tasksExecutedCounts); $i++) {
      $totalCount = 0;
      for ($j = 0; $j < count($tasksExecutedCounts[$i]['data']); $j++) {
        $totalCount += $tasksExecutedCounts[$i]['data'][$j];
      }
      $tasksExecutedCounts[$i]['name'] .= " (Total: {$totalCount})";
    }

    // Other statistics count
    if ($tasksCount > 1) {
      unset($tasksExecutedCounts[$tasksCount - 1]);
    }

    $this->view->setVar('tasksForFilter', $tasksForFilter);
    $this->view->setVar('selectedTasks', $selectedTasks);
    $this->view->setVar('tasksExecutedDates', $tasksExecutedDates);
    $this->view->setVar('tasksExecutedCounts', $tasksExecutedCounts);
    $this->view->setVar('intervalType', $intervalType);
    $this->view->setVar('intervalValue', $intervalValue);
    $this->view->setVar('minDate', $minDate);
    $this->view->setVar('maxDate', $maxDate);
  }

  private function trackQuery($query)
  {
    $trimmedQuery = trim($query);
    /** @var \Phalcon\Db\Adapter\MongoDB\Database $mongodb */
    $mongodb = $this->getDI()->get('mongodbTracker');
    $sqlLogsCollection = $mongodb->selectCollection('crm_sql_logs');
    $sqlLogsCollection->insertOne([
      'user_id' => intval(Core::getViewerId()),
      'query' => $trimmedQuery,
      'executed_date' => new \MongoDB\BSON\UTCDateTime()
    ]);
  }
}