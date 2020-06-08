<?php


use Phalcon\Mvc\Controller;

class AbstractLoginController extends Controller {
  public function onConstruct()
  {
    $this->view->setVar('baseUrl', $this->url->getBaseUri());
    $this->view->setVar('title', 'CRM SOCIALSHOPWAVE.COM');
  }

  public function getParam($key, $default = null)
  {
    $val = $this->dispatcher->getParam($key, array());
    if( !$val )
      $val = $this->request->getQuery($key);

    if( !$val )
      $val = $this->request->getPost($key, array(), $default);

    return $val;
  }

  public function _getAllParams()
  {
    $res = $this->dispatcher->getParams();
    $res = array_merge($res, $this->request->getQuery());
    $res = array_merge($res, $this->request->getPost());
    return $res;
  }
}