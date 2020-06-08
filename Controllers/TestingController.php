<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 4/23/18
 * Time: 1:54 PM
 */

class TestingController extends AbstractController
{
  /** @var  \Phalcon\Db\Adapter\MongoDB\Database */
  private $mongoDb;
  private $services = [
    'facebook',
    'google',
    'twitter',
    'amazon',
    'yahoo',
    'instagram',
    'tumblr'
  ];

  private $collection__fields = [
    'shops' => [
      'url' => 'url',
      'password' => 'string',
      'testing_target' => 'string',
      'enabled' => 'int',
    ],
    'social_accounts' => [
      'email' => 'email',
      'password' => 'string',
      'service' => 'string',
      'enabled' => 'int',
    ],
    'customers' => [
      'first_name' => 'string',
      'last_name' => 'string',
      'email' => 'email',
      'password' => 'string',
      'enabled' => 'int',
    ]
  ];

  public function initialize()
  {
    $mongoConfig = $this->getDI()->get('config')->mongodbTrackerTesting;
    $mongoClient = new \Phalcon\Db\Adapter\MongoDB\Client("mongodb://{$mongoConfig->host}:{$mongoConfig->port}/{$mongoConfig->db}");
    $this->mongoDb = $mongoClient->selectDatabase($mongoConfig->db);
  }

  public function indexAction()
  {
    /** @var \Phalcon\Db\Adapter\MongoDB\Model\CollectionInfoLegacyIterator $collections */
    $collection = $this->mongoDb->selectCollection('shops');
    $success = null;
    $message = null;
    if ($this->request->isPost()) {
      $success = false;
      $message = 'Something went wrong!';
      if ($_id = $this->request->getPost('_id', 'string')) {
        /** @var \Phalcon\Db\Adapter\MongoDB\Model\BSONDocument $shop */
        $shop = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
        if ($shop) {
          if ($document = $this->buildDocArrayFromRequest('shops')) {
            $document['enabled'] = isset($document['enabled']) && $document['enabled'];
            //$document;
            $collection->updateOne(['_id' => new \MongoDB\BSON\ObjectID($_id)], ['$set' => $document]);
            $updated_shop = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
            $this->view->setVar('updated_shop', $updated_shop);
            $this->view->setVar('saved_shop', $updated_shop);
            $success = true;
            $message = 'Changes has been saved!';
          } else {
            $success = false;
            $message = 'Required fields needs to be filled!';
          }
        }
      } elseif ($this->request->getPost('new', 'int')) {
        if ($url = $this->request->getPost('url', 'url')) {
          $shop = $collection->findOne(['url' => $url]);
          if ($shop) {
            $success = false;
            $message = "Shop $url is already in list!";
          } else {
            $doc = $this->buildDocArrayFromRequest('shops');
            if ($doc) {
              $result = $collection->insertOne($doc);
              if ($result && $_id = $result->getInsertedId()) {
                $created_shop = $collection->findOne(['_id' => $_id]);
                if ($created_shop) {
                  $success = true;
                  $this->view->setVar('created_shop', $created_shop);
                  $this->view->setVar('saved_shop', $created_shop);
                }
              }
              $message = "Shop $url is added to testing list!";
            } else {
              $success = false;
              $message = 'Required fields needs to be filled!';
            }
          }
        }
      }
    }
    $shops = $collection->find()->toArray();
    $state_records = [];
    /** @var \Phalcon\Db\Adapter\MongoDB\Model\BSONDocument $shop */
    foreach ($shops as $shop) {
      /** @var \MongoDB\BSON\ObjectID $_id */
      $shop_records = $this->mongoDb->selectCollection('state_suites')->find([
        'shop_url' => $shop->url
      ])->toArray();
      if (count($shop_records)) {
        $state_records[$shop->url] = $shop_records;
      }
    }
    $this->view->setVars([
      'success' => $success,
      'message' => $message,
      'shops' => $shops,
      'state_records' => $state_records
    ]);
  }

  public function customersAction()
  {
    /** @var \Phalcon\Db\Adapter\MongoDB\Model\CollectionInfoLegacyIterator $collections */
    $collection = $this->mongoDb->selectCollection('customers');
    $success = null;
    $message = null;
    if ($this->request->isPost()) {
      $success = false;
      $message = 'Something went wrong!';
      if ($_id = $this->request->getPost('_id', 'string')) {
        /** @var \Phalcon\Db\Adapter\MongoDB\Model\BSONDocument $customer */
        $customer = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
        if ($customer) {
          if ($document = $this->buildDocArrayFromRequest('customers')) {
//            print_die([$document, $_POST]);
            $document['enabled'] = isset($document['enabled']) && $document['enabled'];
            $collection->updateOne(['_id' => new \MongoDB\BSON\ObjectID($_id)], ['$set' => $document]);
            $updated_customer = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
            $this->view->setVar('updated_customer', $updated_customer);
            $this->view->setVar('saved_customer', $updated_customer);
            $success = true;
            $message = 'Changes has been saved!';
          } else {
            $success = false;
            $message = 'Required fields needs to be filled!';
          }
        } else {
          $success = false;
          $message = 'No such Customer!';
        }
      } elseif ($this->request->getPost('new', 'int')) {
        if ($email = $this->request->getPost('email', 'email')) {
          $customer = $collection->findOne(['email' => $email]);
          if ($customer) {
            $success = false;
            $message = "Customer with email: $email is already in list!";
          } else {
            $doc = $this->buildDocArrayFromRequest('customers');
            if ($doc) {
              $result = $collection->insertOne($doc);
              if ($result && $_id = $result->getInsertedId()) {
                $created_customer = $collection->findOne(['_id' => $_id]);
                if ($created_customer) {
                  $success = true;
                  $this->view->setVar('created_customer', $created_customer);
                  $this->view->setVar('saved_customer', $created_customer);
                }
              }
              $message = "Customer with email: $email is added to testing list!";
            } else {
              $success = false;
              $message = 'Required fields needs to be filled!';
            }
          }
        }
      }
    }
    $customers = $collection->find()->toArray();
    $this->view->setVars([
      'success' => $success,
      'message' => $message,
      'customers' => $customers,
    ]);
  }

