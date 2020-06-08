<?php

use Phalcon\Db;
use Shopify\Image\Image;

class UserController extends AbstractController {
  public function viewAction(){

  }

  public function skypeAction($number){
    $this->view->number=$number;
  }

  public function editAction(){
    $user = User::findFirst(intval(Core::getViewerId()));
    $form = new UserEditForm($user, array('edit'=>true));
    $this->view->user = $user;

    $email_error = false;
    $full_name_error = false;

    if ($this->request->isPost()) {
      if($form->isValid($this->request->getPost())){
        $form->getEntity()->save($this->request->getPost());
      } else{
        if($form->getMessagesFor('email')->count() > 0){
          $email_error = true;
        }
        if($form->getMessagesFor('full_name')->count() > 0){
          $full_name_error = true;
        }
      }
    }

    $this->view->email_error = $email_error;
    $this->view->full_name_error = $full_name_error;
    $this->view->form = $form;
  }
  public function uploadifyAction()
  {
    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
    $targetFolder = realpath('.').'/img';

    if (!empty($_FILES)) {
      $tempFile = current($_FILES['files']['tmp_name']);
      $targetPath = $targetFolder;
      $user = User::findFirst(Core::getViewerId());
      $ts = time();
      $name = current($_FILES['files']['name']);
      $tmp = explode('.', $name);
      $ext = $tmp[count($tmp)-1];
      $targetFile = rtrim($targetPath,'/')."/avatar-{$user->user_id}-{$ts}.{$ext}";

      // Validate the file type
      $fileTypes = array('bmp', 'jpg','jpeg','gif','png'); // File extensions
      $fileParts = pathinfo($name);

      if (in_array($fileParts['extension'],$fileTypes)) {
        // Resize image (profile)
        $image = Image::factory();
        $image->open($tempFile);
        $size = min($image->height, $image->width);
        $x = ($image->width - $size) / 2;
        $y = ($image->height - $size) / 2;

        $image->resample($x, $y, $size, $size, 100, 100)
          ->write($targetFile)
          ->destroy();

        $s3FilePath = "/avatars/{$user->user_id}-{$ts}.{$ext}";
        // s3 upload
        $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
        $s3Client = $aws->get("s3");
        $s3Client->putObject(array(
          'Bucket' => 'crmgrowave',
          'Key' => $s3FilePath,
          'ACL' => 'public-read',
          'Body' => fopen($targetFile, 'r+'),
          'CacheControl' => 'max-age=604800'
        ));

        if (file_exists($targetFile)) {
          @unlink($targetFile);
        }

        $file = File::findFirst("parent_id = {$user->user_id} AND parent_type='user' AND type='avatar'");
        if ($file == false) {
          $file = new File();
          $file->parent_id = $user->user_id;
          $file->parent_type = 'user';
          $file->type = 'avatar';
        } elseif (strstr($file->path, 'avatars/') !== false) {
          $s3Client->deleteObject(array(
            'Bucket' => 'crmgrowave',
            'Key'    => $file->path
          ));
        } else {
          @unlink(realpath('.').$file->path);
        }
        $file->ext = $ext;
        $file->name = $name;
        $file->size = current($_FILES['files']['size']);
        $file->path = $s3FilePath;
        $file->save();

        exit(json_encode(array(
          'files' => array(
            $file->path
          )
        )));
      } else {
        echo 'Invalid file type.';
      }
    }
  }

  public function inviteAction() {
    $form = new InviteForm();
    $success = false;
    if($this->request->isPost()){
      $data = $this->request->getPost();
      $userExistsFirstCheck = User::findFirst(['email = :email:', 'bind' => ['email' => $data['email']]]);
      $userExistsSecondCheck = UserInvite::findFirst(['email = :email:', 'bind' => ['email' => $data['email']]]);
      if($form->isValid($data) && $userExistsFirstCheck == false && $userExistsSecondCheck == false) {
        $invite = new UserInvite();
        $db = Phalcon\DI::getDefault()->get('db');
        $sql = "SELECT * FROM `staff_members` ORDER BY staff_id DESC  LIMIT 1";
        $staff_info = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
        $thisStaffId = $staff_info[0]['staff_id']+1;
        $staffIp=$staff_info[0]['staff_ip']+1;

        $invite->email = $data['email'];
        $invite->code = $data['name'];
        $invite->timestamp = time();
        //Mail::sendSimpleMessage($data['email'], 'CRM sign up invitation', "Hi, go to the following address to sign up. {$_SERVER['HTTP_HOST']}/sign-up/{$invite->code}");

        if($invite->save()) {
          $staff=new StaffMembers();
          $staff->staff_id=$thisStaffId;
          $staff->staff_name=$data['nickname'];
          $staff->staff_ip= $staffIp;
          $staff->lalte=0;
          $staff->staff_duty=0;
          $staff->staff_duty_start=0;
          $staff->enabled=1;

          $user=new User();
          $user->staff_id=$thisStaffId;
          $user->full_name=$data['name'];
          $user->nickname=$data['nickname'];
          $user->department=$data['department'];
          $user->email=$data['email'];
          $user->password=$this->security->hash($data['password']);
          $user->status=1;
          ($data['department'] == 'support') ? $user->duty_level = '' : $user->duty_level = 'critical';
          if($user->save()) {
            if($staff->save()) {
              $success = true;
            }
          }
        } else{
          echo ('<pre style="color: #28AD17; background: #000;border: 2px dotted green; margin-top: 20px;" >');
          print_r( $invite->getMessages() );
          echo "<br /> file: ".__FILE__.'  line: '.__LINE__;
          exit ("</pre>");
        }
      } else {
          $this->view->error = true;
          foreach ($form->getMessages() as $message)
          {
              echo $this->flash->error($message);
          }
          echo ($userExistsFirstCheck || $userExistsSecondCheck) ? $this->flash->error('User has already exists!') : '';
      }
    }

    $this->view->success = $success;
    $this->view->form = $form;
  }

  public function settingTeamAction(){
    $settings=$this->request->getPost('type');
    $newSettings= (object) $settings;

    if ($this->request->isPost()) {
      $user_id=Core::_()->getViewerId();
      $s = Teamsnotificationsettings::find("user_id =".$user_id);

      foreach ($s as $setting) {
          $setting->delete();
      }

      foreach ($newSettings as $setting) {
        $set = new Teamsnotificationsettings();
        $set->save([
          'user_id' => $user_id,
          'type' => $setting
        ]);
      }
    }
    return json_encode('Your changes have been saved!');
  }

  public function settingAction()
  {
    $user_id = Core::_()->getViewerId();
    $this->view->message = '';
    if ($this->request->isPost()) {
      $settings = Notificationsetting::find("user_id = {$user_id}");
      foreach ($settings as $setting) {
        $setting->delete();
      }
      foreach ($this->request->getPost() as $setting) {
        $s = new Notificationsetting();
        $s->save(array(
          'user_id' => $user_id,
          'type' => $setting
        ));
      }

      $this->view->message = "Your changes have been saved!";
    }


    $this->view->type=Teamsnotificationsettings::find("user_id =".$user_id);
    $form = new NotificationForm();
    $this->view->form = $form;
    $this->view->types = Notificationtype::find();
  }

}
