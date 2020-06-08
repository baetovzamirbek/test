<?php
/**
 * Created by PhpStorm.
 * User: RAVSHAN
 * Date: 04.09.14
 * Time: 17:19
 */

/*require_once(__DIR__ . '/../library/twitteroauth/twitteroauth.php');
require_once(__DIR__ . '/../config/twitter.php');*/
class ListController extends AbstractController
{
  public function indexAction()
  {
    $this->view->setVar("pageTitle", "CRM Lists");

    $params = array(
      'ipp' => 30,
      'page' => $this->getParam('page', 1),
      'order' => 'list_id ASC'
    );

    $paginator = Twlist::getListsPaginator($params);

    $this->view->setVars(array(
      'paginator' => $paginator
    ));
  }

  public function editAction()
  {
    if($this->request->isPost()){
      $listId = intval($this->getParam('list_id', 0));
      $list = Twlist::findFirst($listId);
      if($list){
        $data = array(
          'status' => $this->getParam('status', $list->status)
        );
        if($list->save($data)){
          exit(json_encode(array('success' => true, 'message' => 'Your changes have been saved!')));
        }else{
          $message = '';
          foreach($list->getMessages() as $msg){
            $message .= $msg . "\n";
          }
          exit(json_encode(array('success' => false, 'message' => $message)));
        }
      }else{
        exit(json_encode(array('success' => false, 'message' => 'Lead not found!')));
      }
    }else{
      exit(json_encode(array('success' => false, 'message' => 'Unauthorized request!')));
    }
  }

  public function testAction()
  {
    /*$user_names = array(
      'johnjob1988',
      'adilets',
      'jobadilet',
      'UlanT2',
      'burya1988',
      'farside312',
      'microsoft',
      'iphone',
      'samsung'
    );
    foreach($user_names as $user_name)
    {
      $twitter_user = Twitter::getUser($user_name);
      $lead = new Lead();
      $data = array(
        'name' => $twitter_user->name,
        'tw_url' => 'https://twitter.com/' . $user_name,
        'fb_url' => null,
        'site_url' => 'https://' . $user_name . '.myshopify.com',
        'list_id' => 5,
        'following' => $twitter_user->friends_count,
        'followers' => $twitter_user->followers_count,
        'status' => 'active',
        'created' => time(),
        'note' => null,
        'last_checked_time' => 0,
        'checked_count' => 0
      );

      if(!$lead->save($data)){
        print_arr($lead->getMessages());
      }

      print_arr($lead->toArray());
    }

    print_die('success');*/
  }
} 