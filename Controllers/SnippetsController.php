<?php

/**
 * Created by PhpStorm.
 * User: Kalyskin
 * Date: 19.05.18
 * Time: 16:01
 */
class SnippetsController extends AbstractController
{

  public function kalyskinAction()
  {
    exit();
  }

  public function indexAction()
  {
    $user_id = Core::getViewerId();
    $userSnipets = UserSnippets::query()
        ->columns(['UserSnippets.id', 'UserSnippets.user_id', 'u.full_name', 'UserSnippets.title'])
        ->innerJoin("User", "u.user_id = UserSnippets.user_id", 'u')
        ->orderBy("IF(UserSnippets.user_id = {$user_id}, 0, 1), UserSnippets.user_id, UserSnippets.id DESC")
        ->execute();

    exit(json_encode(array(
        'data' => $userSnipets ? $userSnipets->toArray() : [],
        'user_id' => $user_id,
        'status' => (bool)($userSnipets && $user_id)
    )));
  }

  public function viewAction()
  {
    $id = (int)$this->request->get('id', null, 0);
    $userSnipet = UserSnippets::findFirst($id);
    exit(json_encode(array(
        'data' => $userSnipet ? $userSnipet->toArray() : [],
        'status' => (bool)$userSnipet
    )));
  }

  public function createAction()
  {
    if($this->request->isPost()) {
      $content = $this->getParam('content', "");
      $content_type = $this->getParam('content_type', "html");
      $title = $this->getParam('title', '');
      $user_id = Core::getViewerId();

      if (empty($content) || empty($title) || !$user_id) {
        exit(json_encode([
            "status" => false,
            "message" => "Required field empty! or User not found!"
        ]));
      }
      $userSnipet = new UserSnippets();
      $userSnipet->user_id = $user_id;

      $userSnipet->title = $title;
      $userSnipet->content = $content;
      $userSnipet->content_type = $content_type;
      $status = false;
      $message = "";
      if ($userSnipet->save()) {
        $status = true;
        $message = "Sucsess!";
      } else {
        foreach ($userSnipet->getMessages() as $msg) {
          $message .= $msg . '; ';
        }
      }
      exit(json_encode([
          "status" => $status,
          "data" => $status ? $userSnipet->toArray() : [],
          "message" => trim($message)
      ]));
    }
    exit(json_encode([
        "status" => false,
        "data" => [],
        "message" => "Request must be post!"
    ]));
  }

  public function deleteAction()
  {
    $id = (int)$this->getParam('id', 0);
    $user_id = Core::getViewerId();

    if (!$id || !$user_id) {
      exit(json_encode([
          "status" => false,
          "message" => "ID null! or User not found!"
      ]));
    }
    $userSnipet = UserSnippets::findFirst($id);
    if ((int)$userSnipet->user_id != (int)$user_id && (int)$user_id != 29) {
      exit(json_encode([
          "status" => false,
          "message" => "You can delete only own snippets"
      ]));
    }

    $status = false;
    $message = "";
    if($userSnipet->delete()){
      $status = true;
      $message = "Sucsess!";
    }else{
      foreach ($userSnipet->getMessages() as $msg){
        $message .= $msg.'; ';
      }
    }
    exit(json_encode([
        "status" => $status,
        "message" => trim($message)
    ]));
  }

  public function updateAction()
  {
    if ($this->request->isPost()) {
      $id = (int)$this->getParam('id', 0);
      $content = $this->getParam('content', "");
      $content_type = $this->getParam('content_type', "html");
      $title = $this->getParam('title', '');
      $user_id = Core::getViewerId();
      $userSnipet = UserSnippets::findFirst($id);
      if (!$userSnipet || empty($content) || empty($title) || !$user_id) {
        exit(json_encode([
            "status" => false,
            "message" => "Required field empty! or User not found! or Snipet not found!"
        ]));
      }
      if ((int)$userSnipet->user_id != (int)$user_id  && (int)$user_id != 29) {
        exit(json_encode([
            "status" => false,
            "message" => "You can edit only own snippets"
        ]));
      }

      $userSnipet->title = $title;
      $userSnipet->content = $content;
      $userSnipet->content_type = $content_type;
      $status = false;
      $message = "";
      if ($userSnipet->save()) {
        $status = true;
        $message = "Sucsess!";
      } else {
        foreach ($userSnipet->getMessages() as $msg) {
          $message .= $msg . '; ';
        }
      }
      exit(json_encode([
          "status" => $status,
          "data" => $status ? $userSnipet->toArray() : [],
          "message" => trim($message)
      ]));
    }
    exit(json_encode([
        "status" => false,
        "data" => [],
        "message" => "Request must be post!"
    ]));
  }

}