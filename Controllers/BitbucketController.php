<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 19.07.14
 * Time: 16:01
 */

use Phalcon\Mvc\Controller;

class BitbucketController extends Controller
{
  public function print_slackAction()
  {
    $message = "----";
    $channel = $this->getParam('channel', '');
    $type = $this->getParam('type', '');

    if (!$channel && !$type) {
      die('no data');
    }

    if ($type == 'shorthorse') {
      $message = "Pushed! Pls update!";
    }
    print_slack($message,$channel);
    exit("");
  }

  public function indexAction()
  {
    exit("--");
    exec("/mnt/www/deploy-config/py3env/bin/python34 /mnt/www/deploy-config/boto.py --ssw", $output, $var);
    print_arr($var);
    print_die($output);
  }

  public function file_get_contents_curl($url)
  {
    //Get server path
    $ca_cert_bundle = __DIR__ . '/../../public/certs/SimpleNotificationService.pem';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_CAINFO, $ca_cert_bundle);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }

  public function getParam($key, $default = null)
  {
    $val = $this->dispatcher->getParam($key, array());
    if (!$val)
      $val = $this->request->getQuery($key);
    if (!$val)
      $val = $this->request->getPost($key, array(), $default);
    return $val;
  }

  public function aws_triggerAction()
  {
    if (!isset($HTTP_RAW_POST_DATA)) {
      $HTTP_RAW_POST_DATA = file_get_contents('php://input');
    }
    $data = isset($HTTP_RAW_POST_DATA) ? json_decode($HTTP_RAW_POST_DATA) : false;
    //print_slack(json_decode($HTTP_RAW_POST_DATA,true),'kalyskin_debug');
    //Confirm SNS subscription
    if (isset($data->Type) && $data->Type == 'SubscriptionConfirmation') {
      $this->file_get_contents_curl($data->SubscribeURL);
    } else if ($data && $data->Type == 'Notification' && isset($_GET['mykey']) && $_GET['mykey'] == 'hehehehehe88') {
      $MessageId = Settings::getSetting('aws_trigger_messageid', "");
      if($MessageId != $data->MessageId ) {
        Settings::setSetting('aws_trigger_messageid', $data->MessageId);
        exec("/mnt/www/deploy-config/py3env/bin/python34 /mnt/www/deploy-config/boto.py --ssw", $output, $var);
        exec("php /mnt/www/deploy-config/proxysql.php");
        //print_slack('`UPDATE_CONFIG code(' . $var . ')` output: ' . json_encode($output), 'kalyskin_debug');
        print_slack('`UPDATE_CONFIG code('.$var.')` output: '. json_encode($output), 'deployment');
      }else{
        print_slack('`SKIPED:` ' . $data->MessageId, 'kalyskin_debug');
      }
    }
    exit("--");
  }

  public function aws_trigger_whAction()
  {
    if (!isset($HTTP_RAW_POST_DATA)) {
      $HTTP_RAW_POST_DATA = file_get_contents('php://input');
    }
    $data = isset($HTTP_RAW_POST_DATA) ? json_decode($HTTP_RAW_POST_DATA) : false;
    //Confirm SNS subscription
    if (isset($data->Type) && $data->Type == 'SubscriptionConfirmation') {
      $this->file_get_contents_curl($data->SubscribeURL);
    } else if ($data && $data->Type == 'Notification' && isset($_GET['mykey']) && $_GET['mykey'] == 'hehehehehe88') {
      $MessageId = Settings::getSetting('aws_trigger_messageid_wh', "");
      if($MessageId != $data->MessageId ) {
        Settings::setSetting('aws_trigger_messageid_wh', $data->MessageId);
        exec("/mnt/www/deploy-config/py3env/bin/python34 /mnt/www/deploy-config/boto.py --webhook", $output, $var);
        //print_slack('`AWS_WH_TRIGER`', 'kalyskin_debug');
        print_slack('`UPDATE_CONFIG code(' . $var . ')` output: ' . json_encode($output), 'deployment');
      }else{
        print_slack('`SKIPED WH:` ' . $data->MessageId, 'kalyskin_debug');
      }
    }
    exit("--");
  }

  public function aws_trigger_assets_updaterAction()
  {
    if ($this->request->isPost()) {
      $ssw_token = $this->getParam('ssw_token', false);
      $ssw_token = check4UniqueKey($ssw_token);
      if (!$ssw_token) {
        exit(json_encode(['success' => false, 'message' => 'invalid ssw token']));
      }
      $time = time();
      $additional_data = [
          'status' => 0,
          'created_at' => $time
      ];
      $key = "task" . microtime(true);

      $db = $this->getDI()->get('mongodbCache');
      $methods = $this->getParam('methods', json_encode([]));
      $methods = json_decode($methods);
      if (!empty($methods) && in_array('updateScriptTags', $methods)) {

        $updateScriptTags = array_search('updateScriptTags',$methods);
        if ($updateScriptTags !== false) {
          unset($methods[$updateScriptTags]);
          $methods = array_values($methods);
          $methods[] = 'collect_js';
        }
      }

      if (empty($methods)) {
        exit(json_encode(['success' => true]));
      }

      $methods = json_encode($methods);

      $data = [
          'title' => $this->getParam('title', ''),
          'client_status' => $this->getParam('client_status', 'all'),
          'client_app' => $this->getParam('client_app', 'all'),
          'deployment_id' => $this->getParam('deployment_id', ''),
          'methods' => $methods,
          'changedLiquidFiles' => $this->getParam('changedLiquidFiles', json_encode([])),
          'modified_at' => $time,
          'key' => $key
      ];
      if ($additional_data) {
        $data = array_merge($data, $additional_data);
      }
      print_slack('Detected changes in css/js files, preparing to update', 'apaccik_debug');
      $collection = $db->selectCollection('bitbucket_update_tasks');
      $tasks_collection = $db->selectCollection('update_tasks');
      $tasks_collection->insertOne($data);
      $task = $tasks_collection->findOne(['key' => $key]);
      $data['status'] = 1;
      $data['task_id'] = (string) $task['_id'];
      $collection->insertOne($data);
      $response = [
          'success' => true
      ];

      exit(json_encode($response));

    }
  }

  public function smsAction()
  {
    $message = '';
    if ($this->request->isPost()) {
      if ($this->getParam('aws', 0)) {
        if (!isset($HTTP_RAW_POST_DATA)) {
          $HTTP_RAW_POST_DATA = file_get_contents('php://input');
        }

        $data = isset($HTTP_RAW_POST_DATA) ? json_decode($HTTP_RAW_POST_DATA) : false;

        //Confirm SNS subscription
        if (isset($data->Type) && $data->Type == 'SubscriptionConfirmation') {
          $this->file_get_contents_curl($data->SubscribeURL);
        } elseif ($data && $data->Type == 'Notification') {
          $subject = $data->Subject;
          if (strpos($subject, 'CHECK-ALB-5xx-') !== false) {
            preg_match("/\"CHECK-ALB-5..-(\w*)\"/", $subject, $msgParts);
            if ($msgParts) {
              $message = "Too many 5XX errors on {$msgParts[1]} LB";
            } else {
              preg_match("/\"CHECK-ALB-5..-(\w*)-(\w*)\"/", $subject, $msgParts);
              if ($msgParts) {
                $message = "Too many 5XX errors on {$msgParts[1]}-{$msgParts[2]} LB";
              }
            }
            if (!$message) {
              $message = $subject;
            } else {
              if (strpos($subject, 'ALARM:') === 0) {
                $message = '[PROBLEM] ' . $message;
              } elseif (strpos($subject, 'OK:') === 0) {
                $message = '[OK] ' . $message;
              }
            }
          } elseif (strpos($subject, 'RDS-Free Storage Space') !== false) {
            preg_match("/\"RDS-Free StorageSpace (\w*)\"/", $subject, $msgParts);
            if ($msgParts) {
              $message = "Low free disk space on {$msgParts[1]}";
            } else {
              preg_match("/\"RDS-Free StorageSpace (\w*)-(\w*)\"/", $subject, $msgParts);
              if ($msgParts) {
                $message = "Low free disk space on {$msgParts[1]}-{$msgParts[2]}";
              }
            }
            if (!$message) {
              $message = $subject;
            } else {
              if (strpos($subject, 'ALARM:') === 0) {
                $message = '[PROBLEM] ' . $message;
              } elseif (strpos($subject, 'OK:') === 0) {
                $message = '[OK] ' . $message;
              }
            }
          } elseif (strpos($subject, 'RDS-Write Latancy') !== false) {
            preg_match("/\"RDS-Write Latancy (\w*)\"/", $subject, $msgParts);
            if ($msgParts) {
              $message = "Write latency problem on {$msgParts[1]}";
            } else {
              preg_match("/\"RDS-Write Latancy (\w*)-(\w*)\"/", $subject, $msgParts);
              if ($msgParts) {
                $message = "Write latency problem on {$msgParts[1]}-{$msgParts[2]}";
              }
            }
            if (!$message) {
              $message = $subject;
            } else {
              if (strpos($subject, 'ALARM:') === 0) {
                $message = '[PROBLEM] ' . $message;
              } elseif (strpos($subject, 'OK:') === 0) {
                $message = '[OK] ' . $message;
              }
            }

          } elseif (strpos($subject, 'RDS-CPU') !== false) {
            preg_match("/\"RDS-CPU (\w*)\"/", $subject, $msgParts);
            $db_name = '';
            if ($msgParts) {
              $message = "High CPU on {$msgParts[1]}";
              $db_name = $msgParts[1];
            } else {
              preg_match("/\"RDS-CPU (\w*)-(\w*)\"/", $subject, $msgParts);
              if ($msgParts) {
                $message = "High CPU on {$msgParts[1]}-{$msgParts[2]}";
                $db_name = $msgParts[1] . '-' . $msgParts[2];
              }
            }
            if (!$message) {
              $message = $subject;
            } else {
              if (strpos($subject, 'ALARM:') === 0) {
                $message = '[PROBLEM] ' . $message;
              } elseif (strpos($subject, 'OK:') === 0) {
                $message = '[OK] ' . $message;
              }
            }
            if (strpos($message, '[PROBLEM]') !== false || strpos($message, 'ALARM:') !== false) {
              $this->trackProcessList($db_name);
            }
          }
        }
      } else {
        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == '54.156.196.130') {
          $data = file_get_contents('php://input');
          if (is_string($data)) {
            $data = json_decode($data, true);
          }
          if (isset($data['attachments']) && isset($data['attachments'][0]) && $data['attachments'][0]['title'] && $data['attachments'][0]['title'] != 'Zabbix server') {
            $message = $data['attachments'][0]['title'];
            if (isset($data['attachments'][0]['author_name'])) {
              if (strpos($message, 'Load Average too high') !== false) {
                $message .= ' on ' . $data['attachments'][0]['author_name'];
              } else {
                $message = $data['attachments'][0]['author_name'] . ': ' . $message;
              }
            }
            $message = str_replace("socialshopwave.com", "ssw", $message);
            $message = str_replace("growave.io", "ssw", $message);
            $message = str_replace("db.ssw", "db-ssw", $message);
            $message = str_replace("beta.ssw", "beta-ssw", $message);
            $message = str_replace("dev.db-ssw", "dev-db-ssw", $message);
            $message = str_replace("dev.ssw", "dev-ssw", $message);
          }
        }
      }
    }

    if ($message) {
      // Sent SMS with twilio
      // Twilio API Credentials
      $sid = 'AC6f3df53080e80b834308ad66b7208f9b';
      $token = 'd78b0ef19c90c3c46edabc350e454da2';
      $twilioClient = new Twilio\Rest\Client($sid, $token);
      $numbers = [
        'Ermek' => '+996706868640',
        'Ravshan' => '+996703314649',
        'Ulan' => '+996559092995',
        'Kalyskin' => '+996709770095',
        'Stas' => '+996555144203',
        'Anton' => '+996703078690'
      ];
      $message_sum = md5(trim($message));
      print_slack($message, 'server');
      $lastMessage = Settings::getSetting('last_message', '');
      if ($lastMessage != $message_sum) {
        foreach ($numbers as $number) {
          $result = $twilioClient->messages->create(
            $number,
            array(
              'from' => '+18305051824',
              'body' => $message
            )
          );
        }
      }
      Settings::setSetting('last_message', $message_sum);
    }
    exit("--");
  }

  public function rdsCPUAction()
  {
    if ($this->request->isPost() && $this->getParam('aws', 0)) {
      if (!isset($HTTP_RAW_POST_DATA)) {
        $HTTP_RAW_POST_DATA = file_get_contents('php://input');
      }

      $data = isset($HTTP_RAW_POST_DATA) ? json_decode($HTTP_RAW_POST_DATA) : false;

      //Confirm SNS subscription
      if (isset($data->Type) && $data->Type == 'SubscriptionConfirmation') {
        $this->file_get_contents_curl($data->SubscribeURL);
      } elseif ($data && $data->Type == 'Notification') {
        $message = '';
        $subject = $data->Subject;
        preg_match("/\"HIGH-CPU (\w*)\"/", $subject, $msgParts);
        $db_name = '';
        if ($msgParts) {
          $message = "High CPU on {$msgParts[1]}";
          $db_name = $msgParts[1];
        } else {
          preg_match("/\"HIGH-CPU (\w*)-(\w*)\"/", $subject, $msgParts);
          if ($msgParts) {
            $message = "High CPU on {$msgParts[1]}-{$msgParts[2]}";
            $db_name = $msgParts[1] . '-' . $msgParts[2];
          }
        }
        if (!$message) {
          $message = $subject;
        } else {
          if (strpos($subject, 'ALARM:') === 0) {
            $message = '[PROBLEM] ' . $message;
          } elseif (strpos($subject, 'OK:') === 0) {
            $message = '[OK] ' . $message;
          }
        }
        print_slack($message, 'server');
        if (strpos($message, '[PROBLEM]') !== false || strpos($message, 'ALARM:') !== false) {
          $this->trackProcessList($db_name);
        }
      }
    }
    exit("--");
  }

  private function trackProcessList($db_name)
  {
    $databases = [
      'socialshopwave-rds0' => 1,
      'socialshopwave-rds1' => 101,
      'socialshopwave-rds2' => 104,
      'socialshopwave-devrds' => 'devssw_database'
    ];

    if (array_key_exists($db_name, $databases)) {
      $default_timezone = date_default_timezone_get();
      date_default_timezone_set('Asia/Bishkek');
      $query = "SHOW FULL PROCESSLIST";
      $dbNumber = $databases[$db_name];
      if (is_numeric($dbNumber)) {
        $dbAdapter = ($dbNumber == 1) ? 'ssw_database' : 'ssw_database' . $dbNumber;
      } else {
        $dbAdapter = $dbNumber;
      }

      /**
       * @var Phalcon\Db\Adapter\Pdo\Mysql $db
       */
      $db = Phalcon\DI::getDefault()->get($dbAdapter);
      $result = $db->fetchAll($query, Phalcon\Db::FETCH_ASSOC);
      $data = [];
      $date = date('Y-m-d H:i:s');
      foreach ($result as $item) {
        if ($item['Command'] == 'Query' && $item['Info'] && $item['Info'] != $query) {
          $item['Tracked Time'] = $date;
          $data[] = $item;
        }
      }

      if ($data) {
        /** @var \Phalcon\Db\Adapter\MongoDB\Database $mongodb */
        $mongodb = $this->getDI()->get('mongodbTracker');
        // Select users collection
        $collection = $mongodb->selectCollection('sql_processlist');
        $collection->insertMany($data);
      }

      date_default_timezone_set($default_timezone);
    }
  }
}