  public function deleteAction()
  {
    $success = 0;
    $_id = $this->request->getPost('_id', 'string');
    $collection_name = $this->request->getPost('collection');
    if ($this->request->isPost() && $_id && in_array($collection_name, ['shops', 'state_suites', 'customers', 'social_accounts'])) {
      /** @var \Phalcon\Db\Adapter\MongoDB\Model\CollectionInfoLegacyIterator $collections */
      $collection = $this->mongoDb->selectCollection($collection_name);
      try {
        $collection->deleteOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
        $success = 1;
      } catch (\Exception $e) {
        $success = 0;
      }
    }
    exit(json_encode([
      'success' => $success
    ]));
  }

  public function statusAction()
  {
    $success = 0;
    $message = 'Something went wrong';
    $_id = $this->request->getPost('_id', 'string');
    $collection_name = $this->request->getPost('collection');
    if ($this->request->isPost() && $_id && in_array($collection_name, ['shops', 'state_suites', 'customers', 'social_accounts'])) {
      /** @var \Phalcon\Db\Adapter\MongoDB\Model\CollectionInfoLegacyIterator $collections */
      $collection = $this->mongoDb->selectCollection($collection_name);
      try {
        $collection->updateOne(['_id' => new \MongoDB\BSON\ObjectID($_id)], ['$set' => [
          'enabled' => !!$this->request->getPost('enabled', 'int')
        ]]);
        $success = 1;
        $message = 'Updated';
      } catch (\Exception $e) {
        $success = 0;
        $message = $e->getMessage();
      }
    }
    exit(json_encode([
      'success' => $success,
      'message' => $message,
      'status' => $this->request->getPost('enabled')
    ]));
  }

  public function socialAction()
  {
    /** @var \Phalcon\Db\Adapter\MongoDB\Model\CollectionInfoLegacyIterator $collections */
    $collection = $this->mongoDb->selectCollection('social_accounts');
    $success = null;
    $message = null;
    if ($this->request->isPost()) {
      $success = false;
      $message = 'Something went wrong!';
      if ($_id = $this->request->getPost('_id', 'string')) {
        /** @var \Phalcon\Db\Adapter\MongoDB\Model\BSONDocument $account */
        $account = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
        if ($account) {
          if ($document = $this->buildDocArrayFromRequest('social_accounts')) {
//            print_die([$document, $_POST]);
            $document['enabled'] = isset($document['enabled']) && $document['enabled'];
            $collection->updateOne(['_id' => new \MongoDB\BSON\ObjectID($_id)], ['$set' => $document]);
            $updated_account = $collection->findOne(['_id' => new \MongoDB\BSON\ObjectID($_id)]);
            $this->view->setVar('updated_account', $updated_account);
            $this->view->setVar('saved_account', $updated_account);
            $success = true;
            $message = 'Changes has been saved!';
          } else {
            $success = false;
            $message = 'Required fields needs to be filled!';
          }
        } else {
          $success = false;
          $message = 'No such account found!';
        }
      } elseif ($this->request->getPost('new', 'int')) {

        $email = $this->request->getPost('email', 'email');
        $service = $this->request->getPost('service', 'string');

        if ($email && $service && in_array($service, $this->services)) {
          $account = $collection->findOne(['email' => $email, 'service' => $service]);
          if ($account) {
            $success = false;
            $message = "Account with email: $email for $service is already in list!";
          } else {
            $doc = $this->buildDocArrayFromRequest('social_accounts');
            if ($doc) {
              try {
                $result = $collection->insertOne($doc);
                if ($result && $_id = $result->getInsertedId()) {
                  $created_account = $collection->findOne(['_id' => $_id]);
                  if ($created_account) {
                    $success = true;
                    $this->view->setVar('created_account', $created_account);
                    $this->view->setVar('saved_account', $created_account);
                  }
                }
                $message = "Account with email: $email for $service is added to testing list!";
              } catch (\Exception $e) {
                $message = $e->getMessage();
              }
            } else {
              $success = false;
              $message = 'Required fields needs to be filled!';
            }
          }
        } else {
          print_die($_POST);
        }
      }
    }
    $accounts = $collection->find()->toArray();
    $this->view->setVars([
      'success' => $success,
      'message' => $message,
      'accounts' => $accounts,
    ]);
  }

  private function buildDocArrayFromRequest($collection_name)
  {
    $document = [];
    foreach ($this->collection__fields[$collection_name] as $shop_field => $type) {
      $document[$shop_field] = $this->request->getPost($shop_field, $type, null, true);
      if ($document[$shop_field] === false) {
        return false;
      }
    }
    return $document;
  }
}