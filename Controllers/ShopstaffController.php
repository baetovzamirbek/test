<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 19.07.14
 * Time: 17:28
 */
class ShopstaffController extends AbstractController {
  public function createAction()
  {
    if ($this->request->isPost() && $this->request->getPost('shop_id') && $this->request->getPost('client_id')) {
      $type = $this->request->getPost('type', null , 'staff');
      $save = Shopstaffs::createShopStaff((int)$this->request->getPost('shop_id'), (int)$this->request->getPost('client_id'), $type);
      if ($save)
        exit(json_encode(true));
      else
        exit(json_encode(false));
    }
    exit(json_encode(false));
  }

  public function deleteAction()
  {
    if ($this->request->isPost() && $this->request->getPost('shop_id') && $this->request->getPost('client_id')) {
      $save = Shopstaffs::deleteShopStaff((int)$this->request->getPost('shop_id'), (int)$this->request->getPost('client_id'));
      if ($save)
        exit(json_encode(true));
      else
        exit(json_encode(false));
    }
    exit(json_encode(false));
  }

  public function getShopAction()
  {
    $params = $this->_getAllParams();
    $key_word = $this->getParam('query', '');
    $client = Client::findFirst('client_id = ' . $params['client_id']);
    $shops = Shops::getForStaffShops($client, $key_word);

    $response = array(
      'query' => $key_word,
      'suggestions' => array(

      )
    );

    foreach( $shops  as $shop ) {
      $response['suggestions'][] = array(
        'data' => array(
          'shop_id' => $shop->shop_id,
          'sswclient_id' => $shop->sswclient_id,
          'name' => $shop->url
        ),
        'value' => $shop->url
      );
    }

    exit(json_encode($response));

  }

  public function getStaffAction()
  {
    $params = $this->_getAllParams();
    $key_word = $this->getParam('query', '');
    $shop = Shops::findFirst('shop_id = ' . $params['shop_id']);
    $clients = Client::getStaffForShop($shop, $key_word);

    $response = array(
      'query' => $key_word,
      'suggestions' => array(

      )
    );

    foreach( $clients  as $client) {
      $response['suggestions'][] = array(
        'data' => array(
          'client_id' => $client->client_id,
          'name' => $client->name
        ),
        'value' => $client->name . '('. $client->email .')'
      );
    }

    exit(json_encode($response));

  }

}