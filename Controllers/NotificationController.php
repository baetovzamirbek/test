<?php

class NotificationController extends AbstractController
{
  public function indexAction()
  {
      $sender = new stdClass();
      $sender->name = 'Ermek Galiev';

      $recipient = new stdClass();
      $recipient->name = 'Adilet Ibragimov';

      $ticket = new stdClass();
      $ticket->subject = 'Help to install app';
      $ticket->id = 1;

      $client = new stdClass();
      $client->email = 'carlos.roman@gmail.com';


      $post = new stdClass();
      $post->text = 'test';

      $this->view->sender = $sender;
      $this->view->ticket = $ticket;
      $this->view->client = $client;
      $this->view->recipient = $recipient;
      $this->view->post = $post;
  }

//  public function addAction()
//  {
//    $result = Notification::addNotification('team_post', 15, 62, array(array('user' => 2), array('post' => 65)));
//    print_die($result);
//  }
//
//  public function sendAction()
//  {
//    /**
//     * @var Notification $notification
//     */
//
////    $notification = Notification::findFirst();
////    $notification->sendGtalk();
////    print_die(1);
//
//    $ticket = Ticket::findFirst(54);
//    NotificationService::newTicket($ticket);
//  }

//  public function sendSuperOptimizedAction()
//  {
//    //gtalk
//    $notifications = Notification::find("gtalk = 0");
//
//    $items = array();
//    $notify_types = array();
//    // collect items (type and ids) and notification types
//    foreach ($notifications as $notification) {
//      $params = $notification->getSortedParams();
//
//      $items = array_merge_recursive($items, $params);
//      $items['user'][] = $notification->recipient_id;
//      $items['ticket'][] = $notification->ticket_id;
//
//      $notify_types[] = $notification->type;
//    }
//
//    // fetch items
//    $fetched_items = array();
//    foreach ($items as $type => $ids) {
//      $item_list = Core::_()->getItems($type, $ids);
//      foreach ($item_list as $item) {
//        $fetched_items[$type][$item->getIdentity()] = $item;
//      }
//    }
//
//    // fetch notification types
//    $types = Notificationtype::findByKeys($notify_types);
//    $fetched_types = array();
//    foreach ($types as $notify_type) {
//      $fetched_types[$notify_type->key] = $notify_type;
//    }
//  }
}



