<?php

/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 15.08.14
 * Time: 16:19
 */
class UpdateController extends AbstractController
{

  protected $heDomain = 'https://growave.io/public/';

  protected $themes = array();

  public function versionsAction()
  {
    $basePath = '/mnt/www/off.growave.io';
    $snippetsPath = $basePath . '/newpublic/public/snippets/';
    $templatesPath = $basePath . '/newpublic/public/templates/';
    $assetsPath = $basePath . '/newpublic/public/assets/';
    $snippets = array_values(array_diff(scandir($snippetsPath), array('.', '..')));
    $templates = array_values(array_diff(scandir($templatesPath), array('.', '..')));
    $assets = array_values(array_diff(scandir($assetsPath), array('.', '..')));

    $length = (count($snippets) > count($templates)) ? count($snippets) : count($templates);
    $length = ($length > count($assets)) ? $length : count($assets);

    $success = 0;
    if ($this->request->isPost()) {
      $files = [];
      $postSnippets = $this->getParam('snippets', []);
      $postTemplates = $this->getParam('templates', []);
      $postAssets = $this->getParam('assets', []);

      foreach ($postSnippets as $snippet) {
        $files[] = 'newpublic/public/snippets/' . $snippet;
      }
      foreach ($postTemplates as $template) {
        $files[] = 'newpublic/public/templates/' . $template;
      }
      foreach ($postAssets as $asset) {
        $files[] = 'newpublic/public/assets/' . $asset;
      }

      $file_list = implode(' ', $files);
      $export_dir = 'temporary/versions';

      $shell_script = "cd $basePath; git reset --hard; git clean -df; git pull;";
      $shell_script .= "rm -fr temporary/smart-mods; rm -fr $export_dir; ";
      $shell_script .= <<<STR
      
#!/bin/bash
NEWPUBLIC_DIR="newpublic/"
OLD_PUBLIC_HEAD="aa30111d2264bcaeae72431d9f2a7d47a2694d05"
liquid_files=`ls $file_list`
for eachfile in \$liquid_files
do
echo \$eachfile

#===============================================================

# we'll write all git versions of the file to this folder:
EXPORT_DIR=$export_dir
eachfile_name=$(basename \$eachfile)
EXPORT_TO="\$EXPORT_DIR/\$eachfile_name"
# take relative path to the file to inspect
GIT_PATH_TO_FILE=\$eachfile

# ---------------- don't edit below this line --------------

USAGE="Please cd to the root of your git proj and specify path to file you with to inspect (example: $0 some/path/to/file)"

# check if got argument
if [ "\${GIT_PATH_TO_FILE}" == "" ]; then
    echo "error: no arguments given. \${USAGE}" >&2
    exit 1
fi

# check if file exist
if [ ! -f \${GIT_PATH_TO_FILE} ]; then
    echo "error: File '\${GIT_PATH_TO_FILE}' does not exist. \${USAGE}" >&2
    exit 1
fi

# extract just a filename from given relative path (will be used in result file names)
GIT_SHORT_FILENAME=$(basename \$GIT_PATH_TO_FILE)

# create folder to store all revisions of the file
if [ ! -d \${EXPORT_TO} ]; then
    echo "creating folder: \${EXPORT_TO}"
    mkdir -p \${EXPORT_TO}
else 
    echo "creating folder failed: \${EXPORT_TO}"
fi

## uncomment next line to clear export folder each time you run script
#rm \${EXPORT_TO}/*

# reset coutner
COUNT=0

# iterate all revisions
git --no-pager log --all --follow --pretty=oneline -- \${GIT_PATH_TO_FILE} | \
    cut -d ' ' -f1 | \
while read h; do \
     COUNT=$((COUNT + 1)); \
     COUNT_PRETTY=$(printf "%04d" \$COUNT); \
     COMMIT_DATE=`git show \$h | head -3 | grep 'Date:' | awk '{print $4"-"$3"-"$6}'`; \
     if [ "\${COMMIT_DATE}" != "" ]; then \
     if [ "\$h" == "\$OLD_PUBLIC_HEAD" ]; then \
          GIT_PATH_TO_FILE="\${GIT_PATH_TO_FILE//\$NEWPUBLIC_DIR/}" 
     fi;\
         git cat-file -p \${h}:\${GIT_PATH_TO_FILE} > \${EXPORT_TO}/\${COUNT_PRETTY}.\${COMMIT_DATE}.\${h}.\${GIT_SHORT_FILENAME};\
     fi;\
done;

# return success code
echo "result stored to \${EXPORT_TO}"

#=============================================================

done;
exit 0
STR;

      $res = shell_exec($shell_script);

      if ($res) {
        $success = $res;
      } else {
        $success = 1;
      }

    }

    $this->view->setVars([
      'success' => $success,
      'length' => $length,
      'snippets' => $snippets,
      'templates' => $templates,
      'assets' => $assets
    ]);
  }

