<?php
/**
 * Created by PhpStorm.
 * User: Дмитрий
 * Date: 27.07.2019
 * Time: 15:17
 */

class HelpcrunchController extends AbstractController {


  public function setEventsAction() {

    $params = $this->_getAllParams();

    if (!$this->request->isPost() || !isset($params['data']['email']) || empty($params['data']['email'])) {
      exit(json_encode(['error' => 'error']));
    }

    $crm_client_id =  Client::findFirst(['email = :email:', 'bind' => ['email' => $params['data']['email']]]);
    if ($crm_client_id) {
      $crm_client_id = $crm_client_id->client_id;
    }
    else {
      $crm_client_id = 0;
    }

    $ticket = Ticket::findFirst (
          [
            'helpcrunch_id = :help_id: and client_id = :client_id:',
            'bind' => ['help_id' => $params['data']['conversation_id'], 'client_id' => $crm_client_id]
          ]
    );


    if(!$ticket && $params['data']['params_type'] !== 'agentMessage') {
      $subject = 'Re: ' . mb_substr(strip_tags($params['data']['text']), 0, 50) . '...';
      $ticket = new Ticket();
      $ticket->subject = $subject;
      $ticket->creation_date = $ticket->updated_date = date("Y-m-d H:i:s");
      $ticket->app = 'instagram';
      $ticket->status = 'closed';
      $ticket->intercom_id = NULL;
      $ticket->helpcrunch_id = $params['data']['conversation_id'];
      $ticket->client_id = $crm_client_id;
      $ticket->ticket_type = 'user_ticket';
      $ticket->save();
    }

    if($params['data']['params_type'] == 'customerMessage') {
      $post = new Post();
      $post->ticket_id = $ticket->ticket_id;
      $post->text = $params['data']['text'];
      $post->staff_id = 0;
      $post->type = 'client';
      $post->from_intercom = 1;
      $post->creation_date = date('Y-m-d H:i:s');
      $post->save();
    }

    if($ticket && $params['data']['params_type'] == 'agentMessage') {
      $post = new Post();
      $post->ticket_id = $ticket->ticket_id;
      $post->text = $params['data']['text'];
      $post->staff_id = 0;
      $post->type = 'team';
      $post->from_intercom = 1;
      $post->creation_date = date('Y-m-d H:i:s');
      $post->save();
    }

    return json_encode(['succes' => 'true']);
  }

}