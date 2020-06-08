<?php

/**
 * Created by PhpStorm.
 * User: RAVSHAN
 * Date: 14.11.2017
 * Time: 13:52
 */
class DevupdateController extends AbstractController
{

  public function indexAction()
  {
    $task_methods = [
      'updateSql',
      'updateThemesLiquid',
      'updateStyle',
      'updateScriptTags',
      'insertLocaleAndVars',
      'addFbSettings',
      'addGPlusSettings',
      'clearReviewsCache',
      'updateWebhooks',
      'updatePages',
      'updateTasks',
      'resetModelsMetaData',
      'migrateTasks',
    ];

    $task_client_statuses = [
      'all' => 'All',
      1 => 'Active',
      0 => 'Loosing'
    ];

    $task_client_apps = [
      'all' => 'All',
      'default' => 'SocialShopWave',
      'instagram' => 'Instagram'
    ];

    $updateTaskCollection = new UpdateTasks();
    $updateTaskCollection->setConnectionService('mongodbCacheDev');
    $tasks = $updateTaskCollection::find(
      [
        "sort" => [
          "created_at" => -1,
        ]
      ]
    );

    $this->view->setVars([
      'task_methods' => $task_methods,
      'task_client_statuses' => $task_client_statuses,
      'task_client_apps' => $task_client_apps,
      'tasks' => $tasks
    ]);
  }

  public function taskAction()
  {
    // Create or update task
    if ($this->request->isPost()) {
      $updateTaskCollection = new UpdateTasks();
      $updateTaskCollection->setConnectionService('mongodbCacheDev');
      $_id = $this->getParam('_id', '');
      $additional_data = [];
      $time = time();
      if ($_id) {
        $task = $updateTaskCollection::findById($_id);
      } else {
        $task = new UpdateTasks();
        $task->setConnectionService('mongodbCacheDev');
        $additional_data = [
          'status' => 0,
          'created_at' => $time
        ];
      }
      if ($task) {
        $data = [
          'title' => $this->getParam('title', ''),
          'client_status' => $this->getParam('client_status', 'all'),
          'client_app' => $this->getParam('client_app', 'all'),
          'methods' => json_encode($this->getParam('methods', [])),
          'modified_at' => $time
        ];
        if ($additional_data) {
          $data = array_merge($data, $additional_data);
        }
        $task->save($data);
        $response = [
          'success' => true,
          'item' => $task->toArray()
        ];
        $response['item']['_id'] = (string)$task->getId();
        exit(json_encode($response));
      } else {
        exit(json_encode(['success' => false]));
      }
    } else {
      exit(json_encode(['success' => false]));
    }
  }

  public function taskToggleAction()
  {
    // Start, Stop or Restart task
    if ($this->request->isPost()) {
      $_id = $this->getParam('_id', '');
      if ($_id) {
        $updateTaskCollection = new UpdateTasks();
        $updateTaskCollection->setConnectionService('mongodbCacheDev');
        $task = $updateTaskCollection::findById($_id);
        if ($task) {
          $do = $this->getParam('do', '');
          if ($do == 'start') {
            $task->status = 1;
            /**
             * @var $db Phalcon\Db\Adapter\MongoDB\Database
             */
            $db = $this->getDI()->get('mongodbCacheDev');
            $collection = $db->selectCollection('update_task_shops');
            $shop_count = $collection->count(['task_id' => $_id]);
            if (!$shop_count) {
              $this->resetTaskClients($task);
            } else {
              $failed_count = $collection->count(['task_id' => $_id, 'status' => 'failed']);
              if ($failed_count) {
                // Re execute task for failed shops
                $collection->updateMany(['task_id' => $_id, 'status' => 'failed'], ['$set' => ['status' => 'progress', 'modified_at' => time()]]);
              }
            }
          } elseif ($do == 'stop') {
            $task->status = 0;
            // Pending task shops to progress
            $this->resetPendingTaskClients($task);
          } elseif ($do == 'restart') {
            $task->status = 1;
            $this->resetTaskClients($task);
          } else {
            exit(json_encode(['success' => false]));
          }
          $task->save();
          exit(json_encode(['success' => true]));
        } else {
          exit(json_encode(['success' => false]));
        }
      } else {
        exit(json_encode(['success' => false]));
      }
    } else {
      exit(json_encode(['success' => false]));
    }
  }

  public function deleteTaskAction()
  {
    // Start, Stop or Restart task
    if ($this->request->isPost()) {
      $_id = $this->getParam('_id', '');
      if ($_id) {
        $updateTaskCollection = new UpdateTasks();
        $updateTaskCollection->setConnectionService('mongodbCacheDev');
        $task = $updateTaskCollection::findById($_id);
        if ($task) {
          /**
           * @var $db Phalcon\Db\Adapter\MongoDB\Database
           */
          $task_id = (string)$task->getId();
          $db = $this->getDI()->get('mongodbCacheDev');
          $collection = $db->selectCollection('update_task_shops');
          $collection->deleteMany(['task_id' => $task_id]);
          $task->delete();
          exit(json_encode(['success' => true]));
        } else {
          exit(json_encode(['success' => false]));
        }
      } else {
        exit(json_encode(['success' => false]));
      }
    } else {
      exit(json_encode(['success' => false]));
    }
  }

