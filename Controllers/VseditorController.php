<?php

/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 19.07.14
 * Time: 16:01
 */
class VseditorController extends AbstractController
{
  public function indexAction()
  {
    $this->view->setLayout("vs-editor");
    $shop_id = $this->getParam('id');
    $theme_id = $this->getParam('theme_id');
    $key = $this->getParam('key');
    $filetype = 'html';

    $shop = Shops::findFirst('shop_id=' . $shop_id);
    if ($shop) {
      $api = new \Shopify\Client($shop);

      if ($this->request->isPost()) {
        $value = $this->getParam('value');

        try {
          $assetBefore = $api->call('GET', "/admin/themes/{$theme_id}/assets.json", array('id' => $theme_id, 'asset[key]' => $key));
          $api->call('PUT', "/admin/themes/{$theme_id}/assets.json", array('asset' => array('key' => $key, 'value' => $value)));

          $modify = new Modifications();
          $modify->save(array(
            'user_id' => Core::getViewerId(),
            'shop_id' => $shop_id,
            'theme_id' => $theme_id,
            'file' => $key,
            'value' => $value,
            'old_value' => $assetBefore['value'],
            'date' => time()
          ));

          exit(json_encode([
            "status" => true,
          ]));
        } catch (\Exception $e) {
          exit(json_encode([
            "status" => true,
            "message" => $e->getMessage(),
            "code" => $e->getCode()
          ]));
        }

      }

      if (strlen(strstr($key, ".js"))) {
        $filetype = 'javascript';
      }
      if (strlen(strstr($key, ".css"))) {
        $filetype = 'css';
      }
      if (strlen(strstr($key, ".json"))) {
        $filetype = 'json';
      }
      $this->view->setVar('filetype', $filetype);
      $this->view->setVar('shop', $shop);
      try {
        $allAssets = $api->call('GET', "/admin/themes/{$theme_id}/assets.json",["fields"=> 'key']);
        $asset = $api->call('GET', "/admin/themes/{$theme_id}/assets.json", array('id' => $theme_id, 'asset[key]' => $key));
        $this->view->setVar('asset_value', $asset['value']);
        $this->view->setVar('allAssets', $allAssets);
        $this->view->setVar('theme_id', $theme_id);
        $this->view->setVar('shop_id', $shop_id);
        $this->view->setVar('key', $key);
      } catch (\Exception $e) {
        print_die($e->getMessage());
      }
    }
  }
}