<?php
/**
 * Created by PhpStorm.
 * User: RAVSHAN
 * Date: 17.07.14
 * Time: 18:30
 */

class TemplateController extends AbstractController
{
  public function indexAction()
  {
    $this->view->setVar("pageTitle", "CRM Templates");
    $params = array(
      'page' => $this->getParam('page', 1),
      'ipp' => 20,
      'order' => 'keyword ASC'
    );
    $paginator = Template::getTemplatesPaginator($params);

    $this->view->setVars(array(
      'paginator' => $paginator
    ));
  }

  public function createAction()
  {
    $keyword = $this->getParam('keyword', '');
    $body = $this->getParam('body', '');
    $success = true;
    $message = '';
    if($this->request->isPost()){
      if(Template::findFirst("keyword = '" . $keyword . "'"))
      {
        $success = false;
        $message = "This keyword already is exists!";
      }
      else{
        $template = new Template();
        if(!$template->save(array('keyword' => $keyword, 'body' => $body))){
          $success = false;
          foreach($template->getMessages() as $msg)
          {
            $message .= $msg . "\n";
          }
        }
      }
    }

    $this->view->setVars(array(
      'body' => $body,
      'keyword' => $keyword,
      'success' => $success,
      'message' => $message
    ));
  }

  public function editAction()
  {
    $success = true;
    $message = '';
    $params = $this->dispatcher->getParams();
    if (!isset($params[0])) {
      $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'template', 'action' => 'index')), true);
      $success = false;
      $message = "Keyword is required";
    }

    if($success){
      $keyword = $this->getParam('keyword','');
      $body = $this->getParam('body', '');
      $template = Template::findFirst("keyword = '" . $params[0] . "'");
      if(!$template){
        $success = false;
        $message = "Template not found!";
      }

      if($success && $this->request->isPost()){
        if($keyword != $template->keyword && Template::findFirst("keyword = '" . $keyword . "'")){
          $success = false;
          $message = "This keyword already is exists!";
        }
        else{
          $template->keyword = $keyword;
          $template->body = $body;
          if($template->save()){
            $success = true;
            $message = "Your changes has been saved!";
          }
          else{
            $success = false;
            foreach($template->getMessages() as $msg)
            {
              $message .= $msg . "\n";
            }
          }
        }
      }
    }
    $this->view->setVars(array(
      'success' => $success,
      'message' => $message
    ));
  }

  public function deleteAction()
  {
    if($this->request->isPost() && $this->getParam('keyword', false)){
      $keyword = $this->getParam('keyword');
      $template = Template::findFirst("keyword = '" . $keyword . "'");
      if($template){
        $template->delete();
      }
    }
  }
} 