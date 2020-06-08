<?php
class AuthController extends AbstractController
{
  public function loginAction()
  {
    $this->view->setLayout('guest');
    $this->view->setVar('title', 'CRM SOCIALSHOPWAVE.COM');
    //$this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);

    if (Core::isAuthenticated()) {
      return $this->response->redirect('tickets');
    }

    $this->view->email_error = false;
    $this->view->password_error = false;

    $form = new LoginForm();

    if ($this->request->isPost()) {

      if($form->isValid($this->request->getPost())){
        $check = intval(Core::authenticate($_POST['email'], $_POST['password']));
        switch($check){
          case 1:
            if(!empty($_GET['goto'])){
              header("location: {$_GET['goto']}");
              exit();
            }
            $this->response->redirect('/tickets', true);
            break;
          case 0:
            $this->view->password_error = true;
            break;
          case -1:
            $this->view->email_error = true;
            break;
        }
      } else{
        $this->view->email_error = $form->getMessagesFor('email')->count() > 0;
        $this->view->password_error = $form->getMessagesFor('password')->count() > 0;
      }
    }

    $this->view->form = $form;
  }

  public function signupAction(){
    $this->view->setLayout('guest');
    $this->view->setVar('title', 'CRM SOCIALSHOPWAVE.COM');

    $invite = UserInvite::findFirst("code = '{$this->getParam('code')}'");
    $this->view->no_code = false;
    if(!$invite){
      $this->view->no_code = true;
    } else{
      $form = new SignupForm();
      $url=$_GET;

      $url=strrev( $url['_url'] );
      $str=strpos($url, "/");
      $name=substr($url, 0, $str);
      $name=strrev( $name );

      if ($this->request->isPost()) {
        $data = $this->security->hash($_POST['password']);
        $user = User::findFirst('full_name="'.$name.'"');
        $user->password=$data;
        if($user->save()){
          Core::authenticate($user->email, $_POST['password']);
          $invite->delete();
          $this->view->disable();
          return $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'index', 'action' => 'index')), true);
        } else{
          echo ('<pre style="color: #28AD17; background: #000;border: 2px dotted green; margin-top: 20px;" >');
          print_r( $user->getMessages() );
          echo "<br /> file: ".__FILE__.'  line: '.__LINE__;
          exit ("</pre>");
        }
      }

      $this->view->form = $form;
    }
  }

  public function logoutAction(){
    Core::logout();
    $this->view->disable();
    return $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'index', 'action' => 'index')), true);
  }

  public function forgotAction(){
    $this->view->setLayout('guest');
    $form = new ForgotPasswordForm();
    $email_error = false;
    $no_user_error = false;
    $wrong_email_msg = '';
    $email_sent = false;

    if($this->request->isPost()){
      $data = $this->request->getPost();
      if($form->isValid($data)){
        $user = User::findFirst("email = '{$data['email']}'");
        if(!$user){
          $no_user_error  = true;
          $email_error = true;
          $wrong_email_msg = 'There is no user with such email';
        } else{
          $recovery = new PasswordRecovery();
          $recovery->save(array(
            'user_id'=>$user->user_id,
            'code' => md5( strval( $user->email.time() ) )
          ));

          //$r = mail($user->email, 'Password recovery instructions', "Go to this address to recover your password: http://{$_SERVER['HTTP_HOST']}/password-recovery/{$recovery->user_id}/{$recovery->code}", "From: j.adilets@gmail.com");

          Mail::sendSimpleMessage($user->email, 'Password recovery instructions', "Go to this address to recover your password: http://{$_SERVER['HTTP_HOST']}/password-recovery/{$recovery->user_id}/{$recovery->code}");

          $email_sent = true;
        }
      } else{
        $email_error = $form->getMessagesFor('email')->count() > 0;
      }
    }

    $this->view->email_error = $email_error;
    $this->view->wrong_email_msg = $wrong_email_msg;
    $this->view->no_user_error = $no_user_error;
    $this->view->email_sent = $email_sent;
    $this->view->form = $form;
  }

  public function recover_passwordAction(){
    $this->view->setLayout('guest');
    $code = $this->getParam('code');
    $user_id = intval($this->getParam('user-id'));

    $row = PasswordRecovery::findFirst("user_id = {$user_id} AND code = '{$code}'");
    $user = User::findFirst($user_id);
    if ($row == false) {
      return $this->response->redirect('login');
    }

    $form = new ChangePasswordForm();

    $password_length_error = false;
    $password_match_error = false;

    if($this->request->isPost()){
      $data = $this->request->getPost();
      if(!$form->isValid($data)){
        if($form->getMessagesFor('password')->count() > 0 ){
          $password_length_error = $form->getMessagesFor('password')[0]->getMessage();
        }
        if($form->getMessagesFor('confirm')->count() > 0){
          $password_match_error = $form->getMessagesFor('confirm')[0]->getMessage() > 0;
        }
      } else{
        $user->password = $this->security->hash($data['password']);
        if($user->save()){
          $row->delete();
          return $this->response->redirect('login');
        } else{
          echo ('<pre style="color: #28AD17; background: #000;border: 2px dotted green; margin-top: 20px;" >');
          print_r( $user->getMessages() );
          echo "<br /> file: ".__FILE__.'  line: '.__LINE__;
          exit ("</pre>");
        }
      }
    }

    $this->view->form = $form;
    $this->view->password_length_error = $form->getMessagesFor('password')->count() > 0;


    $this->view->password_match_error = $form->getMessagesFor('confirm')->count() > 0;
  }

  public function update_passwordAction(){
    $form = new UpdatePasswordForm();

    $user = User::findFirst(Core::getViewerId());

    $current_error = false;
    $current_error_msg = '';

    $new_password_error = false;
    $new_password_error_msg = '';

    $success = false;
    if($this->request->isPost()){
      $data = $this->request->getPost();
      if($form->isValid($data)){

        if(!$this->security->checkHash($data['current'], $user->password)){
          $current_error = true;
          $current_error_msg = 'Password doesn\'t match';
        } else{
          $user->password = $this->security->hash($data['new-password']);
          $user->save();
          $success = true;
        }
      } else{
        if($form->getMessagesFor('current')->count() > 0){
          $current_error = true;
          $current_error_msg = $form->getMessagesFor('current')[0]->getMessage();
        }
        if($form->getMessagesFor('new-password')->count() > 0){
          $new_password_error = true;
          $new_password_error_msg = $form->getMessagesFor('new-password')[0]->getMessage();
        }
      }
    }

    $this->view->current_error = $current_error;
    $this->view->current_error_msg = $current_error_msg;
    $this->view->new_password_error = $new_password_error;
    $this->view->new_password_error_msg = $new_password_error_msg;
    $this->view->success = $success;
    $this->view->form = $form;
  }

  public function sendAction()
  {
    $page = $this->getParam('page', 1);
    $campaign_id = $this->getParam('campaign_id');
    $campaign = Campaigns::findFirst($campaign_id);

    if (!$campaign) {
      exit(json_encode(array(
        "error" => "Not found campaign"
      )));
    }
    $statuses = $campaign->getShopStatusSearch();
    $count = CampaignStatus::createList(array('page' => $page, 'campaign_id' => $campaign_id, 'statuses' => $statuses));
    exit(json_decode(array('page' => $page, 'status' => true, 'count' => $count)));
  }

  public function redirectWithCodeAction()
  {
    $url = $this->getParam("url","");
    $code = generateUniqueCode();
    $viewer = Core::getViewer();
    $pass_hash = md5($viewer['password']);
    $user_id = $viewer['user_id'];

    if (strpos($url, "?") !== false) {
      $url .= "&code={$code}&user_id={$user_id}&pass_hash={$pass_hash}";
    }else{
      $url .= "?code={$code}&user_id={$user_id}&pass_hash={$pass_hash}";
    }
    return $this->response->redirect($url);
  }
}