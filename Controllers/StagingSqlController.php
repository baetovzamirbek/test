<?php


class StagingSqlController extends AbstractController
{
  public function initialize()
  {
    $this->view->setLayout("sql");
  }

  public function tasksAction()
  {
    $show = $this->getParam('show', 'all');
    $client_id = $this->getParam('client_id', null);
    $format = $this->getParam('format', null);
    /**
     * @var Phalcon\Db\Adapter\Pdo\Mysql $db
     */
    $db = Phalcon\DI::getDefault()->get('staging_database');
    $client_condition = "c.new = 0 AND c.status = 1 AND c.unavailable = 0 AND c.admin_deleted = 0 AND c.package_id <> 0";
    $limit = "LIMIT 100;";
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
      "stacked" => "(t.execute_interval + 600) < (UNIX_TIMESTAMP() - t.executed_date) AND t.execute_status = 'pending' AND UNIX_TIMESTAMP() > t.executed_date AND t.status = 'progress' AND t.executed_date > 0",
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
      $where = $query_where[strtolower($show)]." AND ".$client_condition;
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
        $db = Phalcon\DI::getDefault()->get('staging_database');
        $db->execute($sql);
        $affectedRows = $db->affectedRows();
      }
      exit(json_encode([
        "affectedRows" => $affectedRows
      ]));
    }
  }
}