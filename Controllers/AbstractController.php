<?php

use Phalcon\Mvc\Controller;

class AbstractController extends Controller
{
  public function onConstruct()
  {
    $this->view->setVar('baseUrl', $this->url->getBaseUri());
    $this->view->setVar('pageTitle', 'CRM SocialShopWave.com');
    $this->view->setvar('gviewer', Core::_()->getViewer());
    $this->view->user = User::findFirst(intval(Core::getViewerId()));;

    if (!empty($_GET['ay'])) {
      phpinfo();
      exit;
    }

    $flag = !empty($_REQUEST['from_ssw']) ? $_REQUEST['from_ssw'] : false;
    if ($flag === false) {
      $flag = !empty($_REQUEST['from']) ? $_REQUEST['from'] : false;
    }
    $ssw_token = !empty($_REQUEST['ssw_token']) ? $_REQUEST['ssw_token'] : false;
    $ssw_token = check4UniqueKey($ssw_token);

    $controllerName = $this->dispatcher->getControllerName();
    if ( $controllerName == 'task' ) {
      $isAuthenticated = Core::isAuthenticated() ||  $flag == 1 ||  $flag == 'hehe';
    } else if ($controllerName == 'subscriptions') {
      $isAuthenticated = true;
    } else if ($controllerName == 'helpcrunch') {
        $isAuthenticated = true;
    } else {
      $isAuthenticated = Core::isAuthenticated() ||  $ssw_token;
    }


//    $isAuthenticated = Core::isAuthenticated()
//      || ( (!empty($_POST['from_ssw']) && $_POST['from_ssw'] == 1) )
//      || ( (!empty($_GET['from']) && $_GET['from'] == 'hehe')  )
//    ;

    if (!$isAuthenticated /*|| !$ssw_token_get || !$ssw_token_post*/) {
      //print_slack('CRM PROBLEM isAuthenticated: '.$isAuthenticated.', ssw_token: ' . $ssw_token, 'asmp_debug');
      //print_slack($_REQUEST, 'asmp_debug');
    }


    if (!$isAuthenticated && !(false !== strpos($_SERVER['REQUEST_URI'], '/password-recovery/') || false !== strpos($_SERVER['REQUEST_URI'], '/sign-up/') || preg_match('/\\/login/', $_SERVER['REQUEST_URI']) || in_array($_SERVER['REQUEST_URI'], array('/auth/forgot/', '/ticketfiles/attach', '/user/uploadify')))) {
      $goto = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
      $redirectUrl = "location:https://{$_SERVER['SERVER_NAME']}/login?goto=https://{$goto}";
      $redirectUrl = str_replace('crm.socialshopwave.com', 'crm.growave.io', $redirectUrl);
      header($redirectUrl);
      exit();
    }
    if ($this->request->isAjax()) {
      $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
    } else {
      $controllerName = $this->dispatcher->getControllerName();
      $actionName = $this->dispatcher->getActionName();
      $this->view->setVar('gControllerName', $controllerName);
      $this->view->setVar('gActionName', $actionName);
    }

    if ($controllerName == 'helpcrunch') {
      $this->response->setHeader('Access-Control-Allow-Origin', 'https://growave.io');
      $this->response->setHeader("Access-Control-Allow-Methods", 'POST');
      $this->response->setHeader("Access-Control-Allow-Headers", 'Origin, X-Requested-With, Content-Range, Content-Disposition, Content-Type, Authorization');
      $this->response->sendHeaders();
    }
  }

  public function getSessionUser(){
    $di = \Phalcon\DI::getDefault();
    return $di->get('session')->get('user');
  }

  public function getParam($key, $default = null)
  {
    $val = $this->dispatcher->getParam($key, array());
    if (!$val)
      $val = $this->request->getQuery($key);

    if (!$val)
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

  public function checkAuth($redirect = false)
  {
    if (!Core::isAuthenticated()) {
      $this->view->disable();
      if ($redirect == false) {
        return $this->response->redirect('login', true);
      } else {
        if (is_array($redirect)) {
          $redirect = $this->url->get($redirect);
        }
        return $this->response->redirect($redirect, true);
      }
    }

    return true;
  }
}
