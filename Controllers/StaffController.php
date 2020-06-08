<?php

class StaffController extends AbstractController {
  public function developersAction(){
    $users_data = User::getDevelopers();
//    print_die($users_data['developers']);
    $this->view->setVars(['developers'=>$users_data['developers'],'avatars'=>$users_data['avatars']]);


  }
  public function changeStatusAction(){

  }

  public function staffPublicAction() {
      header("Location: /staff/");
      exit();
  }
}