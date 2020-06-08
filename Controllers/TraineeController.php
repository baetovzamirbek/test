<?php
class TraineeController extends AbstractLoginController
{
  public function indexAction()
  {
    header('Access-Control-Allow-Origin: *');
    return (file_get_contents("/mnt/www/crm.growave.io/public/json/mock-data-for-frontend-test-work.json"));
  }
}