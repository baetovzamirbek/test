<?php

/**
 * Created by PhpStorm.
 * User: bolot
 * Date: 11.01.2018
 * Time: 11:00
 */
class InvoicesController extends AbstractController
{
  public function listAction()
  {
    $this->view->setVar("pageTitle", "CRM Invoices");
    $params = array(
      'page' => $this->getParam('page', 1),
      'ipp' => 20,
      'order' => 'invoice_id DESC'
    );
    $paginator = Invoices::getInvoicesPaginator($params);

    $this->view->setVars(array(
      'paginator' => $paginator,
      'clientModel' => new Client(),
      'site_url' => $this->url->getBaseUri()
    ));
  }

  public function searchClientAction()
  {
    $search_by = $this->getParam('by', 'name');
    $key_word = $this->getParam('query', '');
    $clients = Client::find([
      'conditions' => "name  LIKE :keyword: OR email  LIKE :keyword:",
      'bind' => ['keyword' => "%{$key_word}%"]
    ]);

    $response = array(
      'query' => $key_word,
      'suggestions' => array()
    );

    foreach ($clients as $client) {
      $response['suggestions'][] = array(
        'data' => array(
          'client_id' => $client->client_id,
          'email' => $client->email,
          'name' => $client->name
        ),
        'value' => $client->{$search_by}
      );
    }

    exit(json_encode($response));
  }

    public function searchInvoiceAction()
    {
        $this->view->disable();
        if ($this->request->isPost() && $this->request->isAjax()) {
            $data = json_decode($this->getParam('data'));
            $searchBy = json_decode($this->getParam('searchBy'));

            $invoice = ($searchBy == 'invoice_id_random') ?
                 Invoices::searchInvoice($data,   1) :
                 Invoices::searchInvoice($data,   0);

            if(!empty($invoice)) {
              $result = [
                'client_name' => Client::getClientBy($invoice->client_id)->name,
                'invoice'     => $invoice,
                'date_create' => ($invoice->date_create) ? date('Y-m-d h:m:s',$invoice->date_create) : '-'
              ];
              return json_encode($result);
            }
            else {
              return json_encode(false);
            }
        }
    }

    public function updateStatusAction()
    {
        $this->view->disable();
        if ($this->request->isPost() && $this->request->isAjax()) {

            $data = json_decode($this->getParam('dataForUpdate'));
            $invoice = Invoices::findFirst([
                'conditions' => 'invoice_id_random = ?0',
                'bind' => $data->invoice_id_random,
            ]);

            date_default_timezone_set('Asia/Bishkek');
            $invoice->user_who_changed    = Core::getViewerId();
            $invoice->previous_status     = $invoice->status;
            $invoice->status              = $data->payment_status;
            $invoice->status_changed_date = strtotime("now");

            return ($invoice->update()) ? json_encode('success') : json_encode('not changed');
        }

    }

    public function createAction()
  {
    $client_id = $this->getParam('client_id', '');
    $description = $this->getParam('description', '');
    $amount = $this->getParam('amount', '');
    $success = true;
    $message = '';

    if (!$client_id) {
      return $this->view->setVars(array(
        'success' => false,
        'message' => 'Please select the client!'
      ));
    }

    if ($this->request->isPost()) {
      $invoice = new Invoices();
      $rand_invoice_id = $this->_getUniqInvoice($client_id);
      $time_create = time();
      if (!$invoice->save(array(
        'client_id' => $client_id,
        'description' => $description,
        'invoice_id_random' => $rand_invoice_id,
        'date_create' => $time_create,
        'date_payment' => 0,
        'site_url' => $this->url->getBaseUri(),
        'amount' => $amount,
        'status' => 0
      ))) {
        foreach ($invoice->getMessages() as $msg) {
          $message .= $msg . "\n";
        }
      } else {
        $success = true;
      }
    }

    if ($success) {
      $this->view->setVars(array(
        'invoice_id' => $invoice->invoice_id,
        'client_id' => $client_id,
        'description' => $description,
        'invoice_id_random' => $rand_invoice_id,
        'date_create' => $time_create,
        'date_payment' => 0,
        'amount' => $amount,
        'clientModel' => new Client(),
        'status' => 0,
        'success' => $success,
        'message' => $message
      ));
    } else {
      $this->view->setVars(array(
        'success' => $success,
        'message' => 'No data'
      ));
    }
  }

  public function deleteAction()
  {
    if ($this->request->isPost() && $this->getParam('invoice_id', false)) {
      $invoice_id = $this->getParam('invoice_id');
      $invoice = Invoices::findFirst("invoice_id = $invoice_id");
      if ($invoice) {
        $invoice->delete();
      }
    }
  }

  private function _getUniqInvoice($client_id = 0)
  {
    $rand_invoice_id = $client_id . rand(11111, 99999);
    if (Invoices::findFirst(['invoice_id_random = ?0', 'bind' => [$rand_invoice_id]])) {
      $rand_invoice_id = $this->_getUniqInvoice($client_id);
    }

    return $rand_invoice_id;
  }
}