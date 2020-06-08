<?php
/**
 * Created by PhpStorm.
 * User: adik
 * Date: 09.10.2014
 * Time: 17:16
 */

class FeaturesController extends AbstractController {
  public function indexAction()
  {
    $params = $this->_getAllParams();
    $shop_id = $params['shop_id'];
    $features = Featurelabels::findFirst('shop_id = ' . $shop_id);
    if (!$features) {
      $features = new Featurelabels();
    } else {
      $columns = Features::find();
      foreach ($columns as $column) {
        $name = $column->label_name;
        $features->$name = NULL;
      }
      $features->save();
    }
    foreach($params as $key => $value) {
      $features->$key = $value;
    }
    if ($features->save()) {
      exit(json_encode(array('result' => 1)));
    }
    exit(json_encode(array('result' => 0, 'error' => $features->getMessages())));
  }

  public function createColumnAction()
  {
    $feature = new Features();
    $result = $feature->createLabel($this->getParam('title'), $this->getParam('description'), trim($this->getParam('name')), $this->getParam('type'), $this->getParam('options'));
    if ($result) {
      exit(json_encode(array('result' => 1)));
    }
    exit(json_encode(array('result' => 0)));
  }

  
}