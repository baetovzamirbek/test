<?php

/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 19.07.14
 * Time: 16:01
 */

use Intercom\IntercomClient;
use Phalcon\Mvc\View\Simple as SimpleView;

class ShopsController extends AbstractController
{


  public function indexAction()
  {

    $this->view->setVar("pageTitle", "CRM Sites");
    $params = $this->_getAllParams();

    $db = Phalcon\DI::getDefault()->get('ssw_database');
    if (isset($params['package_id']) && $params['package_id'] && is_numeric($params['package_id'])) {
      $sql = "SELECT s1.client_id FROM shopify_ssw_subscription s1
LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
WHERE s2.subscription_id IS NULL AND s1.package_id = {$params['package_id']} AND s1.status <> 'pending'";
      $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $client_ids = [];
      foreach ($rows as $row) {
        $client_ids[] = $row['client_id'];
      }
      $params['sswclient_ids'] = $client_ids;
    }

    if (($_status = $this->getParam('status', 0))) {
      $condition = [];
      if ($_status == 'active') {
        $condition[] = "s1.status = 1";
      }
      if ($_status == 'losing') {
        $condition[] = "s1.status = 0";
      }
      if ($_status == 'unavailable') {
        $condition[] = "s1.unavailable = 1";
      }
      if (isset($params['package_id']) && is_numeric($params['package_id'])) {
        $condition[] = "s1.package_id = {$params['package_id']}";
      }


      $condition = implode(' and ', $condition);

      if (!empty($condition)) {
        $sql = "SELECT s1.client_id FROM shopify_ssw_client AS s1 WHERE " . $condition;
        if ($this->session->has('QUERY_SHOPIFY_SSW_CLIENT') && $this->session->has('RESULT_SHOPIFY_SSW_CLIENT') && $sql == $this->session->get('QUERY_SHOPIFY_SSW_CLIENT')) {
          $params['sswclient_ids'] = $this->session->get('RESULT_SHOPIFY_SSW_CLIENT');
        } else {
          $clientIds = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
          $client_ids = [];
          foreach ($clientIds as $client_id) {
            $client_ids[] = $client_id['client_id'];
          }
          $params['sswclient_ids'] = $client_ids;
          $this->session->set('QUERY_SHOPIFY_SSW_CLIENT', $sql);
          $this->session->set('RESULT_SHOPIFY_SSW_CLIENT', $client_ids);
        }
      }
    }

    if ($this->getParam('discount', 0) != 0) {

      $clientDiscounts = SswClientDiscount::find('discount_id =' . $this->getParam('discount', 0));

      foreach ($clientDiscounts as $client_id) {
        $client_ids[] = $client_id->client_id;
      }
      $params['sswclient_ids'] = $client_ids;

    }

    if ($partner_id = $this->getParam('partner', 0)) {
      $sql = 'SELECT client_id FROM shopify_ssw_partnerclient WHERE partner_id = :partner_id';
      $clientIds = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC, ['partner_id' => $partner_id]);
      $client_ids = [];
      foreach ($clientIds as $client_id) {
        $client_ids[] = $client_id['client_id'];
      }
      $params['sswclient_ids'] = $client_ids;
    }

    $params['country'] = $this->getParam('country');
    $params['answer']=$this->getParam('answer');

    $shops = Shops::getShopsPaginator($params);
    $shops = $shops->getPaginate();
    $shopIds = [0];
    $clientIds = [1488];
    $shopTickets = [];
    foreach ($shops->items as $shop) {
      $shopIds[] = $shop->shop_id;
      $clientIds[] = $shop->sswclient_id;
      $shopTickets[$shop->shop_id] = 0;
    }
    // get shop tickets count
    $ticketsData = Ticket::getShopTicketCount($shopIds);
    foreach ($ticketsData as $data) {
      $shopTickets[$data['shop_id']] = $data['tickets'];
      $checkTicket[$data['shop_id']]=Ticket::checkTickets($data['shop_id']);
    }

    $clientIdsStr = implode(',', $clientIds);


    /*$sql = "SELECT main.*, subscription.subscription_id FROM
      (
        SELECT client_id, app, status, package_id FROM shopify_ssw_clientapp WHERE client_id IN ({$clientIdsStr}) ORDER BY status DESC, IF (app = 'default', 0, 1)
      ) AS main
      LEFT JOIN shopify_ssw_subscription AS subscription
      ON (main.client_id = subscription.client_id AND main.app = subscription.app AND subscription.status = 'active' AND char_length(subscription.charge_id) = 7)
      GROUP BY client_id";

    $clientAppList = $db->fetchAll($sql, Phalcon\Db::FETCH_OBJ);
    $clientApps = array();
    foreach ($clientAppList as $app) {
      $clientApps[$app->client_id] = $app;
    }*/

    $sql = "SELECT a.client_id, a.app, IF (s1.package_id IS NULL, IF (a.package_id = 0, IF (a.app = 'default', 7, 19), a.package_id), s1.package_id) AS package_id FROM shopify_ssw_clientapp AS a
LEFT JOIN shopify_ssw_subscription s1
	ON (a.client_id = s1.client_id AND a.app = s1.app)
LEFT JOIN shopify_ssw_subscription s2 
	ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
WHERE a.client_id IN ({$clientIdsStr}) AND s2.subscription_id IS NULL AND (s1.status IS NULL OR s1.status <> 'pending')";



    $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $clientApps = [];
    foreach ($rows as $row) {
      $clientApps[$row['client_id']][] = $row;
    }



    $sql = "SELECT package_id, title, app FROM shopify_ssw_package";
    $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $packages = [];
    foreach ($packageList as $package) {
      $packages[$package['package_id']] = [
        'package_id' => $package['package_id'],
        'title' => $package['title'],
        'app' => $package['app']
      ];
    }


    /*$sql = "SELECT name, title FROM shopify_ssw_app";
    $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $apps = array();
    foreach ($appList as $appInfo) {
      $apps[$appInfo['name']] = $appInfo['title'];
    }*/

    $db = Phalcon\DI::getDefault()->get('ssw_database');
    $sql = "SELECT s1.client_id, s1.status, s1.unavailable FROM shopify_ssw_client AS s1 WHERE s1.client_id IN(" . implode($clientIds, ',') . ")";
    $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $shops_status = [];
    foreach ($rows as $row) {
      $shops_status[$row['client_id']] = $row['unavailable'] ? 'unavailable' : ($row['status'] ? 'active' : 'losing');
    }

    $country = Shops::getCountry();
    $shopsArray = json_decode(json_encode($shops), true);


