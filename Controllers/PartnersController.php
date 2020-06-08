<?php

class PartnersController extends AbstractController {
  public function indexAction()
  {
    if ($this->request->isPost()) {
      $partner_id = $this->request->getPost('partner_id', 'int');
      if($partner_id) {
        $partner = SswPartner::findFirst($partner_id);
        $date_raw = $this->request->getPost('date', 'string');
        $date_raw = $date_raw ? $date_raw : date('Y-m-d H:i:s');
        $date = date('Y-m-d H:i:s', strtotime($date_raw));
        $amount = $this->request->getPost('amount', 'float');
        $newWithDraw = new SswPartnerWithdraw();
        if ($newWithDraw->save([
          'partner_id' => $partner_id,
          'amount' => $amount,
          'date' => $date,
          'comment' => $this->request->getPost('comment', 'string', ''),
        ])) {
          $partner->balance -= $amount;
          $partner->withdraw += $amount;
          $partner->save();
        };
      }
    }
    $ipp = 20;
    $page = $this->request->get('page', 'int', 1);
    $keyword = $this->request->get('keyword', [
      'string'
    ], '');
    $only_more_100_usd = $this->request->get('balance_more_100', 'int', 0);
    $select = SswPartner::query()->orderBy('balance DESC, earned DESC, partner_id DESC');
    if ($keyword) {
      $select->where("name LIKE '%$keyword%' OR email LIKE '%$keyword%'");
    }
    if ($only_more_100_usd) {
      $select->andWhere("balance >= 100");
    }
    $pagination_select = (clone $select);
    $total_page_count = ceil(count($pagination_select->columns('partner_id')->execute()->toArray()) / $ipp);
    $partners = $select->limit($ipp, (($page < 1 ? 1 : $page)  - 1) * $ipp)->execute();
    $vars = [
      'page' => $page,
      'pagination_multiplier' => floor($page / 4),
      'total_page_count' => $total_page_count,
      'partners' => $partners,
      'keyword' => $keyword,
      'only_more_100_usd' => $only_more_100_usd,
    ];
    $this->view->setVars($vars);
  }

  public function historyAction()
  {
    $withdraws = [];
    if ($this->request->isPost()) {
      $partner_id = $this->request->getPost('partner_id', 'int');
      if ($partner_id) {
        $withdraws = SswPartnerWithdraw::query()->where('partner_id = :partner_id:', [
          'partner_id' => $partner_id
        ])->execute();
      }
    }
    foreach ($withdraws as $withdraw) {
      $withdraw->time = strtotime($withdraw->date);
    }

    $this->view->setVars([
      'withdraws' => $withdraws
    ]);
  }

  public function createAction()
  {
    $success = false;
    $message = 'Invalid request method!';
    if ($this->request->isPost()) {
      $client_id = $this->request->getPost('client_id', 'int');
      $partner_id = $this->request->getPost('partner_id', 'int');
      $date = $this->request->getPost('join_date', 'string', date('Y-m-d'));
      $message = 'Select shop and partner!';
      if ($client_id && $partner_id) {
        $partner_client = SswPartnerClient::findFirst([
          'conditions' => 'partner_id = ?0 AND client_id = ?1',
          'bind' => [
            $partner_id,
            $client_id
          ]
        ]);
        $message = 'This shop is already added as referral of the partner!';
        if (!$partner_client) {
          try {
            $pc = new SswPartnerClient([
              'partner_id' => $partner_id,
              'client_id' => $client_id,
              'join_time' => strtotime($date)
            ]);
            $message = 'This shop is already added as referral of the partner!';
            if ($pc->save()) {
              $message = 'Shop added as referral successfully!';
              $success = true;
            }
          } catch (\Exception $e) {
            $message = $e->getMessage();
            $success = false;
          }
        }
      }
    }
    exit(json_encode([
      'message' => $message,
      'success' => $success
    ]));
  }
}