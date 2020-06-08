<?php

use GuzzleHttp\Exception\GuzzleException;
use Intercom\IntercomClient;
use Phalcon\Paginator\Adapter\QueryBuilder;

class AafbController extends AbstractController
{
  public function testAction()
  {
    $var = [
      'success' => true,
      'messages' => [
        'message1',
        'message2',
        'message3',
      ]
    ];

    return json_encode($var);
  }

  public function indexAction()
  {
      $people = $vertical = $countries = [];
      $params = [
          'country' => $this->request->getQuery('country', 'int', -1),
          'city' => $this->request->getQuery('city', null, -1),
          'vertical' => $this->request->getQuery('vertical', 'int', -1),
          'tsu_from' => $this->request->getQuery('tsu_from', 'int', -1),
          'tsu_to' => $this->request->getQuery('tsu_to', 'int', -1),
          'imported' => $this->request->getQuery('imported', 'int', -1),
          'page' => $this->request->getQuery('page', 'int', 1)
      ];

      $builder = $this->modelsManager->createBuilder()
          ->columns([
              'Sites.id', 'Sites.domain', 'Sites.company', 'Sites.vertical AS vertical_id', 'TechSpendUsd.id AS tsu_id',
              'TechSpendUsd.value AS tsu_value', 'Sites.city', 'Sites.state', 'Sites.country AS country_id',
              'Sites.telephones', 'Sites.imported', 'Sites.twitter', 'Sites.facebook', 'Sites.linkedin', 'Sites.instagram'
          ])
          ->from('Sites')
          ->innerJoin('TechSpendUsd', 'Sites.tech_spend_usd = TechSpendUsd.id')
          ->where('Sites.id > 0');

      if($params['country'] != '-1')
          $builder->andWhere('Sites.country = :country:', ['country' => $params['country']]);

      if($params['city'] != '-1')
        $builder->andWhere('Sites.city = :city:', ['city' => $params['city']]);

      if($params['vertical'] != '-1')
          $builder->andWhere('Sites.vertical = :vertical:', ['vertical' => $params['vertical']]);

      if($params['imported'] != '-1')
          $builder->andWhere('Sites.imported = :imported:', ['imported' => $params['imported']]);

      if($params['tsu_from'] != '-1') {
          $tsu_ids = TechSpendUsd::getIdsFromValue($params['tsu_from']);
          $builder->inWhere('Sites.tech_spend_usd', $tsu_ids);
      }

      if($params['tsu_to'] != '-1') {
          $tsu_ids = TechSpendUsd::getIdsToValue($params['tsu_to']);
          $builder->inWhere('Sites.tech_spend_usd', $tsu_ids);
      }

      $paginator = new QueryBuilder([
          'builder' => $builder,
          'limit' => 10,
          'page' => $params['page']
      ]);
      $sites = $paginator->getPaginate();

      foreach($sites->items as $item) {
          $people[] = People::find([
              'domain_id = :domain__id:',
              'bind' => ['domain__id' => $item['id']]
          ])->toArray();

          $vertical[] = Vertical::findFirst([
              'id = :id:',
              'bind' => ['id' => $item['vertical_id']]
          ])->value;

          $countries[] = Countries::findFirst([
              'id = :id:',
              'bind' => ['id' => $item['country_id']]
          ])->name;
      }

      $aafbTags = AafbTags::find();

      $this->view->setVars([
          'sites' => $sites,
          'people' => $people,
          'vertical' => $vertical,
          'countries' => $countries,
          'all_countries' => Countries::find()->toArray(),
          'cities_of_country' => ($params['country'] != '-1') ? Sites::getCitiesByCountry($params['country'])->toArray() : [],
          'all_vertical' => Vertical::find()->toArray(),
          'all_tech_spend_usd' => TechSpendUsd::find(['order' => 'value'])->toArray(),
          'params' => $params,
          'searches' => Searches::find()->toArray(),
          'tags' => $aafbTags->toArray()
      ]);
  }
  public function createTagAction(){
    $AafbTagModel = new AafbTags();
    $AafbTagModel->tag_name = $this->request->getPost('name');
    $AafbTagModel->country = $this->request->getPost('country');
    $AafbTagModel->vertical = $this->request->getPost('vertical');
    $AafbTagModel->tsu_from = $this->request->getPost('tsu_from');
    $AafbTagModel->tsu_to = $this->request->getPost('tsu_to');
    $AafbTagModel->imported = $this->request->getPost('imported');
    $AafbTagModel->save();
    echo $AafbTagModel->tag_id;
  }
  public function deleteTagAction(){
    $tag_id = $this->request->getPost('tag_id');
    $tagToDelete = AafbTags::findFirst([
      'tag_id = :id:',
      'bind' => ['id' => $tag_id]
    ]);
    $tagToDelete->delete();
  }
  public function saveSearchAction()
  {
      if ($this->request->isPost()) {
          $search = new Searches();
          $search->country = $this->request->getPost('country', 'int', -1);
          $search->vertical = $this->request->getPost('vertical', 'int', -1);
          $search->tsu_from = $this->request->getPost('tsu_from', 'int', -1);
          $search->tsu_to = $this->request->getPost('tsu_to', 'int', -1);
          $search->imported = $this->request->getPost('imported', 'int', -1);
          $search->page = $this->request->getPost('page', 'int', 1);
          $search->name = $this->request->getPost('search_name', 'string', 'unnamed_search');

          if($search->save())
              return json_encode(true);
      }

      return json_encode(false);
  }

