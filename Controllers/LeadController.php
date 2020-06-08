<?php
/**
 * Created by PhpStorm.
 * User: RAVSHAN
 * Date: 03.09.14
 * Time: 17:13
 */

class LeadController extends AbstractController
{
  public function indexAction()
  {
    $this->view->setVar("pageTitle", "CRM Leads");
    $params = array(
      'page' => $this->getParam('page', 1),
      'ipp' => 30,
      'list_id' => $this->getParam('list', ''),
      'status' => $this->getParam('status', ''),
      'keyword' => $this->getParam('keyword', '')
    );

    $lists = Twlist::find(array('order' => 'list_id ASC'));
    $paginator = Lead::getLeadsPaginator($params);

    $this->view->setVars(array(
      'paginator' => $paginator,
      'params' => $params,
      'lists' => $lists
    ));
  }

  public function editAction()
  {
    if($this->request->isPost()){
      $leadId = intval($this->getParam('lead_id', 0));
      $lead = Lead::findFirst($leadId);
      if($lead){
        $data = array(
          'list_id' => $this->getParam('list_id', $lead->list_id),
          'status' => $this->getParam('status', $lead->status),
          'note' => $this->getParam('note', $lead->note)
        );
        if($lead->save($data)){
          exit(json_encode(array('success' => true, 'message' => 'Your changes have been saved!')));
        }else{
          $message = '';
          foreach($lead->getMessages() as $msg){
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

  public function historyAction()
  {
    $leadId = intval($this->getParam('lead_id', 0));
    $history = History::find("lead_id = " . $leadId);

    $this->view->setVars(array(
      'history' => $history
    ));
  }
}