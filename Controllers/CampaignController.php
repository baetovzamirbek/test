<?php
/**
 * Created by PhpStorm.
 * User: USER
 * Date: 20.11.2014
 * Time: 16:08
 */

class CampaignController extends AbstractController
{

  public function indexAction()
  {
    $campaigns = Campaigns::find();

    $this->view->campaigns = $campaigns;
    $this->view->c_count = $campaigns->count();
  }

  public function createAction()
  {
    $this->view->setVar('errors', array());
    $this->view->setVar('text', '');
    $this->view->setVar('subject', '');

    if( !$this->request->isPost() ) {
      return;
    }

    $post_data = $this->request->getPost();

    $errors = array();

    if( !isset($post_data['subject']) || !$post_data['subject'] ) {
      $errors[] = 'Subject is Required';
    }

    if( !isset($post_data['text']) || !$post_data['text'] ) {
      $errors[] = 'Message is Required';
    }


    if( !isset($post_data['campaign_types']) || empty($post_data['campaign_types']) || !count($post_data['campaign_types']) ) {
      $errors[] = 'Please select at least one Type';
    }

    if( count($errors) ) {
      $this->view->setVar('errors', $errors);
      return;
    }

    $post_data['campaign_types'] = array_keys($post_data['campaign_types']);

    $campaign = new Campaigns();
    $campaign->subject = $post_data['subject'];
    $campaign->body = $post_data['text'];
    $campaign->type = json_encode($post_data['campaign_types']);
    $campaign->status = 'pending';

    if( !$campaign->save() ) {
      print_die($campaign->getMessages());
    }

    $this->response->redirect('/campaign/index', true);
  }

  public function testSendAction()
  {
    if( !$this->request->isPost() ) {
      exit(json_encode(array(
        'status' => false,
        'message' => 'Invalid Method!!!'
      )));
    }

    $post_data = $this->request->getPost();

    if( !isset($post_data['id']) || !$post_data['id'] ) {
      exit(json_encode(array(
        'status' => false,
        'message' => 'Campaign Id is Required!!'
      )));
    }
    if( !isset($post_data['email']) || !$post_data['email'] ) {
      exit(json_encode(array(
        'status' => false,
        'message' => 'Email is Required!!'
      )));
    }

    $campaign = Campaigns::findFirst('campaign_id = ' . $post_data['id']);

    if( !$campaign ) {
      exit(json_encode(array(
        'status' => false,
        'message' => 'Can not find Campaign with id ' . $post_data['id']
      )));
    }

    try {
      Mail::sendSimpleMessage($post_data['email'], $campaign->subject, $campaign->body);
    }  catch(Exception $e){
      exit(json_encode(array(
        'status' => false,
        'message' => $e->getMessage()
      )));
    }

    exit(json_encode(array(
      'status' => true,
    )));
  }

  public function sendAction()
  {
    $page = $this->getParam('page', 1);
    $campaign_id = $this->getParam('campaign_id', 0);
    $campaign = Campaigns::findFirst($campaign_id);

    if (!$campaign) {
      exit(json_encode(array(
        'status' => false,
        "error" => "Not found campaign"
      )));
    }
    $statuses = $campaign->getShopStatusSearch();
    $count = CampaignStatus::createList(array('page' => $page, 'campaign_id' => $campaign_id, 'statuses' => $statuses));

    if( $count == 0 ) {
      $campaign->status = 'process';
      $campaign->save();
    }

    exit(json_encode(array(
      'page' => intval($page),
      'status' => true,
      'count' => intval($count)
    )));
  }

  public function bodyAction()
  {
    $campaign_id = $this->getParam('campaign_id', 0);
    $campaign = Campaigns::findFirst($campaign_id);

    if (!$campaign) {
      exit(json_encode(array(
        'status' => false,
        "error" => "Not found campaign"
      )));
    }

    exit($campaign->body);
  }

  public function unsubscribeAction()
  {
    $token = $this->getParam('token', '');
    if($token){
      $campaignStatus = CampaignStatus::findFirst(array(
        "conditions" => "unsubscribe_token = ?1",
        "bind"       => array(1 => $token)
      ));

      if($campaignStatus){
        $email = $campaignStatus->email;
        $unsubscribe = Unsubscribe::findFirst(array(
          "conditions" => "email = ?1",
          "bind"       => array(1 => $email)
        ));
        if(!$unsubscribe){
          $unsubscribe = new Unsubscribe();
          $unsubscribe->save(array(
            'email' => $email,
            'date' => date('Y-m-d H:i:s')
          ));
        }
        /** @var  $manager \Phalcon\Mvc\Model\Manager */
        $di = \Phalcon\DI::getDefault();
        $manager = $di->get('modelsManager');

        $query = "DELETE FROM CampaignStatus WHERE email = :email:";
        $manager->executeQuery($query, array(
          'email' => $email
        ));
      }
    }
    exit();
  }
}