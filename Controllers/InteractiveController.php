<?php

/**
 * Created by PhpStorm.
 * User: ���� ���������
 * Date: 23.11.2015
 * Time: 17:20
 */
class InteractiveController extends AbstractController
{
  public function feedAction()
  {
    $page = $this->getParam('page', 1);
    $module = $this->getParam('module', null);
    $group = $this->getParam('group', null);
    $ipp = 18;

    $groups = array(
      1 => 'Advice',
      2 => 'Did you know?',
      3 => 'What\'s new?'
    );

    $modules = SswFeedCards::getModules();
    $mArray = array();
    foreach ($modules as $m) {
      $mArray[] = $m->module;
    }

    $cards = SswFeedCards::getClientCards(array(
      'page' => $page,
      'ipp' => $ipp,
      'module' => $module,
      'group' => $group
    ));

    $paginatorLastCondition = '';
    if ($module) {
      $paginatorLastCondition = "module ='{$module}'";
      if($group) {
        $paginatorLastCondition .= " AND [group] = {$group}";
      }
    } elseif($group) {
      $paginatorLastCondition = "[group] = {$group}";
    }
    $paginatorLast = ceil(SswFeedCards::count($paginatorLastCondition) / $ipp);

    $query_string = '/interactive/feed';
    $this->view->setVars(array(
      'paginatorCurrent' => $page,
      'query_string' => $query_string,
      'paginatorLast' => $paginatorLast,
      'cards' => $cards,
      'modules' => $mArray,
      'module' => $module,
      'groups' => $groups,
      'group' => $group
    ));
  }

  public function cardpublishAction()
  {
    $response = array(
      'success' => false,
    );
    if ($this->request->isPost() && ($card_id = $this->getParam('card_id', false)) && ($published = $this->getParam('published', null)) !== null) {
      if ($card2publish = SswFeedCards::findFirst('card_id = ' . $card_id)) {
        try {
          $card2publish->published = $published;
          if ($card2publish->save()) {
            $response['success'] = true;
          } else {
            $response['error'] = true;
            $response['card_id'] = $card_id;
            $response['card'] = $card2publish->toArray();
            $response['message'] = implode("<br>", $card2publish->getMessages());
          }
        } catch (\Exception $e) {
          $response['error'] = true;
          $response['message'] = nl2br($e->getMessage() . '<br>' . $e->getTraceAsString());
        }
      }
    } else {
      $response['error'] = true;
      $response['message'] = "Invalid Method";
    }
    exit(json_encode($response));
  }

  public function carddeleteAction()
  {
    $response = array(
      'success' => false,
    );
    if ($this->request->isPost() && $card_id = $this->getParam('card_id', false)) {
      if ($card2delete = SswFeedCards::findFirst('card_id = ' . $card_id)) {
        try {
          if ($card2delete->delete()) {
            $response['success'] = true;
          } else {
            $response['error'] = true;
            $response['message'] = implode("<br>", $card2delete->getMessages());
          }
        } catch (\Exception $e) {
          $response['error'] = true;
          $response['message'] = nl2br($e->getMessage() . '<br>' . $e->getTraceAsString());
        }
      }
    } else {
      $response['error'] = true;
      $response['message'] = "Invalid Method";
    }
    exit(json_encode($response));
  }

  public function feedcreateAction()
  {

    $lastCard = SswFeedCards::query()
      ->orderBy('[order] DESC')
      ->limit(1)
      ->execute()
      ->getFirst();
    $this->view->setVar('error', 0);
    $this->view->setVar('saved', 0);
    $card_id = $this->getParam('card_id', false);
    $card = false;
    $this->view->card_id = $card_id;
    if ($card_id && $card = SswFeedCards::findFirst('card_id = ' . $card_id))
      $this->view->setVar('card', $card);
    if ($this->request->isPost()) {
      $data = $this->request->getPost();
      if ($data = $this->validate($data)) {
        if (!$card) {
          $card = new SswFeedCards();
          $resp = $this->uploadFile();
          if (isset($resp['status']) && $resp['status'] == 'ok' && $resp['image_url']) {
            $data['image_url'] = $resp['image_url'];
          }
        } elseif ($this->uploadFile($card)) {
          unset($data['image_url']);
        }

        if ($card->save($data)) {
          $this->view->setVar('saved', 1);
          $this->view->setVar('card', $card);
        } else {
          $messages = '';
          foreach ($card->getMessages() as $message) {
            $messages .= "\n" . $message;
          }
          $this->view->setVar('error', array(
            'message' => $messages
          ));
        }
      } else {
        $this->view->setVar('error', array(
          'message' => 'Invalid data! Given data did not pass validation'
        ));
      }
    }
    $this->view->setVars(array(
      'order' => $lastCard->order + 1
    ));
  }

