<?php

use Phalcon\Http\Response;

class StaffDevelopersController extends AbstractController
{
  public function developersAction()
  {
    date_default_timezone_set("Asia/Bishkek");

    $session_user = $this->getSessionUser();
    if ($session_user['staff_role'] != 'admin' && $session_user['user_id'] != 59){
      return $this->response->redirect('staff-developers/dutyList');
    }
    $users_data = User::getDevelopers();
    $this->view->setVars([
        'developers' => $users_data['developers'],
        'avatars' => $users_data['avatars'],
        'session_user' => $session_user,
      ]
    );
  }

  public function changeStatusAction()
  {
    date_default_timezone_set("Asia/Bishkek");

    $status_code = 404;
    if ($this->request->isPost()) {
      $data = $this->_getAllParams();
      if (isset($data['status'])) {
        $data = $data['status'];
        if (count($data)) {
          $status_code = User::updateDutyLevel($data);
          DutyHistoryNew::updateDutyList();
        }
      }
    }
    return json_encode(['status' => $status_code]);
  }

  public function busyDayAction()
  {
    date_default_timezone_set("Asia/Bishkek");

    if ($this->request->isAjax() && $this->request->isPost()) {
      $data = $this->request->getPost();
      $status = BusyDay::saveBusyDate($data);
      DutyHistoryNew::updateDutyList();

      ($status) ? $status = 202 : $status = 404;
    }
    return json_encode(['status' => $status]);
  }

  public function dutyListAction()
  {
    $dutyRun = $this->getParam('duty');
    if ($dutyRun == 'tomorrow') {
      FirebaseApi::pushNotificationForDutiesTomorrow();
    } elseif ($dutyRun == 'today') {
      FirebaseApi::pushNotificationForDuties();
    }

    date_default_timezone_set("Asia/Bishkek");

    $month = date('n');
    $year = date('Y');

    $workDays = DutyHistoryNew::getWorkDays();
    $dutyLogs = DutyHistoryNew::getDutyLogs();
    $busyDays = DutyHistoryNew::getBusyDays();

    // get member priority and primary points
    $allData = DutyHistoryNew::getMemberPriorityAndPoints();
    $memberNames = $allData['memberNames'];
    $primaryPoints = $allData['primaryPoints'];
    $memberDelimiters = $allData['memberDelimiters'];

    $finalList = [];
    $currentPoints = [];
    foreach ($workDays as $day => $developers) {
      $dayTime = mktime(0,0,0, $month, $day, $year);
      $finalList[$day] = ['weekday' => date('l', $dayTime)];
      if (isset($dutyLogs[$day])) {
        $finalList[$day]['developers'] = $dutyLogs[$day];
      } else {
        $currentPoints = ($currentPoints) ? $currentPoints : $primaryPoints;
        $convertedPoints = [];
        foreach ($currentPoints as $memberId => $currentPoint) {
          $convertedPoints[$memberId] = $currentPoint/$memberDelimiters[$memberId];
        }
        asort($convertedPoints);
        $dutyMembers = [];
        foreach ($convertedPoints as $memberId => $point) {
          if (isset($busyDays[$memberId]) && $dayTime >= $busyDays[$memberId]['startDate']
            && $dayTime <= $busyDays[$memberId]['endDate']) {
            continue;
          }
          $dutyMembers[] = $memberId;
          if (count($dutyMembers) >= 5) {
            break;
          }
        }
        foreach ($currentPoints as $memberId => $currentPoint) {
          $currentPoints[$memberId] = $currentPoint / 10;
          if (in_array($memberId, $dutyMembers)) {
            $currentPoints[$memberId] += 100;
          }
        }
        $finalList[$day]['developers'] = $dutyMembers;
      }
    }

    if ($this->request->isAjax()) {
      echo json_encode($finalList);
      exit();
    }

    $this->view->today = date('j');
    $this->view->workDays = $finalList;
    $this->view->memberNames = $memberNames;
    $this->view->currentUser = $this->getSessionUser();
  }

  public function addDutyDeveloperAction()
  {
      if ($this->request->isAjax()) {
          $response = new Response();

          $developer = $this->request->getPost('developer');
          $day = $this->request->getPost('day');

          $message = DutyHistoryNew::addDeveloper($developer, date('Y-m') . '-' . $day);

          if(count($message)) {
              $response->setStatusCode(500, 'Internal Server Error');
              $response->setContent(json_encode($message));
          } else {
              $response->setStatusCode(200, 'OK');
              $response->setContent(json_encode([
                  'duty' => $developer
              ]));
          }

          return $response;
      }

      return false;
  }

  public function removeDutyDeveloperAction()
  {
      if ($this->request->isAjax()) {
          $response = new Response();

          $developer = $this->request->getPost('developer');
          $day = $this->request->getPost('day');

          $message = DutyHistoryNew::removeDeveloper($developer, date('Y-m') . '-' . $day);

          if(count($message)) {
              $response->setStatusCode(500, 'Internal Server Error');
              $response->setContent(json_encode($message));
          } else {
              $response->setStatusCode(200, 'OK');
              $response->setContent(json_encode([
                  'success' => true
              ]));
          }

          return $response;
      }

      return false;
  }

