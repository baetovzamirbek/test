<?php

use Intercom\IntercomClient;
use GuzzleHttp\Exception\GuzzleException;

class SubscriptionsController extends AbstractController
{
  public function intercomWebhookAction()
  {
    $data = file_get_contents('php://input');
    $data = json_decode($data);
    $topic = $data->topic;
    $conversation_id = $data->data->item->id;
    $client_email = $data->data->item->user->email;
    $author_email = 'development@socialshopwave.com';
    if (isset($data->data->item->conversation_parts->conversation_parts[0])) {
      $message = $data->data->item->conversation_parts->conversation_parts[0];
      $author_id = $message->author->id;
      if (isset($message->author->email))
        $author_email = $message->author->email;
    }
    $this->login($author_email);

    $ticket = Ticket::findFirst([
      'conditions' => 'intercom_id = :id:',
      'bind' => ['id' => $conversation_id]
    ]);

    $subject = "From Intercom";
    if (isset($data->data->item->conversation_message->subject) && !empty($data->data->item->conversation_message->subject)) {
      $subject = 'Re: ' . strip_tags($data->data->item->conversation_message->subject);
      $subject = str_replace("\n", "", $subject);
      $subject = str_replace("\r", "", $subject);
    }
    else if (isset($data->data->item->conversation_message->body) && !empty($data->data->item->conversation_message->body)) {
      $subject = 'Re: ' . substr(strip_tags($data->data->item->conversation_message->body), 0, 50);
      $subject = str_replace("\n", "", $subject);
      $subject = str_replace("\r", "", $subject);
    }

    if (!$ticket) {
      $ticket = new Ticket();
      $ticket->subject = $subject;
      $ticket->creation_date = $ticket->updated_date = date("Y-m-d H:i:s");
      $ticket->app = 'default';
      $ticket->status = 'closed';
      $ticket->intercom_id = $conversation_id;
    } else {
      $total = Post::count([
        'conditions' =>'ticket_id = :ticket_id:',
        'bind' => ['ticket_id' => $ticket->ticket_id]
      ]);
      if ($total % 30 == 0 || $total == 1) {
        try {
          $link_to_crm = "<a href=\"https://crm.growave.io/ticket/{$ticket->ticket_id}\">Link to CRM</a>";
          $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);
          $intercom->conversations->replyToConversation($conversation_id, [
            "type" => "admin",
            "admin_id" => $author_id,
            "message_type" => "note",
            "body" => $link_to_crm
          ]);
        } catch (GuzzleException $e) {}
      }
    }

    switch ($topic) {
      case 'conversation.admin.single.created':
        //Checking for auto-sending email on closing ticket feature
        if ($subject !== 'Re: What comes next') {
          if (isset($ticket->ticket_id)) {
            $ticket->save();
          } else {
            $client = Client::findFirst([
              'conditions' => 'email = :email:',
              'bind' => ['email' => $client_email]
            ]);
            if ($client) {
              if ($subject === 'Re: I noticed that you\'ve changed your theme') {
                $ticket->status = 'closed';
              }

              $ticket->client_id = $client->client_id;
              $ticket->save();

              if ($subject === 'Re: I noticed that you\'ve changed your theme') {
                $ticket_logs = new TicketLogs();
                $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);
              }

              $post = new Post();
              $post->ticket_id = $ticket->ticket_id;
              $post->text = $data->data->item->conversation_message->body;

              if ($subject === 'Re: I noticed that you\'ve changed your theme') {
                $post->text = strip_tags($data->data->item->conversation_message->body);
              }

              $post->staff_id = 0;
              $post->type = 'team';
              $post->from_intercom = 1;
              $post->creation_date = date('Y-m-d H:i:s');
              $post->save();
            }
          }
        }
        break;

      case 'conversation.admin.opened':
        if (isset($ticket->ticket_id)) {
          $ticket->save();
        } else {
          $client = Client::findFirst([
            'conditions' => 'email = :email:',
            'bind' => ['email' => $client_email]
          ]);
          if ($client) {
            $ticket->client_id = $client->client_id;
            $ticket->save();
          }
        }
        break;

      case 'conversation.admin.replied':
        $user = User::findFirst([
          'conditions' => 'intercom_id = :id:',
          'bind' => ['id' => $author_id]
        ]);
        if (!$user) {
          if ($author_email !== 'development@socialshopwave.com') {
            $user = User::findFirst([
              'conditions' => 'email = :email:',
              'bind' => ['email' => $author_email]
            ]);
          }
          if (!$user) {
            $user = User::findFirst([
              'conditions' => 'email = :email:',
              'bind' => ['email' => 'development@socialshopwave.com']
            ]);
          }
        }
        if (!isset($ticket->ticket_id)) {
          $client = Client::findFirst([
            'conditions' => 'email = :email:',
            'bind' => ['email' => $client_email]
          ]);
          if ($client) {
            $ticket->client_id = $client->client_id;
            $ticket->save();
          } else {
            break;
          }
        }
        $post = new Post();
        $post->ticket_id = $ticket->ticket_id;
        $post->text = $message->body;
        $post->staff_id = $user->user_id;
        $post->type = 'team';
        $post->from_intercom = 1;
        $post->creation_date = date('Y-m-d H:i:s');
        $post->save();
        break;

