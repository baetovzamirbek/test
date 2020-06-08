<?php
use GuzzleHttp\Exception\GuzzleException;
use Intercom\IntercomClient;
use Phalcon\Paginator\Adapter\QueryBuilder;
use Phalcon\Http\Request;

class DevelopersController extends AbstractController
{
  public function indexAction()
  {

    $request = new Request();

    if ($this->request->isPost() && $this->request->isAjax()) {

      if ($request->getPost('request') == 'get_statistics') {

        $date = new DateTime( $request->getPost('picked_date') );
        $isChecked = $request->getPost('isChecked');
        $isCheckedCritical = $request->getPost('isCheckedCritical');
        $date->setTime(0, 0, 0);
        $picked_date = $date->format('Y-m-d H:s:s');
        $date = $date->format('Y-m-d');

        switch ([$isChecked, $isCheckedCritical]):
          case [true, false]:
            $statistics = $this::getStatistics(true, $picked_date);
            break;
          case [true, true]:
            $statistics = $this::getStatistics(true, $picked_date, ' ');
            break;
          case [false, true]:
            $statistics = $this::getStatistics(false, $picked_date, ' ');
            break;
          default:
            $statistics = $this::getStatistics(false, $picked_date);
            break;
        endswitch;

        $devsIDs = $this::getDevsIDsOnDuty($statistics);
        $reasons = $this::getReasonsCountForFewTickets($devsIDs, $date);

        for($i = 0; $i < count($statistics); $i++) {
            $j = 0;
          foreach ($reasons as $reason){
            if($statistics[$i]['user_id'] === $reason['user_id']){
              $j++;
              $statistics[$i]['few_tickets_reason_count'] = $j;
              $statistics[$i]['few_tickets_reason_ids'][] = $reason['reason_id'];
            }
          }
        }
        return json_encode($statistics);
      }

      else if  ($request->getPost('request') == 'ticketsDetails') {
        $clients = [];
        $infoFromTicket = [];
        $ticketIds = [];
        $isChecked = json_decode( $request->getPost('isChecked') );
        $userId = $request->getPost('user_id');
        $date = new DateTime( $request->getPost('picked_date') );
        $date->setTime(0, 0, 0);
        $pickedDate = $date->format('Y-m-d H:i:s');

        $ticketsDetails = $this::getTicketsDetails($userId, $isChecked, $pickedDate);

        foreach ($ticketsDetails as $ticket) {
          $ticketIds[] = $ticket['ticket_id'];
        }
        $ticketIds = $this::implodeArray($ticketIds);
        $query = "SELECT c.client_id, c.ticket_id, c.priority, c.subject FROM crm_ticket as c WHERE  c.ticket_id in ({$ticketIds})";
        $infoFromTicket[] = $this->db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);

        foreach($infoFromTicket as $info) {
          foreach ($info as $client) {
            $clientIds[] = $client['client_id'];
          }
        }
        $clientIds = $this::implodeArray($clientIds);
        $query2 = "SELECT c.client_id, c.name FROM crm_client as c WHERE  c.client_id in ({$clientIds})";
        $clients[] = $this->db->fetchAll($query2, \Phalcon\Db::FETCH_ASSOC);

        $result = $this::collectClientInfo($infoFromTicket,$clients);
        return json_encode($result);
      }

      else if  ($request->getPost('request') == 'fewTicketReason') {
        $data = json_decode($this->getParam('data'));
        $isTicketFound = Ticket::findFirst([
          'columns' => 'ticket_id',
          'conditions'=> 'ticket_id = :ticket_id:',
          'bind' =>['ticket_id' => $data->ticket_id]
        ]);
        date_default_timezone_set('Asia/Bishkek');

        if($isTicketFound) {
          $fewTicketsReason = new FewTicketsReason();
          $fewTicketsReason->ticket_id     = $data->ticket_id;
          $fewTicketsReason->user_id       = Core::getViewerId();
          $fewTicketsReason->reason_text   = $data->reason_text;
          $fewTicketsReason->creation_date = date('Y-m-d H:i:s');
          $response = ($fewTicketsReason->save()) ? 'success' : $data->ticket_id;
        }else{
          $response = $data->ticket_id;
        }
        return json_encode($response);
      }

      else if  ($request->getPost('request') == 'developersOnDuty') {
        $developersOnDutyIds = [];
        $developersOnDuty = DutyHistoryNew::getDutyDevelopers();
        foreach ($developersOnDuty as $developer) {
          $developersOnDutyIds[] = $developer['user_id'];
        }
        return (count($developersOnDutyIds)) ?  json_encode($developersOnDutyIds) : null;
      }

      else if  ($request->getPost('request') == 'getTicketsCount') {
        $ticketsCount = $this::getTicketCount(Core::getViewerId(), date('Y-m-d'));
        return (count($ticketsCount)) ? json_encode($ticketsCount) : null;
      }

      else if  ($request->getPost('request') == 'getReasons') {
        $IDs = json_decode($this::getParam('IDs'));
        $reasonIDs[] = (is_int($IDs)) ? $IDs : '';
        $responses = (is_array($IDs)) ? FewTicketsReason::getReasons($IDs) : FewTicketsReason::getReasons($reasonIDs);
        return (count($responses)) ? json_encode($responses) : null;
      }

    }
  }

  public function settingAction()
  {
      $this->view->settings = DevelopersSetting::getAllSettings(10);
      $request = new Request();

    if ($this->request->isPost() && $this->request->isAjax()) {

      if ($request->getPost('requestFor') === 'add') {
        $params = json_decode($this->getParam('params'));
        $setting = new DevelopersSetting();
        $setting->ticket_quantity = $params->ticket_quantity;
        $setting->execution_time  = $params->execution_time;
        $setting->creation_date   = date('Y-m-d');
        return ($setting->save()) ? true : null;
      }

      else if ($request->getPost('requestFor') == 'updateSetting') {
        $settingID = $this->getParam('settingID');
        $newParams = json_decode($this->getParam('newParams'));
        $developersSetting = DevelopersSetting::findFirst([
          'conditions'=> 'setting_id = :setting_id:',
          'bind' =>['setting_id' => $settingID ]
        ]);
        $developersSetting->ticket_quantity = $newParams->newTicketQuantity;
        $developersSetting->execution_time = $newParams->newExecutionTime;
        $developersSetting->creation_date = date('Y-m-d H:i:s');
        $reponse = ($developersSetting->update()) ?  'Updated successfully!' : 'Failed! Something went wrong!';
        return json_encode($reponse);
      }

      else if ($request->getPost('requestFor') == 'delete') {
        $settingID = $this->getParam('settingID');
        $response = DevelopersSetting::deleteSetting($settingID);
        return ($response) ? $settingID : null;
      }

      else if ($request->getPost('request') == 'checkSettings') {
        $onlyIDs = $this::getParam('id');
        $settings = ($onlyIDs) ?
        DevelopersSetting::getSettingsIDs(10) :
        DevelopersSetting::getAllSettings(10);
        return (count($settings)) ? json_encode($settings) : null;
      }

    }
  }

  protected function getStatistics($forCurrentDayOnly, $date , $dutyLevelCondition='AND u.duty_level!="critical"')
  {
    $query = ($forCurrentDayOnly) ?
      "SELECT u.user_id, u.full_name, COUNT(DISTINCT p.post_id) AS posts_count, COUNT(DISTINCT a.ticket_id) AS tickets_count, ROUND(COUNT(DISTINCT a.ticket_id)/COUNT(DISTINCT p.post_id)*100, 0) AS quality
      FROM crm_assigns_logs AS a
      INNER JOIN crm_user AS u ON (a.user_id = u.user_id AND u.department = 'development' {$dutyLevelCondition})
      INNER JOIN crm_post AS p ON (a.user_id = p.staff_id AND a.ticket_id = p.ticket_id AND p.type = 'private' AND DATE(a.date) = DATE(p.creation_date))
      WHERE a.user_status = 'unassigned' AND DATE(a.date) = DATE('{$date}')
      GROUP BY a.user_id":

      "SELECT u.user_id, u.full_name, COUNT(DISTINCT p.post_id) AS posts_count, COUNT(DISTINCT a.ticket_id) AS tickets_count, ROUND(COUNT(DISTINCT a.ticket_id)/COUNT(DISTINCT p.post_id)*100, 0) AS quality
      FROM crm_assigns_logs AS a
      INNER JOIN crm_user AS u ON (a.user_id = u.user_id AND u.department = 'development' {$dutyLevelCondition})
      INNER JOIN crm_post AS p ON (a.user_id = p.staff_id AND a.ticket_id = p.ticket_id AND p.type = 'private' AND DATE(a.date) = DATE(p.creation_date))
      WHERE a.user_status = 'unassigned' AND a.date > '{$date}'
      GROUP BY a.user_id";

    $statistics = $this->db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);
    return $statistics;
  }

  protected function getReasonsCountForFewTickets($devsIDs, $date) {
    $query = "SELECT f.reason_id, f.user_id, f.creation_date FROM crm_few_tickets_reason AS f WHERE  f.user_id IN ({$devsIDs}) AND DATE(f.creation_date) = DATE('{$date}')";
    $results = $this->db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);
    return $results;
  }

 protected function getTicketsDetails ($user_id, $isChecked, $picked_date)
 {
    $query = ($isChecked) ? "SELECT a.ticket_id
    FROM crm_assigns_logs AS a
    INNER JOIN crm_user AS u ON (a.user_id = u.user_id AND u.department = 'development')
    INNER JOIN crm_post AS p ON (a.user_id = p.staff_id AND a.ticket_id = p.ticket_id AND p.type = 'private'  AND DATE(a.date) = DATE(p.creation_date))
    WHERE u.user_id = '{$user_id}' AND a.user_status = 'unassigned' AND DATE(a.date) = DATE('{$picked_date}')
    GROUP BY a.ticket_id":

    "SELECT a.ticket_id
    FROM crm_assigns_logs AS a
    INNER JOIN crm_user AS u ON (a.user_id = u.user_id AND u.department = 'development')
    INNER JOIN crm_post AS p ON (a.user_id = p.staff_id AND a.ticket_id = p.ticket_id AND p.type = 'private'  AND DATE(a.date) = DATE(p.creation_date))
    WHERE u.user_id = '{$user_id}'  AND a.user_status = 'unassigned' AND a.date > '{$picked_date}'
    GROUP BY a.ticket_id";

   $ticketsDetails = $this->db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);
   return $ticketsDetails;
  }

  protected function getTicketsCount($viewer_id, $date)
  {
    $query = "SELECT u.user_id, u.full_name, COUNT(DISTINCT p.post_id) AS posts_count, COUNT(DISTINCT a.ticket_id) AS tickets_count, ROUND(COUNT(DISTINCT a.ticket_id)/COUNT(DISTINCT p.post_id)*100, 0) AS quality
      FROM crm_assigns_logs AS a
      INNER JOIN crm_user AS u ON (a.user_id = u.user_id AND u.department = 'development')
      INNER JOIN crm_post AS p ON (a.user_id = p.staff_id AND a.ticket_id = p.ticket_id AND p.type = 'private' AND DATE(a.date) = DATE(p.creation_date))
      WHERE u.user_id = '{$viewer_id}' AND a.user_status = 'unassigned' AND DATE(a.date) = DATE('{$date}')
      GROUP BY a.user_id";
    $result = $this->db->fetchAll($query, \Phalcon\Db::FETCH_ASSOC);
    return $result;
  }

  private function collectClientInfo(array $arr,array $arr2)
  {
    $result = [];
    for ($j = 0; $j < count($arr[0]); $j++) {
      $clientInfo = [];
      for ($k = 0; $k < count($arr2[0]); $k++) {
        if ($arr[0][$j]['client_id'] === $arr2[0][$k]['client_id']) {
          $clientInfo = [
            'client_id' => $arr2[0][$k]['client_id'],
            'client_name' => $arr2[0][$k]['name'],
            'ticket_id' => $arr[0][$j]['ticket_id'],
            'subject' => $arr[0][$j]['subject'],
            'priority' => $arr[0][$j]['priority'],
          ];
        }
      }
      $result [] = $clientInfo;
    }
    return $result;
  }

  protected function implodeArray(array $arr)
  {
    $implodedArr = "'".implode("', '", $arr)."'";
    return $implodedArr;
  }

  protected function getDevsIDsOnDuty(array $statistics)
  {
    foreach ($statistics as $statistic) {
      $devsIDs[] = $statistic['user_id'];
    }
    return $this::implodeArray($devsIDs);
  }

}