  public function taskReportsAction()
  {
    $success = false;
    $_id = $this->getParam('_id', '');
    if ($_id) {
      $updateTaskCollection = new UpdateTasks();
      $updateTaskCollection->setConnectionService('mongodbCacheDev');
      $task = $updateTaskCollection::findById($_id);
      $status = $this->getParam('status', '');
      if ($task) {
        /**
         * @var $db Phalcon\Db\Adapter\MongoDB\Database
         */
        $db = $this->getDI()->get('mongodbCacheDev');
        $collection = $db->selectCollection('update_task_shops');
        $total_count = $collection->count(['task_id' => $_id]);
        $left_count = $collection->count(['task_id' => $_id, 'status' => 'progress']);
        $pending_count = $collection->count(['task_id' => $_id, 'status' => 'pending']);
        $left_count += $pending_count;
        $failed_count = $collection->count(['task_id' => $_id, 'status' => 'failed']);
        $completed_count = $collection->count(['task_id' => $_id, 'status' => 'completed']);

        if ($status) {
          $cursor = $collection->find(['task_id' => $_id, 'status' => $status], ['limit' => 50, 'sort' => ['modified_at' => -1]]);
        } else {
          $cursor = $collection->find(['task_id' => $_id], ['limit' => 50, 'sort' => ['modified_at' => -1]]);
        }
        $client_ids = [];
        $task_shops = [];
        foreach ($cursor as $shop) {
          $client_ids[] = $shop->client_id;
          $item = new stdClass();
          $item->client_id = $shop->client_id;
          $item->shop = $shop->shop;
          $item->status = $shop->status;
          $item->message = $shop->message;
          $task_shops[] = $item;
        }

        // Todo do not use for dev
        /*$rows = Shops::query()->inWhere('sswclient_id', $client_ids)->execute();
        $shops = [];
        foreach ($rows as $row) {
          $shops[$row->sswclient_id] = $row;
        }*/

        $this->view->setVars([
          '_id' => $_id,
          'task' => $task,
          'total_count' => $total_count,
          'left_count' => $left_count,
          'failed_count' => $failed_count,
          'pending_count' => $pending_count,
          'completed_count' => $completed_count,
          'status' => $status,
          'task_shops' => $task_shops,
//          'shops' => $shops
        ]);
        $success = true;
      }
    }

    if (!$success) {
      $this->view->disable();
      $this->response->redirect('devupdate');
    }
  }

  public function taskInfoAction()
  {
    $response = ['success' => false];
    $_id = $this->getParam('_id', '');
    if ($_id) {
      $updateTaskCollection = new UpdateTasks();
      $updateTaskCollection->setConnectionService('mongodbCacheDev');
      $task = $updateTaskCollection::findById($_id);
      if ($task) {
        /**
         * @var $db Phalcon\Db\Adapter\MongoDB\Database
         */
        $db = $this->getDI()->get('mongodbCacheDev');
        $collection = $db->selectCollection('update_task_shops');
        $left_count = $collection->count(['task_id' => $_id, 'status' => 'progress']);
        $pending_count = $collection->count(['task_id' => $_id, 'status' => 'pending']);
        $left_count += $pending_count;
        $failed_count = $collection->count(['task_id' => $_id, 'status' => 'failed']);
        $completed_count = $collection->count(['task_id' => $_id, 'status' => 'completed']);
        $response['success'] = true;
        $response['info'] = [
          'left_count' => $left_count,
          'failed_count' => $failed_count,
          'pending_count' => $pending_count,
          'completed_count' => $completed_count
        ];
      }
    }

    exit(json_encode($response));
  }

  private function resetTaskClients($task)
  {
    /**
     * @var $db Phalcon\Db\Adapter\MongoDB\Database
     */
    $task_id = (string)$task->getId();
    $db = $this->getDI()->get('mongodbCacheDev');
    $collection = $db->selectCollection('update_task_shops');
    $collection->deleteMany(['task_id' => $task_id]);
    $query = SswClientAppDev::query()
      ->columns([
        'client_id'
      ]);
    $query->andWhere('new = 0');
    if ($task->client_app != 'all') {
      $query->andWhere('app = :app:', ['app' => $task->client_app]);
    }
    if ($task->client_status != 'all') {
      $query->andWhere('status = :status:', ['status' => $task->client_status]);
    }

    $rows = $query->execute();
    $client_ids = [];
    foreach ($rows as $row) {
      $client_ids[] = $row->client_id;
    }

    if (!empty($client_ids)) {
      $shops = SswClientsDev::query()
        ->columns([
          'client_id',
          'shop'
        ])
        ->inWhere('client_id', $client_ids)
        ->execute();
      $data = [];
      foreach ($shops as $shop) {
        $data[] = [
          'task_id' => $task_id,
          'client_id' => $shop->client_id,
          'shop' => $shop->shop,
          'status' => 'progress',
          'message' => '',
          'created_at' => time(),
          'modified_at' => time()
        ];
      }
      $shop_count = count($data);
      if ($shop_count) {
        $collection->insertMany($data);
      }
      $task->save();
    }
  }

  private function resetPendingTaskClients($task)
  {
    // Pending task shops to progress
    /**
     * @var $db Phalcon\Db\Adapter\MongoDB\Database
     */
    $task_id = (string)$task->getId();
    $db = $this->getDI()->get('mongodbCacheDev');
    $collection = $db->selectCollection('update_task_shops');
    $collection->updateMany(['task_id' => $task_id, 'status' => 'pending'], ['$set' => ['status' => 'progress', 'modified_at' => time()]]);
  }
}