<?php

/**
 * Created by PhpStorm.
 * User: ���� ���������
 * Date: 23.11.2015
 * Time: 17:20
 */
class DiscountController extends AbstractController
{
  public function discountsAction()
  {

      $params = $this->_getAllParams();

      $discounts = new SswDiscount();

      $page = isset($params['page']) ? $params['page'] : 1;
      $limit = isset($params['limit']) ? $params['limit'] : 20;


      $date = date('Y-m-d');

      $bind = array();
      $condition = "true";

      if (isset($params['isActive']) && $params['isActive'] && is_string($params['isActive'])) {
          if ($params['isActive'] == 'active') {
              $condition .= " AND (expiration_date >= :date: AND count > used)";
              $bind['date'] = $date;
          }
          else if ($params['isActive'] == 'expired') {
              $condition .= " AND (expiration_date < :date: OR used = count)";
              $bind['date'] = $date;
          }

      }


      if (isset($params['package_id']) && is_numeric($params['package_id'])) {
          $condition .= " AND min_package = :package:";
          $bind['package'] = $params['package_id'];

      }

      if (isset($params['keyword']) && $params['keyword'] ) {
          $condition .= " AND (code LIKE :keyword: or note LIKE :keyword:)";
          $bind['keyword'] = '%'.$params['keyword'].'%';
      }


      $discounts = SswDiscount::find(
          [
              $condition,
              "order" => "discount_id DESC",
              "bind" => $bind
          ]
      );
      $discountsCount =  $discounts->count();
      echo ($discountsCount);

      $result = new Phalcon\Paginator\Adapter\Model(
          [
              'data'  => $discounts,
              'limit' => $limit,
              'page'  => $page
          ]
      );


      $this->view->isActive = $params['isActive'];
      $this->view->package = $params['package_id'];
      $this->view->keyword = $params['keyword'];
      $this->view->discounts = $result->getPaginate();
      $this->view->discountsCount = $discountsCount;
      $packages = SswPackages::find();
      //$packages = SswPackages::find("enabled = '1'");
      $this->view->packages = $packages;
  }

  public function startupAction()
  {
    $request_id = $this->request->get('request_id', 'int', 0);
    $action = $this->request->get('action', 'string');
    $status = 'none';
    $request = null;
    if ($request_id) {
      try {
        $request = SswRequests::findFirst($request_id);
        if ($request) {
          $status = 'no_action';
          if ($action) {
            if ($action == 'approve') {
              $request->status = 'accepted';
              $status = 'accepted';
            } elseif ($action == 'reject') {
              $request->status = 'rejected';
              $status = 'rejected';
            } else {
              $status = 'invalid_action';
            }
            $request->save();
          }
        } else {
          $status = 'request_not_found';
        }
      } catch (\Exception $e) {
        $status = 'error';
      }
    }
    $this->view->setVars([
      'request' => $request,
      'status' => $status
    ]);
  }

  public function creatediscountAction(){

    if ($this->request->isPost()) {

      $sswDiscountItem = new SswDiscount();

      $sswDiscountItem->partner_id = $this->getParam('partner_id', 0);
      $sswDiscountItem->code = $this->getParam('code');
      $sswDiscountItem->trial_days = $this->getParam('trial_days');
      $sswDiscountItem->app = $this->getParam('app');
      $sswDiscountItem->expiration_date = $this->getParam('expiration_date')." 00:00:00";
      $sswDiscountItem->used = $this->getParam('used');
      $sswDiscountItem->count = $this->getParam('count');

      if ($this->getParam('app') == 'default') {
        $sswDiscountItem->min_package = $this->getParam('min_package_default');
      } else {
        $sswDiscountItem->min_package = $this->getParam('min_package_instagram');
      }
      $sswDiscountItem->note = strip_tags($this->getParam('note'));

      $sswDiscountItem->save();

      return $this->response->redirect($this->url->get(array('for' => 'default','controller' => 'discount', 'action' => 'discounts' )), true);

    } else {

      $packages_default = SswPackages::find("app = 'default'");
      $packages_instagram = SswPackages::find("app = 'instagram'");

      $this->view->packages_default = $packages_default;
      $this->view->packages_instagram = $packages_instagram;

    }

  }

  public function editdiscountAction(){

    if ($this->request->isPost()) {
      $discount_id = $this->getParam('discount_id', 0);
      $client = SswDiscount::findFirst('discount_id = ' . $discount_id);
      $client->partner_id = $this->getParam('partner_id', 0);
      $client->code = $this->getParam('code');
      $client->trial_days = $this->getParam('trial_days');

      $client->app = $this->getParam('app', '');
      $client->expiration_date = $this->getParam('expiration_date')." 00:00:00";
      $client->used = $this->getParam('used');
      $client->count = $this->getParam('count');

      if ($this->getParam('app') == 'default') {
        $client->min_package = $this->getParam('min_package_default');
      } else {
        $client->min_package = $this->getParam('min_package_instagram');
      }
      $client->note = strip_tags($this->getParam('note'));

      $client->save();

      return $this->response->redirect($this->url->get(array('for' => 'default','controller' => 'discount', 'action' => 'discounts' )), true);
    } else {

      $packages_default = SswPackages::find("app = 'default'");
      $packages_instagram = SswPackages::find("app = 'instagram'");

      $this->view->packages_default = $packages_default;
      $this->view->packages_instagram = $packages_instagram;

      $discount = SswDiscount::findFirst("discount_id =".$this->getParam('0'));
      $this->view->discount_edit = $discount;
    }

  }

  public function deletediscountAction(){
    $discount = SswDiscount::findFirst("discount_id =".$this->getParam('0'));
    $discount->delete();
    return $this->response->redirect($this->url->get(array('for' => 'default','controller' => 'discount', 'action' => 'discounts' )), true);
  }
}