  private function validate($data)
  {
    $valid = false;
    if (isset($data['type'])) {
      if (!isset($data['condition']) || preg_match('/create|delete|insert|remove|update|save|add/i', $data['condition'])) {
        return false;
      }
      if ($data['type'] == 'blog') {
        if (isset($data['params'])) {
          if (isset($data['params']['blog']) && isset($data['params']['blog']['url']) && isset($data['params']['blog']['estimate'])) {
            $valid = true;
          }
        }
      } else {
        $valid = true;
      }
      if (isset($data['params'])) {
        if (isset($data['params']['links'])) {
          if (count($data['params']['links']) == 1) {
            unset($data['params']['links']);
          } else {
            sort($data['params']['links']);
            unset($data['params']['links'][0]);
            $data['params']['links'] = array_values($data['params']['links']);
            foreach ($data['params']['links'] as $index => $link) {
              if (isset($link['attrs'])) {
                if (($attrs = json_decode($link['attrs'])) !== false) {
                  $link['attrs'] = $attrs;
                  $data['params']['links'][$index] = $link;
                } else return false;
              }
            }
          }
        }

        if (isset($data['params']['buttons'])) {
          if (count($data['params']['buttons']) == 1) {
            unset($data['params']['buttons']);
          } else {
            sort($data['params']['buttons']);
            unset($data['params']['buttons'][0]);
            $data['params']['buttons'] = array_values($data['params']['buttons']);
            foreach ($data['params']['buttons'] as $index => $button) {
              if (isset($button['attrs'])) {
                if (($attrs = json_decode($button['attrs'])) !== false) {
                  $button['attrs'] = $attrs;
                  $data['params']['buttons'][$index] = $button;
                } else return false;
              }
            }
          }
        }
        if (isset($data['params']['blog']) && !$data['params']['blog']['url']) {
          unset($data['params']['blog']);
        }

        if (($json = json_encode($data['params'])) !== false)
          $data['params'] = json_encode($data['params']);
        else return false;
      }
      unset($data['add_link']);
      unset($data['add_button']);
      unset($data['submit']);
      $data['creation_date'] = date('Y-m-d:H:i:s');
    }
    return !$valid ? $valid : $data;
  }

  private function uploadFile($forCard = null)
  {

    if (isset($_FILES['image_url']) && !empty($_FILES['image_url'])) {

      $postUrl = 'https://dev.growave.io/utility/feed-image';

      $file = '/mnt/www/crm.growave.io/public/temp/' . md5(time()) . '.' . pathinfo($_FILES['image_url']['name'], PATHINFO_EXTENSION);

      if (move_uploaded_file($_FILES['image_url']['tmp_name'], $file) && exif_imagetype($file)) {
        $post = array(
          'unique_key' => generateUniqueCode(),
          'card_id' => $forCard->card_id,
          'card_image' => '@' . $file
        );
        $curlResource = curl_init();
        curl_setopt($curlResource, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlResource, CURLOPT_VERBOSE, true);
        curl_setopt($curlResource, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlResource, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curlResource, CURLOPT_URL, $postUrl);
        curl_setopt($curlResource, CURLOPT_POST, 1);
        curl_setopt($curlResource, CURLOPT_POSTFIELDS, $post);
        $result = json_decode(curl_exec($curlResource), 1);
        curl_close($curlResource);
        unlink($file);
        return $result;
      } else return false;

    } else return false;
  }
}