  public function sharingstatsAction()
  {
    $map = [
      'collect_email',
      'share_purchase',
      'share_product_icon',
      'share_review',
      'share_review',
      'share_popup',
    ];
    /** @var \Phalcon\Db\Adapter\MongoDB\Database $mongodb */
    $mongodb = $this->getDI()->get('mongodbTracker');
    // Select users collection
    $collection = $mongodb->selectCollection('sharing_stats');
    /** @var \MongoDB\Driver\Cursor $stats */
    $stats = $collection->aggregate([
      [
        '$group' => [
          '_id' => '$type',
          'title' => [
            '$last' => '$title'
          ],
          'type' => [
            '$last' => '$type'
          ],
          'active' => [
            '$sum' => '$active'
          ],
          'impression' => [
            '$sum' => '$impression'
          ],
          'clicks' => [
            '$sum' => '$clicks'
          ],
          'shared' => [
            '$sum' => '$shared'
          ],
          'orders' => [
            '$sum' => '$orders'
          ],
          'revenue' => [
            '$sum' => '$revenue'
          ]
        ]
      ],
      [
        '$sort' => [
          'shared' => -1
        ]
      ]
    ]);
//    print_die($stats->toArray());
    $this->view->setVar('stats', $stats->toArray());
  }

  public function listAction()
  {
//    $rows = SswClientApp::find([
//      "conditions" => "app IN ('default') AND new = 0 AND package_id IN (2,3,4)",
//    ]);
    $rows = SswClients::find("ssw = 1 AND status = 1 and new = 0");

    $client_ids = [];
    foreach ($rows as $row) {
      if (!in_array($row->client_id, $client_ids)) {
        $client_ids[] = $row->client_id;
      }
    }

    $shops = Shops::query()->inWhere('sswclient_id', $client_ids)->orderBy('status ASC')->execute();
    /*$shops = Shops::find(array(
//      "status IN('active','installed') AND shop_id NOT IN(1859,1535, 2062)",
//    "sswclient_id NOT IN(1527,1541,1543,1544,1546,1549,1565,1566,1572,1577,1582,1584,1588,1592,1600,1602,1607,1619,1630,1636,1638,1639,1657,1658,1671,1673,1675,1684,1685,1687,1690,1693,1695,1696,1707,1714,1755,1765,1866,1887,1929,1966,1969,1991,2020,2034,2208,2219,2245,2288,2302,2337,2347,2355,2360,2394,2396,2406,2409,2423,2450,2470,2483,2485,2527,2542,2562,2578,2582,2630,2661,2664,2674,2714,2715,2728,2729,2730,2731,2739,2742,2756,2763,2769,2770,2772,2775,2776,2780,2782,2789,2792,2793,2794,2798,2799,2800,2801,2808,2810,2811,2813,2818,2819,2823,2827,2831,2832,2833,2837,2838,2839,2840,2841,2843,2848,2849,2850,2851,2852,2855,2856,2857,2860,2861,2862,2864,2866,2867,2868,2869,2870,2871,2872,2873,2874,2875,2876,2877,2879,2880,2881,2882,2883,2884,2885,2891,2892,2893,2895,2896,2902,2904,2905,2906,2911,2913,2915,2917,2919,2922,2923,2924,2927,2932,2933,2934,2935,2937,2940,2941,2942,2944,2945,2948,2949,2953,2954,2956,2957,2958,2960,2963,2966,2967,2968,2969,2970,2972,2973,2974,2975,2976,2977,2978,2979,2980,2981,2982,2983,2985,2986,2987,2988,2990,2991,2992,2993,2994,2996,2997,2998,2999,3000,3001,3002,3003,3004,3005,3008,3011) AND removed = 0",
//    "shop_id NOT IN(2561, 2307, 6, 1286, 1290, 773, 273, 1813, 1041, 2068, 2583, 16, 795, 2596, 37, 808, 2344, 297, 2587, 2085, 2348, 2092, 1812, 2352, 1062, 1329, 2098, 1061, 2100, 305, 2616, 1589, 1590, 58, 828, 575, 1339, 2353, 1330, 2106, 1851, 2062, 573, 831, 2115, 322, 2632, 2104, 588, 2381, 2385, 2375, 2387, 1107, 355, 2390, 1371, 614, 2149, 1632, 1358, 1104, 871, 2663, 107, 1895, 1644, 2414, 2411, 1645, 2388, 2684, 1144, 2433, 1663, 644, 1677, 2695, 1415, 2453, 2203, 1181, 2462, 158, 2189, 405, 927, 417, 2209, 678, 677, 2472, 1412, 1705, 1708, 1683, 1196, 2482, 180, 2219, 1205, 432, 2750, 2231, 1215, 2232, 1219, 2489, 936, 1733, 1216, 2501, 2244, 2245, 2356, 2761, 2766, 2510, 2509, 2256, 1484, 712, 2507, 2773, 1489, 733, 2269, 2529, 1500, 2278, 220, 237, 1254, 2542, 1009, 2801, 2033, 753, 2546, 2803, 751, 1790, 2814, 2303, 258, 239, 159, 786, 505, 792, 5, 552, 1063, 557, 45, 1071, 48, 822, 567, 1332, 1326, 1090, 1344, 1092, 1049, 581, 585, 1859, 847, 330, 1364, 310, 1620, 346, 2141, 607, 864, 1381, 363, 632, 1656, 1149, 646, 1413, 1414, 399, 396, 1171, 668, 421, 1187, 420, 1190, 2461, 190, 945, 965, 204, 690, 209, 962, 970, 483, 1001, 1004, 1013, 1020, 1023, 1535, 233, 2302, 2450) AND removed = 0",
//    "removed = 0 AND shop_id IN (12848,8080,12508,4414,256,271,272,5656,5920,3356,84,9259,113,3944,1683,187,184,192,193,194,207,195,208,211,212,215,216,222,217,225,226,2787,250,246,238,252,3399,3732,196,214,202,219,223) AND owner NOT IN ('farside312@gmail.com', 'asmproger@gmail.com', 'ulanproger@gmail.com', 'burya1988@gmail.com')",
//    "shop_id IN (2816,10497,6661,7179,9222,4876,4658,6703,9262,5390,9234,6707,6476,3905,10827,7249,4694,5716,5470,1372,10080,6501,9570,8805,9065,11109,7784,12183,6807,7023,6835,9381,4510,9393,4279,4791,8887,9399,6327,6074,9919,8122,7872,8643,2759,10950,11207,8904,12490,6604,8914,12500,5593,11752,6892,7408,6385,9461,12273,11513,8954,3351,7697,12848,9246,2302,6957,8000,4661,4697,5715,5211,2415,8080,7802,10126,4751,12508,9353,6548,7059,4257,3265,5925,5929,9445,5940,5953,5955,9206,5972,5974,5789,5792,5101,5803,5810,5817,5829,5832,10660,5874,5885,4629,8254,5446)",
//    "shop_id IN (1704,2684,3030,3529,4604,4629,4870,5484,5789,5792,5803,5817,5810,5829,5832,5874,5885,5925,5929,5940,5972,5953,5974,5955,5982,6620,7255,7767,7858,8264,8254,8433,8508,8785,8788,8792,8794,8805,8812,9237,9715,9790,9858,10737,11024,11083,11237,11434,11873,11992,12304,12378,12413,12428,12503,12502,12508,12532,12543,12576,12560,12612,12644,12774,12799,12784,12807,12848,12885)",
//    "shop_id IN (12848,8080,5925,5940,5929,5953,5955,5972,5974,5789,5792,5803,5810,5817,5829,5832,5874,5885,4629,8254)",
//      "status IN('active','installed') AND sswclient_id IN(494,1465,1506,1666,1707,1719,1765,1816,1951,1958,1966,1969,1991,2020,2034,2055,2081,2082,2084,2085,2089,2090,2094,2098,2100,2110,2111,2112,2113,2114,2116,2119,2121)",
      'order' => 'status ASC',
//      'limit' => 20
    ));*/
    $this->view->setVar('shops', $shops);
  }

