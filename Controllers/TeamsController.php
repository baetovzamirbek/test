<?php


class TeamsController extends  \Phalcon\Mvc\Controller
{
  public function indexAction()
  {

  }

  public function proctiveMessageAction()
  {
    $url = $this->request->get('shop');
    $notificationVariant = $this->request->get('event');
    $shop = Shops::findFirst("url='" . $url . "'");
    $devShop = User::findFirst([
      'conditions' => 'email=?0',
      'bind' => [ $shop->owner ]
    ]);
    if ($shop && !$devShop) {
      $notification = Teamsnotificationsettings::find('type="' . $notificationVariant . '"');
      $teamsData = TeamsUserData::find();
      $client = Client::findFirst('email="' . $shop->owner . '"');
      $sswClient = SswClients::findFirst('client_id =' . $shop->sswclient_id);
      $package = SswPackages::findFirst('package_id =' . $sswClient->package_id);



      $prevPackage = SswSubscriptions::query()
        ->where('client_id=' . $shop->sswclient_id)
        ->orderBy('cancellation_date desc')
        ->limit(1)
        ->execute();
      $packageSubscription = SswPackages::findFirst('package_id =' . $prevPackage[0]->package_id);
      $current_date = date('Y-m-d h:i:s');
      $date = new DateTime($prevPackage[0]->creation_date);
      $now = new DateTime($current_date);
      $month = $date->diff($now)->format("%m");
      $day = $date->diff($now)->format("%d");
      $hour = $date->diff($now)->format("%h");

      if($month == '0') {
          if($day == '0') {
              if($hour == '0') {
                $date_parameter = $date->diff($now)->format(" Signed up %i mins ago");
              }else{
                $date_parameter = $date->diff($now)->format(" Signed up %h hours ago");
              }
          }else{
              $date_parameter = $date->diff($now)->format(" Signed up %d days ago");
          }
      }else{
          $date_parameter = $date->diff($now)->format(" Signed up %m months %d days ago");
      }

      if ($notificationVariant == 'downgrade') {
          $clientTicket = Client::findFirst('email="' . $shop->primary_email . '"');
          if ($prevPackage[0]->package_id != 4) {
              $ticketDowngrade = Ticket::query()
                  ->where('client_id=' . $clientTicket->client_id)
                  ->andWhere('subject like "%Why I downgraded%"')
                  ->orderBy('creation_date desc')
                  ->limit(1)
                  ->execute();
              $text = '<a style="font-weight: 700; color: #505050" href="https://crm.growave.io/shop/' . $shop->shop_id . '">' . $shop->domain . '</a> ' . $notificationVariant . 'd <a style="font-weight: 700; color: #f44336" >' . $packageSubscription->title . '</a > > <a style="font-weight: 700; color: #ff776f">' . $package->title . '</a>.' . $date_parameter . '.  <p></p><a style="font-weight: 700; color: #505050" href="https://crm.growave.io/ticket/' . $ticketDowngrade[0]->ticket_id . '">' . $client->name . '</a>';
              $text .= '- <a href="https://crm.growave.io/ticket/create/' . $client->client_id . '" > ' . $shop->owner . ' </a>';
              $text .= '-<a href="https://crm.growave.io/user/skype/' . $shop->phone . '">' . $shop->phone . '</a>';
          }else{
              $text = '<a style="font-weight: 700; color: #505050" href="https://crm.growave.io/shop/' . $shop->shop_id . '">' . $shop->domain . '</a> ' . $notificationVariant . 'd <a style="font-weight: 700; color: #f44336" >' . $packageSubscription->title . '</a > > <a style="font-weight: 700; color: #ff776f">' . $package->title . '</a>.' . $date_parameter . '.  <p></p><a style="font-weight: 700; color: #505050" href="https://crm.growave.io/client/' . $client->client_id . '">' . $client->name . '</a>';
              $text .= '- <a href="https://crm.growave.io/ticket/create/' . $client->client_id . '" > ' . $shop->owner . ' </a>';
              $text .= '-<a href="https://crm.growave.io/user/skype/' . $shop->phone . '">' . $shop->phone . '</a>';
          }
      }

      $teamDataPost = array();
      $identificationTeamsDataPost = 0;
      foreach ($teamsData as $item) {
        foreach ($notification as $itemNotification) {
          if ($itemNotification->user_id == $item->teams_user_id) {
            $teamDataPost[$identificationTeamsDataPost] = [
              'channellId' => $item->teams_channel_id,
              'serviceUrl' => $item->teams_service_url,
              'fromId' => $item->teams_from_id,
              'conversationId' => $item->teams_conversation_id,
              'text' => $text,
              'count' => count($notification),
            ];
            $identificationTeamsDataPost++;
          }
        }
      }
      $postData = http_build_query($teamDataPost);
      $url = 'https://teams-bot.growave.io/api/message/post';
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
      curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000);
      curl_setopt($ch, CURLOPT_HEADER, 'Content-Type:application/x-www-form-urlencoded');
      $result = curl_exec($ch);

      curl_close($ch);
    }
  }
}
