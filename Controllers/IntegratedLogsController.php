<?php

use Phalcon\Mvc\Controller;
use Intercom\IntercomClient;
use Phalcon\Mvc\View\Simple as View;

class IntegratedLogsController extends Controller
{
  protected $simpleView;

  public function initialize()
  {
    $this->simpleView =  new View();
    $this->simpleView->setDI($this->getDI());
  }

  public function indexAction($token)
  {
    if ($token !== md5(SSW_UNIQUE_KEY)) {
      return;
    }

    $this->simpleView->setViewsDir($this->view->getViewsDir());

    $date = date('Y-m-d H:i:s', strtotime('-48 hours'));
    $integratedLogs = IntegratedLogs::getLogs($date);
    $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);

    foreach ($integratedLogs as $integratedLog) {
      $isClientAnswered = Ticket::isClientAnswered($integratedLog->client_id, $integratedLog->created_at);

      if (!$isClientAnswered) {
        try {
          $intercom->messages->create([
              "message_type" => "email",
              "subject" => "What comes next",
              "body" => $this->simpleView->render('integrated-logs/index', ['fullName' => $integratedLog->client_name]),
              "template" => "personal",
              "from" => [
                  "type" => "admin",
                  "id" => "1846591"
              ],
              "to" => [
                  "type" => "user",
                  "email" => $integratedLog->client_email
              ]
          ]);
        } catch (Exception $exception) {
          print_slack(['intercom-error' => $exception->getMessage()]);
        }
      }

      $integratedLog->delete();
    }

    exit(1);
  }
}