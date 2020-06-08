<?php

use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View\Simple as View;

class DevelopmentShopsLogsController extends Controller
{
  protected $simpleView;

  public function initialize()
  {
    $this->simpleView =  new View();
    $this->simpleView->setDI($this->getDI());
  }

  public function developmentShopsAction($token)
  {
    if ($token !== md5(SSW_UNIQUE_KEY)) {
      return;
    }

    $date = date('Y-m-d H:i:s', strtotime('-1 day'));

    $developmentShopsLogs = DevelopmentShopsLogs::getNotSentLogs($date);
    $viewer = User::findFirst(26); // Kalys Salmakbaev
    $this->simpleView->setViewsDir($this->view->getViewsDir());

    foreach ($developmentShopsLogs as $developmentShopLogs) {
      $client = Client::findFirst([
        'conditions' => 'email = :email:',
        'bind' => [
          'email' => $developmentShopLogs->client_email
        ]
      ]);

      try{
        $notificationContent = $this->simpleView->render('development-shops-logs/development-shops', [
          'clientName' => $client->name,
          'shop' => $developmentShopLogs->shop
        ]);
        $message_id = Mail::sendHTML($viewer, $client, 'Are you a Shopify expert?', $notificationContent);

        if ($message_id) {
          $developmentShopLogs->message_id = $message_id;
          $developmentShopLogs->status = 1;
          $developmentShopLogs->sent_date = date('Y-m-d H:i:s');
          $developmentShopLogs->save();
        }
      } catch(\Exception $exception) {
        print_slack([
          $exception->getLine(),
          $exception->getMessage()
        ], 'turar');
      }
    }

    exit(0);
  }
}