    $this->view->setVars([
      'shops' => $shops,
      'shops_status' => $shops_status,
      'params' => $params,
      'tickets' => $shopTickets,
      'clientApps' => $clientApps,
      'packages' => $packages,
      'countries' => $country
    ]);
  }

  public function advancedAction()
  {
    $columns = $columns = Featurelabels::getColumns();
    $this->view->setVar("pageTitle", "CRM Sites");
    $params = $this->_getAllParams();
    $shops = Shops::getShopsPaginator($params);
    $shops = $shops->getPaginate();
    $this->view->setVars([
      'shops' => $shops,
      'params' => $params,
      'columns' => $columns
    ]);
  }

  public function themeAction()
  {
    $shop_id = $this->getParam('id', 0);
    $mod_id = $this->getParam('mod_id', false);

    $shop = Shops::findFirst([
        'conditions' => 'shop_id = :shopId:',
        'bind' => [
            'shopId' => $shop_id
        ]
    ]);

    if (!$shop) {
      return $this->response->redirect('/shops', true);
    }

    $key = $this->getParam('asset', 'layout/theme.liquid');
    $allAssets = [];
    $assetValue = '';
    $old_version = '';
    $saved = false;
    $revision = false;
    $message = '';
    $client = SswClients::findFirst('client_id = ' . $shop->sswclient_id);
    $_SESSION['client_id'] = $shop->sswclient_id;
    $_SESSION['db_id'] = $client->db_id;
    $settingTable = new SswSettings();
    $themeId = $this->getParam('theme_id', 0);

    $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    $isImage = false;
    $white_list = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];
    if (in_array($extension, $white_list)) {
      $isImage = true;
    }

    try {
      $this->view->setVar('shop', $shop);

      $api = new \Shopify\Client($shop);
      $themes = $api->call('GET', "/admin/themes.json");
      $theme = false;

      if ($themeId) {
        $themeId = intval($themeId);
        foreach ($themes as $theme_item) {
          if ($theme_item['id'] == $themeId) {
            $theme = $theme_item;
            break;
          }
        }
      }
      if (!$theme) {
        $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
        $_is_found = 0;
        if ($activeThemeID) {
          $activeThemeID = intval($activeThemeID);
          foreach ($themes as $theme_item) {
            if ($theme_item['id'] == $activeThemeID) {
              $theme = $theme_item;
              $_is_found = 1;
              break;
            }
          }
        }
        if (!$_is_found) {
          foreach ($themes as $theme_item) {
            if ($theme_item['role'] == 'main') {
              $theme = $theme_item;
              break;
            }
          }
        }
      }

      $themeId = $theme['id'];
      $theme_supported = $this->checkIntegrationSupport($api, $theme);

      if ($this->request->isPost()) {
        $data = $this->request->getPost();
        $assetSave = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        if ($isImage) {
          print_die($_FILES);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }

        $modify = new Modifications();
        $modify->save([
          'user_id' => Core::getViewerId(),
          'shop_id' => $shop_id,
          'theme_id' => $themeId,
          'file' => $key,
          'value' => $data['value'],
          'old_value' => $assetSave['value'],
          'date' => time()
        ]);
        $saved = true;

        if ($this->getParam("format", 0) === "ajax") {
          $this->view->disable();
          echo json_encode(["status" => "OK"]);
          return;
        }

      }

      if ($mod_id) {
        $revision = Modifications::findFirst($mod_id);
        $old_version = $this->getParam('old', 0);
        $assetValue = ($old_version) ? $revision->old_value : $revision->value;
      } else {
        $revision = false;
        $old_version = false;
        //  print_die(['GET', "/admin/themes/{$themeId}/assets.json",array('id'=> $themeId, 'asset[key]'=> $key)]);
        $asset = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        $assetValue = ($isImage) ? $asset['public_url'] : $asset['value'];
      }

      $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");

    } catch (\Exception $e) {
      $saved = false;
      $message = $e->getMessage();
      $api = new \Shopify\Client($shop);

      if ($this->request->isPost() && $e->getCode() == 404) {
        // create this file
        $this->view->setVar('shop', $shop);
        if (!isset($theme)) {
          $theme = false;
        }
        if (!isset($themes)) {
          $themes = $api->call('GET', "/admin/themes.json");
        }
        if (!$theme) {
          if ($themeId) {
            $themeId = intval($themeId);
            foreach ($themes as $theme_item) {
              if ($theme_item['id'] == $themeId) {
                $theme = $theme_item;
                break;
              }
            }
          }
          if (!$theme) {
            $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
            if ($activeThemeID) {
              $activeThemeID = intval($activeThemeID);
              foreach ($themes as $theme_item) {
                if ($theme_item['id'] == $activeThemeID) {
                  $theme = $theme_item;
                  break;
                }
              }
            } else {
              foreach ($themes as $theme_item) {
                if ($theme_item['role'] == 'main') {
                  $theme = $theme_item;
                  break;
                }
              }
            }
          }
        }
        $themeId = $theme['id'];
        if (!isset($theme_supported)) {
          $theme_supported = $this->checkIntegrationSupport($api, $theme);
        }

        $data = $this->request->getPost();
        if ($isImage) {
          $attachment = base64_encode(file_get_contents($_FILES["asset_file"]["tmp_name"]));
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'attachment' => $attachment]]);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }
        $assetValue = $data['value'];
        $saved = true;
        if ($saved && $this->request->isPost() && $this->getParam("format", 0) === "ajax") {
          echo json_encode(["status" => "OK", 'message' => "NEW FILE CREATED ", "code" => 0]);
          die;
        }
      }
      if (empty($allAssets)) {
        $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");
      }

      if ($this->request->isPost() && $this->getParam("format", 0) === "ajax") {
        echo json_encode(["status" => 0, 'message' => $e->getMessage(), "code" => $e->getCode()]);
        die;
      }
    }


    foreach ($allAssets as $index => $assetItem) {
      if (strpos($assetItem['key'], '.liquid') === false) {
        foreach ($allAssets as $item) {
          if ($assetItem['key'] . '.liquid' == $item['key']) {
            unset($allAssets[$index]);
            break;
          }
        }

      }
    }

    $modifications = Modifications::getHistory($shop_id, $key);
    $count = Modifications::getAllHistoryCount($shop_id, $key);
    $this->view->setVar('modifications', $modifications);
    $this->view->setVar('value', $assetValue);
    $this->view->setVar('old_version', $old_version);
    $this->view->setVar('key', $key);
    $this->view->setVar('saved', $saved);
    $this->view->setVar('allAssets', $allAssets);
    $this->view->setVar('revision', $revision);
    $this->view->setVar('message', $message);
    $this->view->setVar('themeId', isset($themeId) ? $themeId : 0);
    $this->view->setVar('theme_supported', isset($theme_supported) ? $theme_supported : 0);
    $this->view->setVar('themes', isset($themes) ? $themes : []);
    $this->view->setVar('isImage', $isImage);
    $this->view->setVar('all_count_history', $count);
  }

  public function getremoteAction()
  {
    $shop_id = (int)$this->getParam('id');
    $themeId = $this->getParam('themeid', 0);
    $key = $this->getParam('key', 0);
    $shop = Shops::findFirst('shop_id=' . $shop_id);
    try {
      $api = new \Shopify\Client($shop);
      $asset = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
      $assetValue = $asset['value'];
      exit($assetValue);
    } catch (Exception $e) {
      exit($e->getCode() . ' ' . $e->getMessage());
    }
  }

  public function uploadfileAction()
  {
    $message = "Successfully created !";
    $error = 0;
    $asetName = '';
    try {
      $shop_id = (int)$this->getParam('id');
      $themeId = $this->getParam('theme_id', 0);
      $folder = $this->getParam('asset', '0');
      $file_ext = $this->getParam('file_ext', '0');
      $name = $this->getParam('name', 0);
      if (!$name || !$file_ext || !$folder) {
        die(json_encode([
          "message" => "Please fill in all fields !",
          "status" => false
        ]));
      }
      if (!pathinfo($name, PATHINFO_EXTENSION)) {
        if (!in_array($file_ext, ['image', 'file', 'other'])) {
          $name = $name . $file_ext;
        } else {
          die(json_encode([
            "message" => 'Unknown file type !',
            "status" => false
          ]));
        }
      }
      $shop = Shops::findFirst('shop_id=' . $shop_id);
      $api = new \Shopify\Client($shop);
      $isFile = false;
      if (in_array($file_ext, ['image', 'file', 'other'])) {
        $isFile = true;
      }
      if ($isFile) {
        $attachment = base64_encode(file_get_contents($_FILES["asset_file"]["tmp_name"]));
        $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $folder . '/' . $name, 'attachment' => $attachment]]);
      } else {
        $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $folder . '/' . $name, 'value' => ""]]);
      }
      $asetName = $folder . '/' . $name;
    } catch (\Exception $e) {
      die(json_encode([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "status" => false
      ]));
    }
    die(json_encode([
      "message" => $message,
      "status" => !$error,
      "asset" => $asetName
    ]));
  }

  public function deletefileAction()
  {
    $message = "Successfully deleted !";
    try {
      $shop_id = (int)$this->getParam('id');
      $themeId = $this->getParam('themeid', 0);
      $key = $this->getParam('key', '0');
      $shop = Shops::findFirst('shop_id=' . $shop_id);
      $api = new \Shopify\Client($shop);
      $api->call('DELETE', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key]]);
    } catch (\Exception $e) {
      die(json_encode([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "status" => false
      ]));
    }
    die(json_encode([
      "message" => $message,
      "status" => true
    ]));
  }

  public function instasectionAction()
  {
    $message = "Successfully !";
    try {
      $shop_id = (int)$this->getParam('id');
      $themeId = $this->getParam('themeid', 0);
      $gallery_id = (int)$this->getParam('gallery_id', 1);
      $widget_id = (int)$this->getParam('widget_id', 0);
      $order = (int)$this->getParam('order', null);
      $gallery_type = $this->getParam('gallery_type', 'grid');
      $key = 'config/settings_data.json';
      $shop = Shops::findFirst('shop_id=' . $shop_id);
      $api = new \Shopify\Client($shop);
      $res = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key]]);
      $value = json_decode($res['value']);
      $json_value = null;

      if (isset($value->current) && isset($value->current->content_for_index)) {
        if (in_array('sswinstagram', $value->current->content_for_index)) {
          $index = array_search('sswinstagram', $value->current->content_for_index);
          if ($index !== false) {
            array_splice($value->current->content_for_index, $index, 1);
            //unset($value['current']['content_for_index'][$key]);
          }
        }
        if ($order > -1) {
          $original = $value->current->content_for_index;
          $inserted = ['sswinstagram'];
          array_splice($original, $order, 0, $inserted);
          $value->current->content_for_index = $original;
        } else {
          $value->current->content_for_index[] = 'sswinstagram';
        }
        if (isset($value->current->sections)) {
          $value->current->sections->sswinstagram = [
            "type" => "sswinstagram",
            "settings" => [
              "option_galleries_id" => "data-gallery_id=\"{$gallery_id}\" data-widget=\"{$widget_id}\""
            ]
          ];
        }
        $json_value = json_encode($value, JSON_PRETTY_PRINT);
      }
      if ($json_value) {
        $modify = new Modifications();
        $modify->save([
          'user_id' => Core::getViewerId(),
          'shop_id' => $shop_id,
          'theme_id' => $themeId,
          'file' => $key,
          'value' => $json_value,
          'old_value' => $res['value'],
          'date' => time()
        ]);

        $api->call('PUT', "/admin/themes/{$themeId}/assets.json", [
          'asset' => [
            'key' => $key,
            'value' => $json_value
          ]
        ]);

      }


    } catch (\Exception $e) {
      die(json_encode([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "status" => false
      ]));
    }
    die(json_encode([
      "message" => $message,
      "status" => true
    ]));
  }

  public function getIgContentForIndexAction()
  {
    try {
      $shop_id = (int)$this->getParam('id');
      $themeId = $this->getParam('themeid', 0);
      $key = 'config/settings_data.json';
      $shop = Shops::findFirst('shop_id=' . $shop_id);
      $api = new \Shopify\Client($shop);
      $res = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key]]);
      $value = json_decode($res['value']);
      $res = [];

      if (isset($value->current) && isset($value->current->content_for_index)) {
        $res = $value->current->content_for_index;
      }
      die(json_encode([
        "sections" => $res,
        "status" => true
      ]));

    } catch (\Exception $e) {
      die(json_encode([
        "message" => $e->getMessage(),
        "code" => $e->getCode(),
        "status" => false
      ]));
    }
  }

  public function loadassetAction()
  {
    $shop_id = $this->getParam('id');
    $themeId = $this->getParam('theme_id');
    $key = $this->getParam('key');
    $shop = Shops::findFirst('shop_id=' . $shop_id);
    $output_array = false;
    preg_match("/^page\-[0-9]+$/", $key, $output_array);
    if (!empty($output_array)) {
      try {
        $page_id = trim(explode('-', $output_array[0])[1]);
        $api = new \Shopify\Client($shop);
        $assetValue = $api->call('GET', "/admin/pages/{$page_id}.json");
      } catch (\Exception $e) {
        print_die($e->getMessage());
      }
      exit($assetValue['body_html']);
    }

    try {
      $api = new \Shopify\Client($shop);
      $assetValue = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
    } catch (\Exception $e) {
      $assetValue = "ERROR LOADING: " . $e->getMessage();
    }
    $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    $isImage = false;
    $white_list = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];
    if (in_array($extension, $white_list)) {
      $isImage = true;
    }
    if ($isImage) {
      exit($assetValue['public_url']);
    } else {
      $assetValueContent = isset($assetValue['value']) ? $assetValue['value'] : 'Error! Please reload this asset!';
      exit($assetValueContent);
    }
  }

  public function testkalysAction()
  {
    $this->view->setLayout("editor");
    $shop_id = $this->getParam('id');
    $mod_id = $this->getParam('mod_id', false);

    $shop = Shops::findFirst('shop_id=' . $shop_id);

    if (!$shop) {
      return $this->response->redirect('/shops', true);
    }

    $key = $this->getParam('asset', 'layout/theme.liquid');
    $allAssets = [];
    $assetValue = '';
    $old_version = '';
    $saved = false;
    $revision = false;
    $message = '';
    $client = SswClients::findFirst('client_id = ' . $shop->sswclient_id);
    $_SESSION['client_id'] = $shop->sswclient_id;
    $_SESSION['db_id'] = $client->db_id;
    $settingTable = new SswSettings();
    $themeId = $this->getParam('theme_id', 0);

    $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    $isImage = false;
    $white_list = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];
    if (in_array($extension, $white_list)) {
      $isImage = true;
    }


    $theme = false;

    try {
      $this->view->setVar('shop', $shop);

      $api = new \Shopify\Client($shop);
      $themes = $api->call('GET', "/admin/themes.json");
      if (!$theme && $themeId) {
        $themeId = intval($themeId);
        foreach ($themes as $theme_item) {
          if ($theme_item['id'] == $themeId) {
            $theme = $theme_item;
            break;
          }
        }
      }
      if (!$theme) {
        $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
        $_is_found = 0;
        if ($activeThemeID) {
          $activeThemeID = intval($activeThemeID);
          foreach ($themes as $theme_item) {
            if ($theme_item['id'] == $activeThemeID) {
              $theme = $theme_item;
              $_is_found = 1;
              break;
            }
          }
        }
        if (!$_is_found) {
          foreach ($themes as $theme_item) {
            if ($theme_item['role'] == 'main') {
              $theme = $theme_item;
              break;
            }
          }
        }
      }

      $themeId = $theme['id'];


      if ($this->request->isPost()) {

        $data = $this->request->getPost();
        $output_array = false;
        preg_match("/^page\-[0-9]+$/", $key, $output_array);
        if (count($output_array)) {
          try {
            $page_id = trim(explode('-', $output_array[0])[1]);
            $api = new \Shopify\Client($shop);
            $assetValue = $api->call('PUT', "/admin/pages/{$page_id}.json", [
              'page' => ['body_html' => $data['value']]
            ]);
          } catch (\Exception $e) {
            echo json_encode(["status" => 0, 'message' => $e->getMessage(), "code" => $e->getCode()]);
            die;
          }
          echo json_encode(["status" => "OK"]);
          die;
        }


        $assetSave = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        $oldValue = Modifications::getOldValue($shop_id, $key);
        $isSame = CompareHtml::getInstance()->isSame($assetSave['value'], $oldValue ? $oldValue : "");
        if ($isImage) {
          print_die($_FILES);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }

        $modify = new Modifications();
        $modify->save([
          'user_id' => Core::getViewerId(),
          'shop_id' => $shop_id,
          'theme_id' => $themeId,
          'file' => $key,
          'value' => $data['value'],
          'old_value' => $assetSave['value'],
          'date' => time()
        ]);
        $saved = true;

        if ($this->getParam("format", 0) === "ajax") {
          $this->view->disable();
          echo json_encode([
            "status" => "OK",
            "isSame" => $isSame,
          ]);
          return;
        }

      }

      if ($mod_id) {
        $revision = Modifications::findFirst($mod_id);
        $old_version = $this->getParam('old', 0);
        $assetValue = ($old_version) ? $revision->old_value : $revision->value;
      } else {
        $revision = false;
        $old_version = false;
        //  print_die(['GET', "/admin/themes/{$themeId}/assets.json",array('id'=> $themeId, 'asset[key]'=> $key)]);
        $asset = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        $assetValue = ($isImage) ? $asset['public_url'] : $asset['value'];
      }

      $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");

    } catch (\Exception $e) {
      $saved = false;
      $message = $e->getMessage();
      $api = new \Shopify\Client($shop);
      if ($this->request->isPost() && $this->getParam("format", 0) === "ajax") {
        echo json_encode(["status" => 0, 'message' => $e->getMessage(), "code" => $e->getCode()]);
        die;
      }
      if ($this->request->isPost() && $e->getCode() == 404) {
        // create this file
        $this->view->setVar('shop', $shop);
        if (!isset($theme)) {
          $theme = false;
        }
        if (!isset($themes)) {
          $themes = $api->call('GET', "/admin/themes.json");
        }
        if (!$theme) {
          if ($themeId) {
            $themeId = intval($themeId);
            foreach ($themes as $theme_item) {
              if ($theme_item['id'] == $themeId) {
                $theme = $theme_item;
                break;
              }
            }
          }
          if (!$theme) {
            $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
            if ($activeThemeID) {
              $activeThemeID = intval($activeThemeID);
              foreach ($themes as $theme_item) {
                if ($theme_item['id'] == $activeThemeID) {
                  $theme = $theme_item;
                  break;
                }
              }
            } else {
              foreach ($themes as $theme_item) {
                if ($theme_item['role'] == 'main') {
                  $theme = $theme_item;
                  break;
                }
              }
            }
          }
        }
        $themeId = $theme['id'];
        if (!isset($theme_supported)) {
          $theme_supported = $this->checkIntegrationSupport($api, $theme);
        }

        $data = $this->request->getPost();
        if ($isImage) {
          $attachment = base64_encode(file_get_contents($_FILES["asset_file"]["tmp_name"]));
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'attachment' => $attachment]]);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }
        $assetValue = $data['value'];
        $saved = true;
      }
      if (empty($allAssets)) {
        $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");
      }
    }

    foreach ($allAssets as $index => $assetItem) {
      if (strpos($assetItem['key'], '.liquid') === false) {
        foreach ($allAssets as $item) {
          if ($assetItem['key'] . '.liquid' == $item['key']) {
            unset($allAssets[$index]);
            break;
          }
        }

      }
    }

    $assets_with_folders = [];

    foreach ($allAssets as $index => $assetItem) {
      $_asset = explode("/", $assetItem["key"]);
      $folder = $_asset[0];
      $tx = isset($_asset[2]) ? "/" . $_asset[2] : "";
      $file = $_asset[1] . $tx;
      $assets_with_folders[$folder][] = $file;
    }

    $allPages = [];
    try {
      $allPages = $api->call('GET', "/admin/pages.json", ['fields' => 'id,handle,title']);
    } catch (\Exception $e) {
    }

    $modifications = Modifications::getHistory($shop_id, $key);
    $count = Modifications::getAllHistoryCount($shop_id, $key);
    $this->view->setVar('modifications', $modifications);
    $this->view->setVar('value', $assetValue);
    $this->view->setVar('old_version', $old_version);
    $this->view->setVar('key', $key);
    $this->view->setVar('saved', $saved);
    $this->view->setVar('allAssets', $allAssets);
    $this->view->setVar('allPages', $allPages);
    $this->view->setVar('allAssetsWithFolders', $assets_with_folders);
    $this->view->setVar('revision', $revision);
    $this->view->setVar('message', $message);
    $this->view->setVar('themeId', isset($themeId) ? $themeId : 0);
    $this->view->setVar('themes', isset($themes) ? $themes : []);
    $this->view->setVar('isImage', $isImage);
    $this->view->setVar('all_count_history', $count);
  }

  public function testkalys2Action()
  {
    $this->view->setLayout("editor");
    $shop_id = $this->getParam('id');
    $mod_id = $this->getParam('mod_id', false);

    $shop = Shops::findFirst('shop_id=' . $shop_id);

    if (!$shop) {
      return $this->response->redirect('/shops', true);
    }

    $key = $this->getParam('asset', 'layout/theme.liquid');
    $allAssets = [];
    $assetValue = '';
    $old_version = '';
    $saved = false;
    $revision = false;
    $message = '';
    $client = SswClients::findFirst('client_id = ' . $shop->sswclient_id);
    $_SESSION['client_id'] = $shop->sswclient_id;
    $_SESSION['db_id'] = $client->db_id;
    $settingTable = new SswSettings();
    $themeId = $this->getParam('theme_id', 0);

    $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
    $isImage = false;
    $white_list = ['jpg', 'jpeg', 'gif', 'png', 'bmp'];
    if (in_array($extension, $white_list)) {
      $isImage = true;
    }

    try {
      $this->view->setVar('shop', $shop);

      $api = new \Shopify\Client($shop);
      $themes = $api->call('GET', "/admin/themes.json");
      $theme = false;

      if ($themeId) {
        $themeId = intval($themeId);
        foreach ($themes as $theme_item) {
          if ($theme_item['id'] == $themeId) {
            $theme = $theme_item;
            break;
          }
        }
      }
      if (!$theme) {
        $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
        $_is_found = 0;
        if ($activeThemeID) {
          $activeThemeID = intval($activeThemeID);
          foreach ($themes as $theme_item) {
            if ($theme_item['id'] == $activeThemeID) {
              $theme = $theme_item;
              $_is_found = 1;
              break;
            }
          }
        }
        if (!$_is_found) {
          foreach ($themes as $theme_item) {
            if ($theme_item['role'] == 'main') {
              $theme = $theme_item;
              break;
            }
          }
        }
      }

      $themeId = $theme['id'];


      if ($this->request->isPost()) {

        $data = $this->request->getPost();
        $output_array = false;
        preg_match("/^page\-[0-9]+$/", $key, $output_array);
        if (count($output_array)) {
          try {
            $page_id = trim(explode('-', $output_array[0])[1]);
            $api = new \Shopify\Client($shop);
            $assetValue = $api->call('PUT', "/admin/pages/{$page_id}.json", [
              'page' => ['body_html' => $data['value']]
            ]);
          } catch (\Exception $e) {
            echo json_encode(["status" => 0, 'message' => $e->getMessage(), "code" => $e->getCode()]);
            die;
          }
          echo json_encode(["status" => "OK"]);
          die;
        }


        $assetSave = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        if ($isImage) {
          print_die($_FILES);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }

        $modify = new Modifications();
        $modify->save([
          'user_id' => Core::getViewerId(),
          'shop_id' => $shop_id,
          'theme_id' => $themeId,
          'file' => $key,
          'value' => $data['value'],
          'old_value' => $assetSave['value'],
          'date' => time()
        ]);
        $saved = true;

        if ($this->getParam("format", 0) === "ajax") {
          $this->view->disable();
          echo json_encode(["status" => "OK"]);
          return;
        }

      }

      if ($mod_id) {
        $revision = Modifications::findFirst($mod_id);
        $old_version = $this->getParam('old', 0);
        $assetValue = ($old_version) ? $revision->old_value : $revision->value;
      } else {
        $revision = false;
        $old_version = false;
        //  print_die(['GET', "/admin/themes/{$themeId}/assets.json",array('id'=> $themeId, 'asset[key]'=> $key)]);
        $asset = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => $key]);
        $assetValue = ($isImage) ? $asset['public_url'] : $asset['value'];
      }

      $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");

    } catch (\Exception $e) {
      $saved = false;
      $message = $e->getMessage();
      $api = new \Shopify\Client($shop);
      if ($this->request->isPost() && $this->getParam("format", 0) === "ajax") {
        echo json_encode(["status" => 0, 'message' => $e->getMessage(), "code" => $e->getCode()]);
        die;
      }
      if ($this->request->isPost() && $e->getCode() == 404) {
        // create this file
        $this->view->setVar('shop', $shop);
        if (!isset($theme)) {
          $theme = false;
        }
        if (!isset($themes)) {
          $themes = $api->call('GET', "/admin/themes.json");
        }
        if (!$theme) {
          if ($themeId) {
            $themeId = intval($themeId);
            foreach ($themes as $theme_item) {
              if ($theme_item['id'] == $themeId) {
                $theme = $theme_item;
                break;
              }
            }
          }
          if (!$theme) {
            $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
            if ($activeThemeID) {
              $activeThemeID = intval($activeThemeID);
              foreach ($themes as $theme_item) {
                if ($theme_item['id'] == $activeThemeID) {
                  $theme = $theme_item;
                  break;
                }
              }
            } else {
              foreach ($themes as $theme_item) {
                if ($theme_item['role'] == 'main') {
                  $theme = $theme_item;
                  break;
                }
              }
            }
          }
        }
        $themeId = $theme['id'];
        if (!isset($theme_supported)) {
          $theme_supported = $this->checkIntegrationSupport($api, $theme);
        }

        $data = $this->request->getPost();
        if ($isImage) {
          $attachment = base64_encode(file_get_contents($_FILES["asset_file"]["tmp_name"]));
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'attachment' => $attachment]]);
        } else {
          $api->call('PUT', "/admin/themes/{$themeId}/assets.json", ['asset' => ['key' => $key, 'value' => $data['value']]]);
        }
        $assetValue = $data['value'];
        $saved = true;
      }
      if (empty($allAssets)) {
        $allAssets = $api->call('GET', "/admin/themes/{$themeId}/assets.json");
      }
    }

    foreach ($allAssets as $index => $assetItem) {
      if (strpos($assetItem['key'], '.liquid') === false) {
        foreach ($allAssets as $item) {
          if ($assetItem['key'] . '.liquid' == $item['key']) {
            unset($allAssets[$index]);
            break;
          }
        }

      }
    }

    $assets_with_folders = [];

    foreach ($allAssets as $index => $assetItem) {
      $_asset = explode("/", $assetItem["key"]);
      $folder = $_asset[0];
      $tx = isset($_asset[2]) ? "/" . $_asset[2] : "";
      $file = $_asset[1] . $tx;
      $assets_with_folders[$folder][] = $file;
    }

    $allPages = [];
    try {
      $allPages = $api->call('GET', "/admin/pages.json", ['fields' => 'id,handle,title']);
    } catch (\Exception $e) {
    }

    $modifications = Modifications::getHistory($shop_id, $key);
    $count = Modifications::getAllHistoryCount($shop_id, $key);
    $this->view->setVar('modifications', $modifications);
    $this->view->setVar('value', $assetValue);
    $this->view->setVar('old_version', $old_version);
    $this->view->setVar('key', $key);
    $this->view->setVar('saved', $saved);
    $this->view->setVar('allAssets', $allAssets);
    $this->view->setVar('allPages', $allPages);
    $this->view->setVar('allAssetsWithFolders', $assets_with_folders);
    $this->view->setVar('revision', $revision);
    $this->view->setVar('message', $message);
    $this->view->setVar('themeId', isset($themeId) ? $themeId : 0);
    $this->view->setVar('themes', isset($themes) ? $themes : []);
    $this->view->setVar('isImage', $isImage);
    $this->view->setVar('all_count_history', $count);
  }

  public function getallhistoryAction()
  {
    $shop_id = (int)$this->getParam('shop_id', 0);
    $key = $this->getParam('key', 0);
    $themeid = $this->getParam('themeid', 0);
    $all = $this->getParam('all', 0);

    if ($shop_id && $key) {
      if ($all) {
        $modifications = Modifications::getAllHistory($shop_id, $key);
      } else {
        $modifications = Modifications::getHistory($shop_id, $key);
      }
      $count = Modifications::getAllHistoryCount($shop_id, $key);
      $this->view->setVar('modifications', $modifications);
      $this->view->setVar('themeId', $themeid);
      $this->view->setVar('shop_id', $shop_id);
      $this->view->setVar('key', $key);
      $this->view->setVar('all_count', $count);
    }
    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
  }

  public function gethistoryAction()
  {
    $shop_id = (int)$this->getParam('id', 0);
    $key = $this->getParam('key', 0);
    $themeid = $this->getParam('themeid', 0);
    $all = $this->getParam('all', 1);

    if ($shop_id && $key) {
      if ($all) {
        $modifications = Modifications::getAllHistory($shop_id, $key);

      } else {
        $modifications = Modifications::getHistory($shop_id, $key);
      }
      $group_modifications = [];
      $colors = ['#0eea90', '#133390', '#096bd0', '#3794ce', '#6f9bc9', '#4ee2ae',
        '#43c524', '#7076ed', '#fd6ea3', '#c818bc ', '#f55508', '#f3f365', '#9efd97', '#f76d65', '#cb4be9', '#ed2e2c'];
      $theme_colors = [];
      $i = 0;
      foreach ($modifications as $mods) {
        $th_id = $mods->theme_id ? $mods->theme_id : 0;
        $theme_colors[$th_id] = $colors[$i++];
        if ($i >= count($colors)) {
          $i = 0;
        }
        $group_modifications[$th_id][] = $mods;
      }
      $count = Modifications::getAllHistoryCount($shop_id, $key);
      $this->view->setVar('modifications', $modifications);
      $this->view->setVar('group_modifications', $group_modifications);
      $this->view->setVar('colors', $theme_colors);
      $this->view->setVar('themeId', $themeid);
      $this->view->setVar('shop_id', $shop_id);
      $this->view->setVar('key', $key);
      $this->view->setVar('all_count', $count);
    }
    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
  }

  public function modificationAction()
  {
    $mod_id = $this->getParam('mod_id', false);
    $assetValue = "";
    if ($mod_id) {
      $revision = Modifications::findFirst($mod_id);
      $old_version = $this->getParam('old', 0);
      $assetValue = ($old_version) ? $revision->old_value : $revision->value;
    }
    $this->view->setVar('value', $assetValue);

    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);

  }

  public function profileAction()
  {
    $shop_id = $this->getParam('id');
    $shop = Shops::findFirst('shop_id=' . $shop_id);

    if (!$shop) {
      return $this->response->redirect('/shops', true);
    }
    $this->view->setVar("pageTitle", "CRM Site - " . $shop->domain);
    $staffMembers = Shopstaffs::getShopStaffs($shop_id);
    $staffIds = [];
    $invoices = [];
    if ($staffMembers->count() > 0) {
      foreach ($staffMembers as $member) {
        $staffIds[] = $member->client_id;
      }

      $invoices = Invoices::getByClientIds($staffIds);
    }
    if (empty($staffIds))
      $staffIds = [0];
    $tickets = Ticket::getTicketsPaginator(['ipp' => 15, 'page' => $this->getParam('page', 1), 'staff_ids' => $staffIds]);

    $this->view->setVar('shop', $shop);
    $this->view->setVar('tickets', $tickets);
    $this->view->setVar('staffMembers', $staffMembers);
    $this->view->setVar('invoices', $invoices);
// --------------------------------------------------------------------
    $columns = Featurelabels::getColumns();
    $features = Featurelabels::findFirst('shop_id = ' . $shop->sswclient_id);
    $treated = true;
    if (!$features) {
      $treated = false;
    }
    foreach ($columns as $i => $column) {
      $column_name = isset($column['name']) ? $column['name'] : '';
      if ($column_name && $features) {
        if (property_exists($features, $column_name)) {
          $columns[$i]['value'] = $features->{$column_name};
        } else {
          $columns[$i]['value'] = 0;
        }
      } else {
        $columns[$i]['value'] = 0;
      }
    }

    //@todo check this code
    $client = SswClients::findFirst($shop->sswclient_id);
    if (!$client) {
      print_die('Shop not found!');
    }
    $apps = SswClientApp::find(["client_id = {$shop->sswclient_id}", 'order' => "status DESC, IF (app = 'default', 0, 1)"]);
//    $lastPlans = array();


    $package_ids = [0];
    $application_names = [0];
    foreach ($apps as $app) {
      $package_ids[] = $app->package_id;
      $application_names[] = $app->app;

      //  last plan
      //if(isset($_REQUEST['kir'])) {
      /*$subs = SswSubscriptions::findFirst(array('client_id='.$shop_id, 'order' => 'charge_id DESC', 'limit' => 1));
      if($subs) {
        $package = SswPackages::findFirst(array('package_id='.$subs->package_id));
        $lastPlans[$app->clientapp_id] = $package->title;
      }*/
      //}
      //  last plan
    }

    $package_ids_str = implode(',', $package_ids);
    $applications_str = "'" . implode("','", $application_names) . "'";

    /** @var  $db \Phalcon\Db\Adapter\Pdo\Mysql */
    $db = $this->di->get('ssw_database');

    $lastPlans = [];
    $sql = "SELECT s1.*, p.title FROM shopify_ssw_subscription s1
LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
INNER JOIN shopify_ssw_package p ON s1.package_id = p.package_id
WHERE s1.status = 'active' AND s1.client_id = {$shop->sswclient_id}"; // s2.subscription_id IS NULL AND
    $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    foreach ($rows as $row) {
      $lastPlans[$row['app']] = $row;
    }


    $app = $apps[0];
    $isFreeGrowingBusiness = false;
    if (!empty($lastPlans[$app->app])) {
      $isFreeGrowingBusiness = $lastPlans[$app->app]['package_id'] == 3 &&
        $lastPlans[$app->app]['charge_id'] == 1111111 &&
        $lastPlans[$app->app]['status'] === 'active';
      if ($isFreeGrowingBusiness) {
        $diffInSeconds = strtotime($lastPlans[$app->app]['expiration_date']) - time(); // 86400 seconds === 1 day
        $diffInDays = $diffInSeconds > 86400 ? round($diffInSeconds / 86400) . ' days trial left' : 'last day';
        $lastPlans[$app->app]['title'] = 'Free Growing Business (' . $diffInDays . ')';
      }
    }


    $sql = "SELECT app FROM shopify_ssw_subscription WHERE client_id = {$shop->sswclient_id} AND status = 'active'  AND char_length(charge_id) >= 7";
    $subscriptions = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $charged = [];
    foreach ($subscriptions as $subscription) {
      $charged[$subscription['app']] = 1;
    }

    $sql = "SELECT package_id, title FROM shopify_ssw_package WHERE package_id IN ($package_ids_str)";
    $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $packages = ['Free'];
    foreach ($packageList as $package) {
      $packages[$package['package_id']] = $package['title'];
    }

    $sql = "SELECT name, title FROM shopify_ssw_app WHERE name IN ($applications_str)";
    $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
    $applications = [];
    foreach ($appList as $appInfo) {
      $applications[$appInfo['name']] = $appInfo['title'];
    }

    $sql = "SELECT charge_id, client_id FROM crm_epic_testmode WHERE client_id = {$shop->sswclient_id}";
    $row = $db->fetchOne($sql, Phalcon\Db::FETCH_ASSOC);
    $testMode = $row ? true : false;

    $payments = Payment::getClientPayments($shop->sswclient_id, 'payment_id DESC');
    $paymentTotal = Payment::getClientTotal($shop->sswclient_id);

    $shop_status = $client->unavailable ? "unavailable" : ($client->status ? "active" : "losing");

    // Startup package check
    $startup_edition = false;

    if ($client->ssw && $client->package_id == 3) {
      $subscription = SswSubscriptions::findFirst("status='active' AND client_id={$client->client_id} AND app = 'default' AND charge_id ORDER BY subscription_id DESC");
      if ($subscription) {
        if (SswRequests::findFirst([
          'website' => $client->shop,
          'status' => 'accepted',
          'package_id' => $client->package_id
        ])) {
          $api = new \Shopify\Client($shop);
          try {
            $charge = $api->call('GET', "/admin/recurring_application_charges/{$subscription->charge_id}.json");
            if ($charge['price'] === '49.50') {
              $startup_edition = true;
            }
          } catch (\Exception $e) {
          }
        }
      }
    }

    $plan_colors = [
      0 => 'gray',
      7 => 'gray',
      1 => 'gray',
      4 => '#663399'
    ];
    $this->view->setVars([
      'isFreeGB' => $isFreeGrowingBusiness,
      'client' => $client,
      'shop_status' => $shop_status,
      'apps' => $apps,
      'lastPlans' => $lastPlans,
      'plan_colors' => $plan_colors,
      'is_startup' => $startup_edition,
      'charged' => $charged,
      'packages' => $packages,
      'applications' => $applications,
      'columns' => $columns,
      'treated' => $treated,
      'testMode' => $testMode,
      'payments' => $payments,
      'paymentTotal' => $paymentTotal,
    ]);

  }

  public function editAction()
  {
    $params = $this->dispatcher->getParams();
    if (!isset($params[0]) || !$params[0]) {
      return $this->response->redirect('/', true);
    }

    $shop_id = $params[0];

    $shop = Shops::findFirst('shop_id=' . $shop_id);

    if (!$shop) {
      return $this->response->redirect('/shops', true);
    }
    $this->view->setVar('shop', $shop);

    if (!$this->request->isPost()) {
      return;
    }

    $data = $this->request->getPost();

    foreach ($data as $key => $value)
      $shop->$key = $value;
    $shop->save();
    return $this->response->redirect('/shop/' . $shop_id, true);
  }

  public function editDescAction()
  {
    if ($this->request->isPost()) {
      $shopId = $this->request->getPost('shop_id', null, 0);
      $shop = Shops::findFirst('shop_id = ' . $shopId);
      if ($shop && $this->request->getPost('desc', null, false)) {
        $shop->note = $this->request->getPost('desc');
        $shop->save();
        if ($this->request->getPost('render', null, false)) {
          exit(CrmTags::nl2br($shop->note));
        }
        exit(json_encode(true));
      }
    }
  }

  public function stagingAction()
  {
    $shops = StagingSswClients::find([
      "conditions" => "status = 1 AND shop <> 'https://ssw-themes.myshopify.com'"
    ]);
    $this->view->setVar('shops', $shops);
  }

  public function checkStatusAction()
  {
    if ($shop_id = $this->getParam('shop_id', false)) {
      $shop = Shops::findFirst('shop_id = ' . $shop_id);
      if ($shop) {
        $sswClient = SswClients::findFirst('client_id = ' . $shop->sswclient_id);
        if ($sswClient) {
          $beforeSswClient = $sswClient->toArray();
          $shop->domain = $sswClient->domain;
          $clientApps = SswClientApp::find('client_id = ' . $shop->sswclient_id);
          $sswClientStatus = 0;
          $unAvailable = 0;
          $api = false;
          foreach ($clientApps as $clientApp) {
            $app = SswApp::findFirst("name = '" . $clientApp->app . "'");
            $api = new \Shopify\Client($shop, [
              'api_key' => $app->key,
              'secret' => $app->secret,
              'token' => $clientApp->token,
              'shop' => $shop->url
            ]);
            try {
              $testCall = $api->call('GET', '/admin/shop.json');
              $clientApp->status = 1;
              $sswClientStatus = 1;
              $trackStatusList = SswClientStatus::find("client_id = {$clientApp->client_id} AND (app = '{$clientApp->app}' OR type = 'unavailable')");
              foreach ($trackStatusList as $trackStatus) {
                $trackStatus->delete();
              }
            } catch (\Exception $e) {
              if ($e->getCode() == 401) {
                // Unauthorized
                $clientApp->status = 0;
              } else if ($e->getCode() == 404 || $e->getCode() == 403 || $e->getCode() == 402) {
                // Not Found or Forbidden or Payment Required
                $unAvailable = 1;
              }
            }
            $clientApp->save();
          }
          $clientDefaultApp = SswClientApp::findFirst("client_id = " . $shop->sswclient_id . " AND app = 'default'");
          $ssw = 0;
          if ($clientDefaultApp && $clientDefaultApp->status && $clientDefaultApp->package_id) {
            $ssw = 1;
          }
          $sswClient->status = $sswClientStatus;
          $sswClient->ssw = $ssw;
          $sswClientTokenApp = SswClientApp::findFirst("client_id = " . $shop->sswclient_id . " AND ((status = 1 AND app = 'default') OR status = 1 OR (status = 0 AND app = 'default') OR status = 0)");
          if ($sswClientTokenApp && $sswClientTokenApp->token) {
            $sswClient->token = $sswClientTokenApp->token;
          }
          if (!$sswClientStatus) {
            $sswClient->ssw = 0;
            $sswClient->package_id = 0;
          }
          if ($sswClientStatus) {
            $shopStatus = 'installed';
            if ($api) {
              try {
                $themes = $api->call('GET', "/admin/themes.json");
                $theme = false;
                $_SESSION['client_id'] = $shop->sswclient_id;
                $_SESSION['db_id'] = $sswClient->db_id;
                $settingTable = new SswSettings();
                $activeThemeID = $settingTable->getSetting('shop_active_theme', 0);
                unset($_SESSION['client_id']);
                unset($_SESSION['db_id']);
                if ($activeThemeID) {
                  $activeThemeID = intval($activeThemeID);
                  foreach ($themes as $theme_item) {
                    if ($theme_item['id'] == $activeThemeID) {
                      $theme = $theme_item;
                      break;
                    }
                  }
                } else {
                  foreach ($themes as $theme_item) {
                    if ($theme_item['role'] == 'main') {
                      $theme = $theme_item;
                      break;
                    }
                  }
                }
                if ($theme) {
                  $themeId = $theme['id'];
                  $ssw_helper = $api->call('GET', "/admin/themes/{$themeId}/assets.json", ['id' => $themeId, 'asset[key]' => 'layout/theme.liquid']);
                  if (strpos($ssw_helper['value'], "{% include 'socialshopwave-helper' %}") !== false) {
                    $shopStatus = 'active';
                  }
                }
              } catch (\Exception $e) {

              }
            }
            $shop->status = $shopStatus;
            if ($sswClient->unavailable) {
              $sswClient->unavailable = 0;
              $sswClient->unavailable_date = date('Y-m-d: H-i-s', PHP_INT_MAX);
              mail('burya1988@gmail.com, ermechkin@gmail.com, ulanproger@gmail.com', 'Unavailable shop now is available', 'Shop ' . $sswClient->shop . ' now is available');
            }
          } else {
            if ($unAvailable) {
              if (!$sswClient->unavailable) {
                $sswClient->unavailable = 1;
                $sswClient->unavailable_date = date('Y-m-d: H-i-s');
              }
              $shop->status = 'unavailable';
            } else {
              if ($shop->status != 'lost') {
                $shop->status = 'losing';
              }
            }
          }
          if ($beforeSswClient != $sswClient->toArray()) {
            $sswClient->save();
          }
        }
        $shop->last_update = time();
        $shop->save();
        return $this->response->redirect('/shop/' . $shop_id, true);
      } else {
        return $this->response->redirect('/shops', true);
      }
    }
  }

  public function installAction()
  {
    if ($this->getParam('client_id', false)) {
      $clientId = $this->getParam('client_id', 0);

      $client = SswClients::findFirst('client_id = ' . $clientId);
      if ($client) {
        $staffUser = User::findFirst([
          'conditions' => 'email = :email:',
          'bind' => [
            'email' => $client->email
          ]
        ]);

        $sswSubscriptions = SswSubscriptions::findFirst([
          'columns' => 'test',
          'conditions' => 'client_id = :clientId: AND status = :status:',
          'bind' => [
            'clientId' => $clientId,
            'status' => 'active',
          ]
        ]);

        if (in_array($client->plan_name, ['affiliate', 'staff']) && !$staffUser || $sswSubscriptions && $sswSubscriptions->test && !$staffUser) {
          $developmentShopLog = DevelopmentShopsLogs::findFirst([
            'conditions' => 'client_id = :clientId: AND status = :status:',
            'bind' => [
              'clientId' => $clientId,
              'status' => 0
            ]
          ]);

          if (!$developmentShopLog) {
            $developmentShopLog = new DevelopmentShopsLogs();
            $developmentShopLog->client_id = $clientId;
            $developmentShopLog->client_email = $client->email;
            $developmentShopLog->shop = $client->shop;
            $developmentShopLog->creation_date = date('Y-m-d H:i:s');
            $developmentShopLog->save();
          }
        }

        $primary_email = ($client->contact_email) ? $client->contact_email : $client->email;
        $checkClient = Client::getClient($primary_email);
        if (!$checkClient) {
          $clientTable = new Client();
          $clientTable->save([
            'email' => $primary_email,
            'name' => $client->shop_owner,
            'type' => 'owner',
            'creation_date' => time()
          ]);
          $checkClient = $clientTable;
        } else {
          $checkClient->type = 'owner';
          $checkClient->save();
        }

        $checkShop = Shops::getShop($client->shop);
        if (!$checkShop) {
          $shop = new Shops();
          $status = $client->getActiveStatus();

          $shop->save([
            'url' => $client->shop,
            'name' => $client->name,
            'owner' => $client->email,
            'domain' => $client->domain,
            'access_token' => $client->token,
            'sswclient_id' => $client->client_id,
            'last_update' => time(),
            'status' => $client->status ? $status : 'losing',
            'removed' => 0,
            'primary_email' => $primary_email
          ]);
          $checkShop = $shop;

          $lead = Lead::findFirst("site_url = '" . $client->shop . "'");
          if ($lead) {
            $lead->status = 'client';
            $lead->save();
          }
        } else {
          $status = $client->getActiveStatus();

          $checkShop->name = $client->name;
          $checkShop->domain = $client->domain;
          $checkShop->access_token = $client->token;
          $checkShop->sswclient_id = $client->client_id;
          $checkShop->status = $client->status ? $status : 'losing';
          $checkShop->last_update = time();
          $checkShop->removed = 0;
          $checkShop->save();
        }

        if ($checkShop) {
          try {
            $api = new \Shopify\Client($checkShop);
            $shop_info = $api->call('GET', '/admin/shop.json');
            $data = [
              'phone' => $shop_info['phone'],
              'country_name' => $shop_info['country_name'],
              'shopify_plan_name' => $shop_info['plan_display_name']
            ];
            $checkShop->save($data);
          } catch (\Exception $e) {

          }
        }


        Shopstaffs::createShopStaff($checkShop->shop_id, $checkClient->client_id, 'owner');
      }
    }
    exit(1);
  }

  public function uninstallAction()
  {
    if ($this->request->isPost() && $this->getParam('shop', false) && !$this->getParam('status', 0)) {
      $url = $this->getParam('shop', false);
     $shop = Shops::getShop($url);
      if ($shop) {
        print_slack('uninstall crm date' .date('Y-m-d h:i:s') . "and shop_id" .$shop->shop_id,'bot-nurbek');
        $this->teams->teamsProactiveMessage($shop->shop_id, 'uninstall');
        $shop->status = 'losing';
        $shop->deleted_date = time();
        $shop->save();
      }
    }
    exit(1);
  }

  public function updateAction()
  {
    if ($this->request->isPost() && $this->getParam('shop', false)) {
      $url = $this->getParam('shop', false);
      $shop = Shops::getShop($url);
      if ($shop) {
        try {
          $api = new \Shopify\Client($shop);
          $shop_info = $api->call('GET', '/admin/shop.json');
          $data = [
            'domain' => $shop_info['domain'],
            'name' => $shop_info['name'],
            'phone' => $shop_info['phone'],
            'country_name' => $shop_info['country_name'],
            'shopify_plan_name' => $shop_info['plan_display_name']
          ];
          $shop->save($data);
        } catch (\Exception $e) {

        }
      }
    }
    exit(1);
  }

  public function themesAction()
  {
    if ($this->request->isPost() && $this->getParam('shop', false) && $this->getParam('status', 0)) {
      $url = $this->getParam('shop', false);
      $shop = Shops::getShop($url);
      $instagram = $this->getParam('instagram');

      if ($shop) {
        $email = $shop->owner;
        $client = Client::getClient($email);

        $simpleView =  new SimpleView();
        $simpleView->setDI($this->getDI());
        $simpleView->setViewsDir($this->view->getViewsDir());
        $templatePath = 'shops/themes';

        if ($instagram) {
          $templatePath = 'shops/instagram';
        }

        try {
          $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);
          $intercom->messages->create([
              "message_type" => "email",
              "subject" => "I noticed that you've changed your theme",
              "body" => $simpleView->render($templatePath, ['fullName' => $client->name]),
              "template" => "personal",
              "from" => [
                  "type" => "admin",
                  "id" => "1846591"
              ],
              "to" => [
                  "type" => "user",
                  "email" => $client->email
              ]
          ]);
        } catch (Exception $exception) {
          print_slack(['intercom-error-on_change_theme' => $exception->getMessage()]);
        }
      }
    }
    exit(1);
  }

  public function bridgeAction()
  {
    try {
      $shop_id = $this->getParam(0, false);
    } catch (\Exception $e) {
      print_die('Shop not found!!!');
    }
    $off = $this->getParam('off', false);
    $dev2 = $this->getParam('dev2', false);
    $shop = Shops::findFirst($shop_id);

    if (!$shop_id || !$shop) {
      print_die('Shop not found!!!');
    }

    $code = generateUniqueCode();
    $url = "https://growave.io/login?from=hehe&he=pass&shop={$shop->url}&key={$code}";
    if ($off) {
      $url = "https://off.growave.io/login?from=hehe&he=pass&shop={$shop->url}&key={$code}";
    } elseif($dev2) {
      $url = "https://dev2.growave.io/login?from=hehe&he=pass&shop={$shop->url}&key={$code}";
    }
    if ($this->getParam('integrate')) {
      $url .= '&integrate=1';
    }

    return $this->response->redirect($url, true);
  }

  public function bridgeeditorAction()
  {
    try {
      $shop_id = $this->getParam(0, false);
    } catch (\Exception $e) {
      print_die('Shop not found!!!');
    }
    $off = $this->getParam('off', false);
    $shop = Shops::findFirst($shop_id);

    if (!$shop_id || !$shop) {
      print_die('Shop not found!!!');
    }

    $code = generateUniqueCode();
    $url = "https://growave.io/login?from=hehe&he=pass&shop={$shop->url}&key={$code}";
    if ($this->getParam('integrate')) {
      $url .= '&integrate=1';
    }

    $url .= '&redirect_to_editor=1';

    return $this->response->redirect($url, true);
  }

  public function removeFilesAction()
  {
    $shop_id = $this->getParam(0, false);
    $shop = Shops::findFirst($shop_id);
    $app = $this->getParam(1, false);

    if (!$shop_id || !$shop || !$app) {
      print_die('Shop or app not found!!!');
    }

    $code = generateUniqueCode();

    $out = '';
    if ($curl = curl_init()) {
      curl_setopt($curl, CURLOPT_URL, "https://growave.io/lite/index/uninstallLite?&shop={$shop->url}&key={$code}");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, "app=" . $app);
      $out = curl_exec($curl);
      curl_close($curl);
    }

    exit($out);
  }

  public function changesAction()
  {
    $shop_id = $this->getParam('id');

    $changes = Modifications::getChangeList($shop_id);

    $this->view->setVar('shop_id', $shop_id);
    $this->view->setVar('changes', $changes);
    $this->view->setVar('current_key', '');

    $this->view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
  }

  public function setRemovedAction()
  {
    $client_id = (int)$this->getParam('client_id', false);
    $code = $this->getParam('code', false);
    $status = (int)$this->getParam('status', 0);

    if (!$client_id || !check4UniqueKey($code)) {
      exit(1);
    }

    $shop = Shops::findFirst(['conditions' => 'sswclient_id = ?0', 'bind' => [$client_id]]);
    if (!$shop) {
      exit(2);
    }

    if ($shop->removed != $status) {
      $shop->removed = $status;
      $shop->save();
      exit(0);
    } else {
      exit(3);
    }
  }

  public function autoIntegrateAction()
  {
    $sswclient_id = (int)$this->getParam('sswclient_id', 0);
    $key = $this->getParam('key', false);
    $value = $this->getParam('value', false);
    $old_value = $this->getParam('old_value', false);
    $author_id = (int)$this->getParam('author_id', 0);
    $author_id = ($author_id) ? $author_id : 25; // set TechSocialShopWave by default

    $shop = Shops::findFirst(['conditions' => "sswclient_id = ?0", 'bind' => [$sswclient_id]]);
    if (!$shop || $key === false || $value === false || $old_value === false) {
      exit(0);
    }

    $modify = new Modifications();
    $modify->save([
      'user_id' => $author_id,
      'shop_id' => $shop->shop_id,
      'file' => $key,
      'value' => $value,
      'old_value' => $old_value,
      'date' => time()
    ]);

    exit(1);
  }

  public function autoIntegrateUserIDAction()
  {
    header("Access-Control-Allow-Origin: *://growave.io");

    echo 'crmViewerID = ' . (int)Core::getViewerId() . ';';
    exit();
  }

  public function checkIntegrationSupport($api, $shop_theme)
  {
    $sswTheme = false;
    if ($shop_theme['theme_store_id']) {
      $sswTheme = SswTheme::findFirst($shop_theme['theme_store_id']);
    } else {
      $sswThemes = SswTheme::find();
      foreach ($sswThemes as $item) {
        if ($item->unique_snippet) {
          try {
            $asset = $api->call('GET', '/admin/themes/' . $shop_theme['id'] . '/assets.json', ['asset[key]' => $item->unique_snippet]);
            if ($item->unique_line) {
              if (strpos($asset['value'], $item->unique_line) !== false) {
                $sswTheme = $item;
                break;
              }
            } else {
              $sswTheme = $item;
              break;
            }
          } catch (\Exception $e) {
            // todo continue
          }
        }
      }
    }

    return $sswTheme ? true : false;
  }
}