      case 'conversation.admin.assigned':
//            $assigned_id = $message->assigned_to->id;
//            $user_id = User::findFirst([
//              'conditions' => 'intercom_id = :id:',
//              'bind' => ['id' => $assigned_id]
//            ])->user_id;
//              Assigns::reAssign($ticket->ticket_id, $user_id);

//            print_slack('Reassign is success in conversation.admin.assigned', "alym_debug");
        break;

      case 'conversation.admin.closed':
        try {
          $client = Client::findFirst([
            'conditions' => 'email = :email:',
            'bind' => ['email' => $client_email]
          ]);
          if ($client) {
            $ticket->client_id = $client->client_id;
            $ticket->save();
            $link_to_crm = '<a href="https://crm.growave.io/ticket/' . $ticket->ticket_id . '">Link to CRM</a>';
            $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);
            $intercom->conversations->replyToConversation($conversation_id, [
              "type" => "admin",
              "admin_id" => $author_id,
              "message_type" => "note",
              "body" => $link_to_crm
            ]);
          }
        } catch (Exception $ex) {}
        break;

      case 'conversation.user.created':
        if (isset($ticket->ticket_id)) {
          $ticket->save();
        } else {
          $client = Client::findFirst([
            'conditions' => 'email = :email:',
            'bind' => ['email' => $client_email]
          ]);
          if ($client) {
            $ticket->client_id = $client->client_id;
            $ticket->save();

            $post = new Post();
            $post->ticket_id = $ticket->ticket_id;
            $post->text = $this->prepareClientPostText($data->data->item->conversation_message->body);
            $post->staff_id = 0;
            $post->type = 'client';
            $post->from_intercom = 1;
            $post->creation_date = date('Y-m-d H:i:s');
            $post->save();
          }
        }
        break;

      case 'conversation.user.replied':
        if (isset($ticket->ticket_id)) {
          $ticket->save();
        } else {
          $client = Client::findFirst([
            'conditions' => 'email = :email:',
            'bind' => ['email' => $client_email]
          ]);
          if ($client) {
            $ticket->client_id = $client->client_id;
            $ticket->save();
          } else {
            break;
          }
        }
        $post = new Post();
        $post->ticket_id = $ticket->ticket_id;
        $post->text = $this->prepareClientPostText($message->body);
        $post->staff_id = 0;
        $post->type = 'client';
        $post->from_intercom = 1;
        $post->creation_date = date('Y-m-d H:i:s');
        $post->save();
        break;

      default:
        break;
    }
    Core::logout();
  }

  public function createIntercomCompanyAction()
  {
    if ($this->request->isPost()) {
      $shop_url = $this->request->getPost('shop_url');
      $user_email = $this->request->getPost('user_email');

      $shop = Shops::findFirst([
        "conditions" => "url = :url:",
        "bind" => ["url" => $shop_url]
      ]);
      if ($shop) {
        $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);
        try {
          $company = $intercom->companies->getCompanies(["company_id" => $shop->shop_id]);
          $intercom_user = $intercom->users->getUser("", ["email" => $user_email]);

          $isFromCurrentCompany = false;
          foreach ($intercom_user->companies->companies as $item) {
            if ($item->company_id == $company->company_id)
              $isFromCurrentCompany = true;
          }
          if (!$isFromCurrentCompany)
            $intercom->users->create([
              "email" => $intercom_user->email,
              "companies" => [ ["company_id" => $company->company_id] ]
            ]);
        }
        catch (GuzzleException $ex) {
          try {
            $intercom_user = $intercom->users->getUser("", ["email" => $user_email]);
            if (isset($intercom_user->custom_attributes->Plan)) {
              $company = $intercom->companies->create([
                "name" => $shop->name,
                "id" => $shop->shop_id,
                "website" => $shop->url,
                "custom_attributes" => [
                  "Enabled Apps" => isset($intercom_user->custom_attributes->{'Enabled apps'}) ? $intercom_user->custom_attributes->{'Enabled apps'} : '',
                  "Company Plan" => $intercom_user->custom_attributes->Plan,
                  "Shopify plan" => $shop->shopify_plan_name,
                  "Company phone" => $shop->phone
                ]
              ]);
              $intercom->users->create([
                "email" => $intercom_user->email,
                "companies" => [ ["company_id" => $company->company_id] ]
              ]);
            }
          }
          catch (GuzzleException $e) {}
        }
      }
    }
    exit();
  }

  private function prepareClientPostText($text)
  {
    $matches = [];
    preg_match('@src="([^"]+)"@', $text, $matches);
    $text = strip_tags($text);

    if(!empty($matches))
      $text = $matches[1];

    return $text;
  }

  private function login($email)
  {
    $user = User::findFirst([
      'conditions' => 'email = :email:',
      'bind' => ['email' => $email]
    ]);
    if (!$user) {
      $user = User::findFirst(["email = 'development@socialshopwave.com'"]);
    }
    $di = \Phalcon\DI::getDefault();
    $di->get('session')->set('user', $user->toArray());
  }
}