  public function loadSearchAction()
  {
      if ($this->request->isPost()) {
          $search_id = $this->request->getPost('search_id', 'int');

          $search = Searches::findFirst([
              'id = :id:',
              'bind' => ['id' => $search_id]
          ])->toArray();

          return json_encode($search);
      }

      return json_encode(false);
  }

  public function importToIntercomAction()
  {
      $result = [
          'success' => true,
          'messages' => []
      ];

      if ($this->request->isPost()) {
          $people_ids = $this->request->getPost('ids');
          $people_count = count($people_ids);

          if(!empty($people_ids)) {
              $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);

              foreach($people_ids as $id) {
                $builder = $this->modelsManager->createBuilder()
                  ->columns([
                    'Sites.domain', 'People.name', 'People.email', 'People.title', 'Sites.telephones',
                    'Countries.name AS country', 'Sites.state', 'Sites.city', 'Vertical.value as vertical'
                  ])
                  ->from('People')
                  ->join('Sites', 'People.domain_id = Sites.id')
                  ->join('Countries', 'Sites.country = Countries.id')
                  ->join('Vertical', 'Sites.vertical = Vertical.id')
                  ->where('People.id = :id:', ['id' => $id])
                  ->getQuery();
                $data = $builder->execute()->toArray();

                try {
                  $intercom_client = $this->checkIntercomClient($intercom, $data[0]['email'], $data[0]['domain']);

                  if(!$intercom_client) {
                    $location = ($data[0]['city'] != '') ? $data[0]['city'] . ', ' : '';
                    $location .= ($data[0]['state'] != '') ? $data[0]['state'] . ', ' : '';
                    $location .= $data[0]['country'];

                    $intercom->users->create([
                      'name' => $data[0]['name'],
                      'email' => $data[0]['email'],
                      'phone' => $data[0]['telephones'],
                      'custom_attributes' => [
                        'job_title' => $data[0]['title'],
                        'Website' => $data[0]['domain'],
                        'Vertical' => $data[0]['vertical'],
                        'Location' => $location,
                        'User Type' => 'Lead'
                      ]
                    ]);
                  }
                  else {
                    $people_count--;
                    $result['messages'][] = $data[0]['email'] . ' already exists in Intercom';
                  }
                }
                catch (GuzzleException $ex) {
                  $result['messages'][] = $ex->getMessage();
                  return json_encode($result);
                }

                $person = People::findFirst([
                  'id = :id:',
                  'bind' => ['id' => $id]
                ]);
                $person->imported = '1';

                $site = Sites::findFirst([
                  'id = :id:',
                  'bind' => ['id' => $person->domain_id]
                ]);
                $site->imported = '1';
                $person->save();
                $site->save();
              }
              $result['messages'][] = $people_count . ' people successfully imported to Intercom';

              return json_encode($result);
          }
          else {
              $result['success'] = false;
              $result['messages'][] = 'Input data can not be empty';

              return json_encode($result);
          }
      }
      else {
        $result['success'] = false;
        $result['messages'][] = 'Must be POST request';
      }

      return json_encode($result);
  }

  public function citiesAction()
  {
    if ($this->request->isAjax()) {
      $countryId = (int)$this->getParam('country_id', -1);
      $cities = new stdClass();

      if ($countryId != -1) {
        $cities = Sites::getCitiesByCountry($countryId);
      }

      return json_encode($cities);
    }

    exit(0);
  }

  private function checkIntercomClient(IntercomClient $intercom, $email, $domain) {
    try {
      $user = $intercom->users->getUser('', ['email' => $email]);
      return true;
    }
    catch (GuzzleException $ex) {
    }

    $shop = Shops::findFirst([
      'domain = :domain:',
      'bind' => ['domain' => $domain]
    ]);
    if($shop)
      return true;

    return false;
  }
}