  public function getDevelopersAction()
  {
      if ($this->request->isAjax()) {
          $response = new Response();

          $dutyIds = $this->request->get('dutyIds');
          $sql = 'User.user_id NOT IN (2,5,4,14,25,64,' . implode(',', $dutyIds) . ")";

          $developersForDuty = User::getDevelopers($sql);

          if(count($developersForDuty)) {
              $response->setStatusCode(200, 'OK');
              $response->setContent(json_encode($developersForDuty));
          } else {
              $response->setStatusCode(500, 'Internal Server Error');
          }

          return $response;
      }

      return false;
  }

  public function weekendDutyListAction()
  {
    $holidays = DutyHistoryNew::getWeekendDaysInMonth();
    $last_date = date('Y-m',strtotime('-1 month')).'-01';
    $month_duty = [];
    foreach ($holidays as $month){
      $month_duty[$month['month']]=$month['month'];
    }

    $holiday_developers = HolidayDevelopers::getWeekendDuties($last_date, 'development');
    $dutySupports = HolidayDevelopers::getWeekendDuties($last_date, 'support');

    $holiday_work_developers = $this->buildDutiesData($holiday_developers);
    $builtDutySupports = $this->buildDutiesData($dutySupports);

    if ($this->request->isAjax() && $this->request->getPost()) {
      $devs = User::getDevelopers("User.user_id NOT IN (2,5,4,14,25,64)");
      return json_encode($devs);
    }

    $next_saturday = new DateTime('next saturday');
    $session_user = $this->getSessionUser();
    $this->view->setVars([
      'session_user'=>$session_user,
      'holidays' => $holidays,
      'developers' => $holiday_work_developers,
      'duty_supports' => $builtDutySupports,
      'today' => date('Y-m-d'),
      'months'=>$month_duty,
      "current_month"=>date('F'),
      "next_saturday"=>$next_saturday->format("Y-m-d")
    ]);
  }

  public function HolidayDevAction()
  {
    if ($this->request->isAjax() && $this->request->isPost()) {
      $developerData = $this->request->getPost();
      if (isset($developerData['delete'])) {
        $status = HolidayDevelopers::deleteDevInHolidayDay($developerData['delete']);
        ($status) ? $status = 202 : $status = 404;
        return json_encode(['status' => $status]);
      } else {
        $status = HolidayDevelopers::addDeveloper($developerData);
        ($status) ? $status = 202 : $status = 404;

        return json_encode(['status' => $status]);
      }
    }
  }

  public function getSupportsAction()
  {
    if ($this->request->isAjax()) {
      $supportIds = $this->request->get('dutySupportsIds') ? $this->request->get('dutySupportsIds') : [];
      $date = $this->request->get('date');

      $busyUsers = BusyDay::find([
          'columns' => 'user_id',
          'conditions' => 'at_busy_date <= :date: AND to_busy_date >= :date:',
          'bind' => [
              'date' => $date
          ]
      ]);

      $busyUserIds = [2, 5, 4, 14, 25, 64, 75];
      foreach ($busyUsers as $busyUser) {
        $busyUserIds[] = $busyUser->user_id;
      }

      $userIds = array_merge($busyUserIds, $supportIds);

      $builder = (new self())->modelsManager->createBuilder()
          ->from('User')
          ->columns(['User.user_id', 'User.full_name'])
          ->where('User.department = :department:', ['department' => 'support']);

      if (count($userIds) > 0) {
        $builder->notInWhere('User.user_id', $userIds);
      }

      $supports = $builder->orderBy('User.full_name')->getQuery()->execute();

      return json_encode($supports->toArray());
    }
  }

  public function createOrUpdateAndRemoveTeamAction(){
    if ($this->request->isAjax() && $this->request->isPost()){
      $data = $this->request->getPost();
      $team = 200;
      if ($data['method'] == "post") {
        $team = Teams::createTeam($data["teamName"]);
      }else if ($data['method'] == "delete"){
        $team = Teams::removeTeam($data['team_id']);
      }else if ($data['method'] == "edit"){
        $team = Teams::updateTeam($data['team_id'],$data['new_name']);
      }
      exit(json_encode([
        "status" => $team
      ]));
    }
  }

  public function addOrChangeDevInTeamAction(){
    if ($this->request->isAjax() && $this->request->isPost()){
      $this->view->disable();
      $data = $this->request->getPost();
      if ($data['for'] == "add"){
      $dev = User::findFirst(['user_id = :user_id:','bind'=>['user_id'=>$data['user_id']]]);
        $dev->team_id = ($data['team_id'] == 0) ? "" : $data['team_id'];
        $status = $dev->save();
        exit(json_encode([
          "status"=>($status) ? 200 : 404
        ]));
      }

    }

  }

  public function saveFireBaseTokenAction()
  {
    if ($this->request->isAjax() && $this->request->isPost()) {
      $token = $this->request->getPost();
      $user = $this->getSessionUser();
      if ($user['fire_base_token'] != $token['token']  || !$user['fire_base_token']) {

        $res = User::updateFireBastToken($user['user_id'], $token['token']);

        exit(json_encode([
          'success' => ($res)? true : false,
          'code' => ($res)? 202 : 404
        ]));
      }
    }
  }

  private function buildDutiesData($holidayDuties)
  {
    $builtHolidayDuties = [];

    foreach ($holidayDuties as $developer) {
      $builtHolidayDuties[] = [
          'full_name' => $developer->full_name,
          'user_id' => $developer->user_id,
          'date' => explode(" ", $developer->holiday_day)[0],
      ];
    }

    return $builtHolidayDuties;
  }
}