  public function stagingAction()
  {
    $shops = StagingSswClients::find();
    $this->view->setVar('shops', $shops);
  }

  public function statusesAction()
  {
    $shops = Shops::find(array('order' => 'status ASC'));
    $this->view->setVar('shops', $shops);
  }

  public function indexAction()
  {
    $task_methods = [
      'updateSql',
      'updateThemesLiquid',
      'updateStyle',
      'updateScriptTags',
      'insertLocaleAndVars',
      'addSocialButtons',
      'addFbSettings',
      'addGPlusSettings',
      'clearReviewsCache',
      'updateWebhooks',
      'updatePages',
      'updateTasks',
      'resetModelsMetaData',
      'updateUniteAvgRates',
      'autoIntegrationInstagramm',
      'migrateTasks',
      'updateSpendingRules'
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

    $tasks = UpdateTasks::find(
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
      $_id = $this->getParam('_id', '');
      $additional_data = [];
      $time = time();
      if ($_id) {
        $task = UpdateTasks::findById($_id);
      } else {
        $task = new UpdateTasks();
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
        $task = UpdateTasks::findById($_id);
        if ($task) {
          $do = $this->getParam('do', '');
          if ($do == 'start') {
            $task->status = 1;
            /**
             * @var $db Phalcon\Db\Adapter\MongoDB\Database
             */
            $db = $this->getDI()->get('mongodbCache');
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
        $task = UpdateTasks::findById($_id);
        if ($task) {
          /**
           * @var $db Phalcon\Db\Adapter\MongoDB\Database
           */
          $task_id = (string)$task->getId();
          $db = $this->getDI()->get('mongodbCache');
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
      $task = UpdateTasks::findById($_id);
      $status = $this->getParam('status', '');
      if ($task) {
        /**
         * @var $db Phalcon\Db\Adapter\MongoDB\Database
         */
        $db = $this->getDI()->get('mongodbCache');
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
        $rows = Shops::query()->inWhere('sswclient_id', $client_ids)->execute();
        $shops = [];
        foreach ($rows as $row) {
          $shops[$row->sswclient_id] = $row;
        }

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
          'shops' => $shops
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
      $task = UpdateTasks::findById($_id);
      if ($task) {
        /**
         * @var $db Phalcon\Db\Adapter\MongoDB\Database
         */
        $db = $this->getDI()->get('mongodbCache');
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
    $db = $this->getDI()->get('mongodbCache');
    $collection = $db->selectCollection('update_task_shops');
    $collection->deleteMany(['task_id' => $task_id]);
    $query = SswClientApp::query()
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

    $rows = $query->execute()->toArray();
    $client_ids = [];
    foreach ($rows as $row) {
      $client_ids[] = $row['client_id'];
    }

    if (!empty($client_ids)) {
      $query = SswClients::query()
        ->columns([
          'client_id',
          'shop'
        ])
        ->inWhere('client_id', $client_ids)
        ->andWhere('new = 0');

      if ($task->client_status != 'all') {
        $query->andWhere('status = :status:', ['status' => $task->client_status]);
      }

      $shops = $query->execute()->toArray();
      $data = [];
      foreach ($shops as $shop) {
        $data[] = [
          'task_id' => $task_id,
          'client_id' => $shop['client_id'],
          'shop' => $shop['shop'],
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
    $db = $this->getDI()->get('mongodbCache');
    $collection = $db->selectCollection('update_task_shops');
    $collection->updateMany(['task_id' => $task_id, 'status' => 'pending'], ['$set' => ['status' => 'progress', 'modified_at' => time()]]);
  }
}