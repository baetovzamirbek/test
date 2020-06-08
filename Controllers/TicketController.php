<?php

use Intercom\IntercomClient;
use Phalcon\Mvc\View\Simple as View;

class TicketController extends AbstractController
{
  public $supporters = [
    48 => 'kalys',
    36 => 'nurila',
    43 => 'nargiz',
    55 => 'azem',
    37 => 'tursunai',
    63 => 'timur',
    65 => 'alice',
    71 => 'anton',
    97 => 'aida',
    76 => 'aibek',
    88 => 'tina',
    108 => 'sales',
    89 => 'aiza'
  ];

  /**
   * @var TicketConversations
   */
  public $ticketConversations;

  protected $sortFilterOptions = [
    'by_plan' => 'Sort by plan',
    'enterprises' => 'Enterprise',
    'urgent' => 'Urgent',
    'highs' => 'Highs',
    'leads' => 'Leads',
    'lows' => 'Lows'
  ];

  public function initialize()
  {
    $this->ticketConversations = new TicketConversations();
  }

  public function indexAction()
  {
    $this->view->setVar("pageTitle", "CRM Tickets");
    $viewerId = Core::getViewerId();
    $ticketsStatistics = [];

    $beginningOfInterval = date('Y-m-d', strtotime('-30 day')) . ' 00:00:00';
    $endOfInterval = date('Y-m-d', strtotime('yesterday')) . ' 23:59:59';
    $interimTicketsCount = $this->getAssignsLogsModel()->getInterimTicketsCount($viewerId, $beginningOfInterval, $endOfInterval);

    $params = array(
      'page' => $this->getParam('page', 1),
      'ipp' => 20,
      'assign' => $this->getParam('assign', $viewerId),
      'status' => $this->getParam('status', ''),
      'keyword' => $this->getParam('keyword', ''),
      'sort-filter' => $this->getParam('sort-filter', false),
    );
    $page_type = 'all';
    if ($params['assign'] == $viewerId) {
      $this->view->setVar("pageTitle", "CRM Tickets - My");
      $page_type = "my";
      $ticketsStatistics = $this->getAssignsLogsModel()->getTicketsStatistics($viewerId);

    } elseif ($params['assign'] == 'unassigned') {
      $this->view->setVar("pageTitle", "CRM Tickets - Unassigned");
      $page_type = 'unassigned';
    }
    $developers = User::find("department = 'development' AND user_id != {$viewerId} AND status = 1");
    $supporters = User::find(array("department = 'support' AND status = 1"));

    $paginator = Ticket::getTicketsPaginator($params);
    $selectedTickets = $paginator->items;

    if (!in_array($params['assign'], ['all', 'unassigned']) && $params['sort-filter'] ||
      $params['status'] == 'open' && $params['sort-filter']) {
      $selectedTickets = Ticket::getTicketsByParams($params);
    }

    $collectedIds = Ticket::collectIds($selectedTickets);

    if (count($collectedIds['client_ids']) > 0) {
      Ticket::preparePriorities($collectedIds['client_ids']);
    }

    $tickets = array();
    if (count($collectedIds['ticket_ids']) > 0) {
      $ticketList = Ticket::query()->inWhere('ticket_id', $collectedIds['ticket_ids'])->execute();
      foreach ($ticketList as $ticket) {
        $tickets[$ticket->ticket_id] = $ticket;
      }
      // prepare ticket post counts
      Ticket::preparePostCounts($collectedIds['ticket_ids']);
    }

    $priority = Ticket::priority($selectedTickets, false);

    if (!in_array($params['assign'], ['all', 'unassigned']) && $params['sort-filter'] ||
      $params['status'] == 'open' && $params['sort-filter']) {
      $page = isset($params['page']) ? intval($params['page']) : 1;
      $paginator = Ticket::sortOrFilterByPlan($selectedTickets, $priority, $page, $params['sort-filter']);
    }

    $this->view->setVars(array(
      'priority_ticket' => $priority,
      'page_type' => $page_type,
      'paginator' => $paginator,
      'developers' => $developers,
      'supporters' => $supporters,
      'params' => $params,
      'viewerId' => $viewerId,
      'tickets' => $tickets,
      'order' => $this->getParam('keyword', 'sort_by_priority'),
      'ticketsStatistics' => $ticketsStatistics,
      'closedTicketsCount' => $this->getModel()->getCountByStatus('closed'),
      'openedTicketsCount' => $this->getModel()->getCountByStatus('open'),
      'interimTicketsCount' => $interimTicketsCount,
      'sortFilterOptions' => $this->sortFilterOptions,
    ));
  }

  public function viewAction()
  {
    $ticket_id = $this->getParam('id');
    if (!$ticket_id) {
      return $this->response->redirect('tickets');
    }

    $ticket = Ticket::findFirst('ticket_id=' . $ticket_id);
    if (!$ticket) {
      return $this->response->redirect('tickets');
    }

    $viewer_id = Core::getViewerId();

    $ticket_tags = TicketTags::getTags($ticket_id);

    $page_type = "my";

    if ((isset($_REQUEST['user_for_post_email'])) || ($viewer_id == 5) || ($viewer_id == 26) || ($viewer_id == 28) || (array_key_exists($viewer_id, $this->supporters))
      || (isset($_REQUEST['user_for_post_email']) && in_array($_REQUEST['user_for_post_email'], $this->supporters))) {
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'eldar_galiev' || $viewer_id == 5) {
        $viewer_id = 5;
        $user_for_post_email = "eldar_galiev";
      }
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'kalys_salmakbaev' || ($viewer_id == 26)) {
        $viewer_id = 26;
        $user_for_post_email = "kalys_salmakbaev";
      }
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'aiza_kupueva' || ($viewer_id == 28)) {
        $viewer_id = 28;
        $user_for_post_email = "aiza_kupueva";
      }
      if (array_key_exists($viewer_id, $this->supporters)) {
        $user_for_post_email = $this->supporters[$viewer_id];
      }
      if (isset($_REQUEST['user_for_post_email']) && in_array($_REQUEST['user_for_post_email'], $this->supporters)) {
        $viewer_id = array_search($_REQUEST['user_for_post_email'], $this->supporters);
        $user_for_post_email = $_REQUEST['user_for_post_email'];
      }
    } else {
      if (isset($_REQUEST['user_for_post_email'])) {
        if ($_REQUEST['user_for_post_email'] == "eldar_galiev") {
          $viewer_id = 5;
          $user_for_post_email = "eldar_galiev";
        }
        if ($_REQUEST['user_for_post_email'] == "kalys_salmakbaev") {
          $viewer_id = 26;
          $user_for_post_email = "kalys_salmakbaev";
        }

        if ($_REQUEST['user_for_post_email'] == "azem") {
          $viewer_id = 55;
          $user_for_post_email = "azem";
        }

        if ($_REQUEST['user_for_post_email'] == "nurila") {
          $viewer_id = 36;
          $user_for_post_email = "nurila";
        }

        if ($_REQUEST['user_for_post_email'] == "nargiz") {
          $viewer_id = 43;
          $user_for_post_email = "nargiz";
        }

        if ($_REQUEST['user_for_post_email'] == "kalys") {
          $viewer_id = 48;
          $user_for_post_email = "kalys";
        }
        if ($_REQUEST['user_for_post_email'] == "tursunai") {
          $viewer_id = 37;
          $user_for_post_email = "tursunai";
        }
        if ($_REQUEST['user_for_post_email'] == "timur") {
          $viewer_id = 63;
          $user_for_post_email = "timur";
        }
        if ($_REQUEST['user_for_post_email'] == "alice") {
          $viewer_id = 65;
          $user_for_post_email = "alice";
        }
        if ($_REQUEST['user_for_post_email'] == "anton") {
          $viewer_id = 71;
          $user_for_post_email = "anton";
        }
        if ($_REQUEST['user_for_post_email'] == "aida") {
          $viewer_id = 97;
          $user_for_post_email = "aida";
        }
        if ($_REQUEST['user_for_post_email'] == "aibek") {
          $viewer_id = 76;
          $user_for_post_email = "aibek";
        }
        if ($_REQUEST['user_for_post_email'] == "tina") {
          $viewer_id = 88;
          $user_for_post_email = "tina";
        }
        if ($_REQUEST['user_for_post_email'] == "sales") {
          $viewer_id = 108;
          $user_for_post_email = "sales";
        }
        if ($_REQUEST['user_for_post_email'] == "aiza") {
          $viewer_id = 89;
          $user_for_post_email = "aiza";
        }

      } else {
        $viewer_id = 26;
        $user_for_post_email = "kalys_salmakbaev";
      }
    }

    $viewer = User::findFirst($viewer_id);
    $this->view->setVar('viewer', $viewer);
    $this->view->setVar("pageTitle", "CRM Ticket - " . $ticket->subject);


    if ($this->request->isPost()) {
      $post_data = $this->request->getPost();
//      $ticket->app = (isset($post_data['ticket-instagram']) && $post_data['ticket-instagram']) ? 'instagram' : 'default';
      $ticket->app = (isset($post_data['ticket-instagram']) && $post_data['ticket-instagram']) ? $post_data['ticket-instagram'] : 'default';

      $copies = null;

      if (!isset($post_data['cc_emails'])) {
        $post_data['cc_emails'] = [];
      } else {
        $copies = json_encode($post_data['cc_emails']);
      }

      $data = array(
        'ticket_id' => $ticket->ticket_id,
        'text' => $this->cleanUrlCustom(Core::txt2link($post_data['post-text'])),
        'staff_id' => Core::getViewerId(),
        'type' => $post_data['type'],
        'creation_date' => date('Y-m-d H:i:s'),
        'subject' => 'Re: ' . $ticket->getLastSubject(),
        'from_id' => $viewer_id,
        'copies' => $copies,
      );
      if (isset($post_data['ticket-close']) && $post_data['ticket-close'] == 1) {
        $ticket->status = 'closed';
        $assigns = Assigns::find('ticket_id = ' . $ticket->ticket_id);

        $log_assigns = $assigns->toArray();
        foreach ($log_assigns as $log) {
          $remove_assign = new AssignsLogs();
          $remove_assign->RemoveTicketLogs($log['ticket_id'], $log['staff_id']);
        }

        foreach ($assigns as $assign) {
          $assign->delete();
        }

        //ticket logsk
        $ticket_logs = new TicketLogs();
        $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);

        $ticket->save();
        $this->ticketConversations->createConversation($ticket);
      }
      $post = new Post();

      if (!$post->save($data)) {
        print_die($post->getMessages());
      }

      if (isset($post_data['files']) && count($post_data['files']) > 0) {
        foreach ($post_data['files'] as $file_id) {
          $file = File::findFirst($file_id);
          $file->parent_id = $post->post_id;
          $file->parent_type = 'post';

          if ($file->type === 'attachment') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file->type = finfo_file($finfo, DOCROOT . $file->path);
          }

          $file->save();
        }
      }
      if ($post_data['type'] == 'team') {
        $files = $post->getFiles();
        $posts = Post::find(array(
          "ticket_id={$ticket->ticket_id} AND type <> 'private'",
          'order' => 'post_id ASC'
        ));

        $client = $ticket->getClient();

        if ($ticket->app == 'instagram') {
          $message_id = Mail::sendHTML($viewer, $client, $ticket->subject, $post->text, $files, $post_data['cc_emails'], $ticket->getReplyMessageId($post->post_id), $posts, "insta@growave.io");
        } elseif ($ticket->app == 'default') {
          $message_id = Mail::sendHTML($viewer, $client, $ticket->subject, $post->text, $files, $post_data['cc_emails'], $ticket->getReplyMessageId($post->post_id), $posts);
        } else {
          $message_id = Mail::sendFromEmaORNicoleHTML(ucfirst($ticket->app), $viewer, $client, $ticket->subject, $post->text, $files, $post_data['cc_emails'], $ticket->getReplyMessageId($post->post_id), $posts);
        }

        $postAutoReplyIntercom = 'N';
        if (strpos($ticket->subject, 'Growave Integration') !== false && $ticket->ticket_type == 'default' && isset($post_data['send-in-intercom']) && $post_data['send-in-intercom'] == 1) {
          try {
            $intercom = new IntercomClient('dG9rOjkwZTljYWM4XzBkN2FfNGVlMl9hZGMzXzVkZTdlNGY4MzAzNDoxOjA=', null);
            $user = $intercom->users->getUser("", ["email" => $client->email]);

            $result = $intercom->conversations->replyToLastConversation([
              "intercom_user_id" => $user->id,
              "body" => $post->text,
              "type" => "admin",
              "admin_id" => "1846591",
              "message_type" => "comment"
            ]);
          } catch (Exception $e) {
            $result = $e->getMessage();
          }

          if (is_object($result)) {
            $postAutoReplyIntercom = 'Y';
          }
        }

        $post->message_id = $message_id;
        $post->auto_reply_intercom = $postAutoReplyIntercom;
        $post->save();
      }


      $redirect_url = $this->url->get(array('for' => 'ticket', 'id' => $ticket_id));
      $redirect_url .= ($this->getParam('page', 1) > 1) ? '?page=' . $this->getParam('page', 1) : '';
      return $this->response->redirect($redirect_url, true);
    }
    $assigns = array();
    $a = Assigns::find('ticket_id = ' . $ticket_id);
    foreach ($a as $s) {
      $assigns[] = $s->staff_id;
    }
    $assigns = implode(',', $assigns);

    $page = $this->getParam('page', 1);
    $ipp = 50;


    $posts = $ticket->getPostsPaginator(array(
      'page' => $page,
      'ipp' => $ipp
    ));

    $posts = $posts->getPaginate();

    //********************************************* COMBINING POSTS
    $redesigned_posts = [];
    $prev = null;
    $i = 0;
    $all_posts_ids = [];

    foreach ($posts->items as $item) {
      $all_posts_ids[] = $item->p->post_id;
      if ($prev == null) {
        $prev = $item;
        $redesigned_posts[$i]['post_id'] = $redesigned_posts[$i]['posts_ids'][] = $item->p->post_id;
        $redesigned_posts[$i]['creation_date'] = $item->p->creation_date;
        $redesigned_posts[$i]['type'] = $item->p->type;
        $redesigned_posts[$i]['staff_id'] = $item->p->staff_id;
        $redesigned_posts[$i]['from_id'] = $item->p->from_id;
        $redesigned_posts[$i]['avatar_path'] = $item->avatar_path;
        $redesigned_posts[$i]['full_name'] = $item->full_name;
        $redesigned_posts[$i]['text'] = $item->p->text;
        $redesigned_posts[$i]['copies'] = $prev->p->copies;
        $redesigned_posts[$i]['from_intercom'] = $prev->p->from_intercom;
        $redesigned_posts[$i]['auto_reply_intercom'] = $item->p->auto_reply_intercom;
        continue;
      }

      $same_type = ($item->p->type == $prev->p->type) ? true : false;
      $same_time = (abs(strtotime($item->p->creation_date) - strtotime($prev->p->creation_date)) < 1200) ? true : false;
      $same_user = ($item->p->staff_id == $prev->p->staff_id) ? true : false;
      $same_from_id = ($item->p->from_id == $prev->p->from_id) ? true : false;
      $from_intercom = $item->p->from_intercom == $prev->p->from_intercom;

      if ($same_type && $same_time && $same_user && $same_from_id && $from_intercom) {
        if ($prev->p->text == $item->p->text) {
          continue;
        }
        if ($item->p->type != 'client')
          $redesigned_posts[$i]['text'] .= '<hr class="post-divider" />';
        $redesigned_posts[$i]['text'] .= "\n" . $item->p->text;
        $redesigned_posts[$i]['posts_ids'][] = $item->p->post_id;
      } else {
        $i++;
        $redesigned_posts[$i]['post_id'] = $redesigned_posts[$i]['posts_ids'][] = $item->p->post_id;
        $redesigned_posts[$i]['creation_date'] = $item->p->creation_date;
        $redesigned_posts[$i]['type'] = $item->p->type;
        $redesigned_posts[$i]['staff_id'] = $item->p->staff_id;
        $redesigned_posts[$i]['from_id'] = $item->p->from_id;
        $redesigned_posts[$i]['avatar_path'] = $item->avatar_path;
        $redesigned_posts[$i]['full_name'] = $item->full_name;
        $redesigned_posts[$i]['text'] = $item->p->text;
        $redesigned_posts[$i]['copies'] = $item->p->copies;
        $redesigned_posts[$i]['from_intercom'] = $prev->p->from_intercom;
        $redesigned_posts[$i]['auto_reply_intercom'] = $item->p->auto_reply_intercom;
//        $redesigned_posts[$i]['text'] = preg_replace('"\b(https?://\S+)"', '<a href="$1">Link</a>', $item->p->text);
      }
      $prev = $item;
    }
    //********************************************* COMBINING POSTS

    $assignUsers = false;
    if ($assigns)
      $assignUsers = User::find("user_id IN ({$assigns})");

    $shopsInfo = array();
    $client = $ticket->getClient();
    if (!$client) {
      return $this->response->redirect('tickets');
    }
    $sites = $client->getShops();

    $reviews = [];

    $startup_edition = false;
    foreach ($sites as $site) {
      $startup_edition = false;
      $shop = Shops::findFirst('shop_id=' . $site->shop_id);
      $client = SswClients::findFirst($shop->sswclient_id);
      $apps = SswClientApp::find(array("client_id = {$shop->sswclient_id}", 'order' => "status DESC, IF (app = 'default', 0, 1)"));

      if (empty($reviews)) {
        $AppReviews = AppReviews::find([
          'sswclient_id = :client_id:',
          'bind' => ['client_id' => $shop->sswclient_id]
        ]);
        if (count($AppReviews)) {
          $reviewsList = $AppReviews->toArray();
          foreach ($reviewsList as $review) {
            $review['shop_id'] = $site->shop_id;
            $reviews[] = $review;
          }
        };
      }

      $package_ids = array(0);
      $application_names = array(0);
      foreach ($apps as $app) {
        $package_ids[] = $app->package_id;
        $application_names[] = $app->app;


      }

      $package_ids_str = implode(',', $package_ids);
      $applications_str = "'" . implode("','", $application_names) . "'";

      /** @var  $db \Phalcon\Db\Adapter\Pdo\Mysql */
      $db = $this->di->get('ssw_database');
      $lastPlans = array();
      $sql = "SELECT s1.app, p.title FROM shopify_ssw_subscription s1
LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
INNER JOIN shopify_ssw_package p ON s1.package_id = p.package_id
WHERE s1.status <> 'pending' AND s1.client_id = {$shop->sswclient_id}"; //s2.subscription_id IS NULL AND
      $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      foreach ($rows as $row) {
        $lastPlans[$row['app']] = $row['title'];
      }


      $sql = "SELECT * FROM shopify_ssw_subscription WHERE client_id = {$shop->sswclient_id} AND status = 'active'  AND char_length(charge_id) >= 7";
      $subscriptions = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $charged = array();
      foreach ($subscriptions as $subscription) {
        $charged[$subscription['app']] = $subscription;
      }

      $sql = "SELECT package_id, title FROM shopify_ssw_package WHERE package_id IN ($package_ids_str)";
      $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $packages = array('Free');
      foreach ($packageList as $package) {
        $packages[$package['package_id']] = $package['title'];
      }

      $sql = "SELECT name, title FROM shopify_ssw_app WHERE name IN ($applications_str)";
      $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $applications = array();
      foreach ($appList as $appInfo) {
        $applications[$appInfo['name']] = $appInfo['title'];
      }

      $sql = "SELECT charge_id, client_id FROM crm_epic_testmode WHERE client_id = {$shop->sswclient_id}";
      $row = $db->fetchOne($sql, Phalcon\Db::FETCH_ASSOC);
      $testMode = $row ? true : false;

      if ($client && $client->ssw && $client->package_id == 3) {
        if (SswRequests::findFirst([
          'conditions' => 'website = ?0 AND status = ?1 AND package_id = ?2',
          'bind' => [$client->shop, 'installed', $client->package_id]
        ])) {
          $startup_edition = true;
        }
      }

      $app = $apps->getFirst();
      $isFreeGrowingBusiness = false;
      if ($app && !empty($charged[$app->app])) {
        $isFreeGrowingBusiness = $charged[$app->app]['package_id'] == 3 &&
          $charged[$app->app]['charge_id'] == 1111111 &&
          $charged[$app->app]['status'] === 'active';
        if ($isFreeGrowingBusiness) {
          $diffInSeconds = strtotime($charged[$app->app]['expiration_date']) - time(); // 86400 seconds === 1 day
          $diffInDays = $diffInSeconds > 86400 ? round($diffInSeconds / 86400) . ' days trial left' : 'last day';
          $packages[$app->package_id] = 'Free Growing Business (' . $diffInDays . ')';
        }
      }

      if (count($sites) == 1) {
        $one_shop_id = $sites[0]['shop_id'];
        $this->view->setVar('shop_id', $one_shop_id);

        $ticket_info_model = new TicketInfo();
        $info = $ticket_info_model->updateInfo($one_shop_id);

        $this->view->info = $ticket_info_model->updateInfo($one_shop_id);
        $this->view->setVar('attributes_info', $info);
        $this->view->setVar('send_info', 1);
      }

      $shopsInfo[$site->shop_id] = array(
        'ssw' => $client && $client->ssw,
        'isFreeGB' => $isFreeGrowingBusiness,
        'id' => $site->shop_id,
        'apps' => $apps->toArray(),
        'charged' => $charged,
        'url' => $site->url,
        'packages' => $packages,
        'lastPlans' => $lastPlans,
        'is_startup' => $startup_edition,
        'applications' => $applications,
        'currentShop' => $shop,
      );
    }

    $users_for_full_name = User::find();

    $assign_logs = new AssignsLogs();
    $logs = $assign_logs->getLogsPaginator($ticket_id, $redesigned_posts, $all_posts_ids);

    $client = Client::getClientby($ticket->client_id)->toArray();

    $users = User::find('status = 1');
    $developers_duty = Ticket::getdevelopersDutyWorkDays($users);
    $developers = Purpose::getDevelopersIds($users, $developers_duty);

    /* Автоматическое направление удалённых тикетов */
    $checkDeletedSites = 1; //проверка всех сайтов. если наш апп удалили = 1
    if (count($sites) > 0) {
      foreach ($apps as $app) {
        if($app->toArray()['status']) {
          $checkDeletedSites = 0;
        }
      }
      if( $checkDeletedSites == 1 and $ticket->status == 'open' and $assignUsers->toArray()[0]['user_id'] == 64) {
        $logs=array_reverse($logs);
        foreach ($logs as $log){
          if($log['staff_id'] <> 64){
            $user_id=$log['staff_id'];
            break;
          }
        }
        Assigns::setAssignUser($ticket->ticket_id, [$user_id] , 64);
      }
    }

    $dutyDeveloper = $this->getNightDutyDeveloper();
    $options = array(
      'ticket_tags' => $ticket_tags,
      'page_type' => $page_type,
      'client' => $client,
      'logs' => $logs,
      'is_startup' => $startup_edition,
      'ticket' => $ticket,
      'posts' => $posts,
      'redesigned_posts' => $redesigned_posts,
      'users_for_full_name' => $users_for_full_name,
      'assigns' => $assigns,
      'assignUsers' => $assignUsers,
      'viewer' => User::findFirst(Core::getViewerId()),
      'users' => $users,
      'developers' => json_encode($developers),
      'File' => new File(),
      'shopsInfo' => $shopsInfo,
      'developers_duty' => $developers_duty,
      'packages' => [
        'default' => SswPackages::find('app="default" AND enabled = 1 AND type <> "free"'),
        'instagram' => SswPackages::find('app="instagram" AND enabled = 1 AND type <> "free"'),
      ],
      'dutyDeveloperId' => $dutyDeveloper,
    );

    if (isset($user_for_post_email)) {
      $options['user_for_post_email'] = $user_for_post_email;
    }
    $reviews = $this->removeDuplicates($reviews, ['body']);

    $this->view->setVar('reviews', $reviews);
    $this->view->setVars($options);
  }

  public function removeDuplicates(array $input, array $unique_fields)
  {
    if (count($input) <= 1) {
      return $input;
    }
    $prevElement = null;
    $toDelete = [];
    foreach ($input as $idx => $element) {
      if (!is_array($element)) {
        $input = array_unique($input);
        break;
      }
      if (empty($prevElement)) {
        $prevElement = ['idx' => $idx];
        foreach ($unique_fields as $field) {
          $prevElement[$field] = $element[$field];
        }
        continue;
      }
      foreach ($unique_fields as $field) {
        if ($prevElement[$field] === $element[$field]) {
          $toDelete[$idx] = $prevElement['idx'];
          continue;
        }
        $prevElement[$field] = $element[$field];
      }
      $prevElement['idx'] = $idx;
    }
    if (!empty($toDelete)) {
      foreach ($toDelete as $idx) {
        unset($input[$idx]);
      }
    }
    return $input;
  }

  public function view2Action()
  {
    $ticket_id = $this->getParam('id');

    if (!$ticket_id) {
      return $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'ticket', 'action' => 'index')), true);
    }

    $ticket = Ticket::findFirst('ticket_id=' . $ticket_id);

    if (!$ticket) {
      return $this->response->redirect($this->url->get(array('for' => 'default', 'controller' => 'ticket', 'action' => 'index')), true);
    }

    $viewer_id = Core::getViewerId();

    if ((isset($_REQUEST['user_for_post_email'])) || ($viewer_id == 5) || ($viewer_id == 26) || ($viewer_id == 28)) {
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'eldar_galiev' || $viewer_id == 5) {
        $viewer_id = 5;
        $user_for_post_email = "eldar_galiev";
      }
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'kalys_salmakbaev' || ($viewer_id == 26)) {
        $viewer_id = 26;
        $user_for_post_email = "kalys_salmakbaev";
      }
      if (isset($_REQUEST['user_for_post_email']) && $_REQUEST['user_for_post_email'] == 'aiza_kupueva' || ($viewer_id == 28)) {
        $viewer_id = 28;
        $user_for_post_email = "aiza_kupueva";
      }
    } else {
      if (isset($_REQUEST['user_for_post_email'])) {
        if ($_REQUEST['user_for_post_email'] == "eldar_galiev") {
          $viewer_id = 5;
          $user_for_post_email = "eldar_galiev";
        }
        if ($_REQUEST['user_for_post_email'] == "kalys_salmakbaev") {
          $viewer_id = 26;
          $user_for_post_email = "kalys_salmakbaev";
        }
        if ($_REQUEST['user_for_post_email'] == "aiza_kupueva") {
          $viewer_id = 28;
          $user_for_post_email = "aiza_kupueva";
        }
      } else {
        $viewer_id = 26;
        $user_for_post_email = "kalys_salmakbaev";
      }
    }


    $viewer = User::findFirst($viewer_id);

    $this->view->setVar('viewer', $viewer);
    $this->view->setVar("pageTitle", "CRM Ticket - " . $ticket->subject);


    if ($this->request->isPost()) {
      $post_data = $this->request->getPost();
      //print_die($post_data['post-text']);
      $ticket->app = (isset($post_data['ticket-instagram']) && $post_data['ticket-instagram']) ? 'instagram' : 'default';

      $data = array(
        'ticket_id' => $ticket->ticket_id,
        'text' => Core::txt2link($post_data['post-text']),
        'staff_id' => Core::getViewerId(),
        'type' => $post_data['type'],
        'subject' => 'Re: ' . $ticket->getLastSubject(),
        'from_id' => $viewer_id,
      );
      if (isset($post_data['ticket-close']) && $post_data['ticket-close'] == 1) {
        $ticket->status = 'closed';
        $assigns = Assigns::find('ticket_id = ' . $ticket->ticket_id);

        $log_assigns = $assigns->toArray();
        foreach ($log_assigns as $log) {
          $remove_assign = new AssignsLogs();
          $remove_assign->RemoveTicketLogs($log['ticket_id'], $log['staff_id']);
        }

        foreach ($assigns as $assign) {
          $assign->delete();
        }
        $ticket->save();
      }
      $post = new Post();


      if (!$post->save($data)) {
        print_die($post->getMessages());
      }

      if (isset($post_data['files']) && count($post_data['files']) > 0) {
        foreach ($post_data['files'] as $file_id) {
          $file = File::findFirst($file_id);
          $file->parent_id = $post->post_id;
          $file->parent_type = 'post';

          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $file->type = finfo_file($finfo, DOCROOT . $file->path);

          $file->save();
        }
      }

      if ($post_data['type'] == 'team') {
        $files = $post->getFiles();
        $posts = Post::find(array(
          "ticket_id={$ticket->ticket_id} AND type <> 'private'",
          'order' => 'post_id ASC'
        ));

        if (!isset($post_data['cc_emails']))
          $post_data['cc_emails'] = array();

        $client = $ticket->getClient();

        if ($ticket->app == 'instagram') {
          $message_id = Mail::sendInstagramHTML($viewer, $client, $ticket->subject, $post->text, $files, $post_data['cc_emails'], $post_data['from_user'], $ticket->getReplyMessageId($post->post_id), $posts);
        } else {
          $message_id = Mail::sendHTML($viewer, $client, $ticket->subject, $post->text, $files, $post_data['cc_emails'], $ticket->getReplyMessageId($post->post_id), $posts);
        }

        $post->message_id = $message_id;
        $post->save();
      }


      $redirect_url = $this->url->get(array('for' => 'ticket2', 'id' => $ticket_id));
      $redirect_url .= ($this->getParam('page', 1) > 1) ? '?page=' . $this->getParam('page', 1) : '';
      return $this->response->redirect($redirect_url, true);
    }
    $assigns = array();
    $a = Assigns::find('ticket_id = ' . $ticket_id);
    foreach ($a as $s) {
      $assigns[] = $s->staff_id;
    }
    $assigns = implode(',', $assigns);

    $page = $this->getParam('page', 1);
    $ipp = 50;
    $posts = $ticket->getPostsPaginator(array(
      'page' => $page,
      'ipp' => $ipp
    ));
    $posts = $posts->getPaginate();

    $assignUsers = false;
    if ($assigns)
      $assignUsers = User::find("user_id IN ({$assigns})");

    $shopsInfo = array();
    $client = $ticket->getClient();
    $sites = $client->getShops();

    foreach ($sites as $site) {
      $shop = Shops::findFirst('shop_id=' . $site->shop_id);
      $client = SswClients::findFirst($shop->sswclient_id);
      $apps = SswClientApp::find(array("client_id = {$shop->sswclient_id}", 'order' => "status DESC, IF (app = 'default', 0, 1)"));


      $package_ids = array(0);
      $application_names = array(0);
      foreach ($apps as $app) {
        $package_ids[] = $app->package_id;
        $application_names[] = $app->app;


      }

      $package_ids_str = implode(',', $package_ids);
      $applications_str = "'" . implode("','", $application_names) . "'";

      /** @var  $db \Phalcon\Db\Adapter\Pdo\Mysql */
      $db = $this->di->get('ssw_database');
      $lastPlans = array();
      $sql = "SELECT s1.app, p.title FROM shopify_ssw_subscription s1
LEFT JOIN shopify_ssw_subscription s2 ON (s1.app = s2.app AND s1.client_id = s2.client_id AND s1.subscription_id < s2.subscription_id)
INNER JOIN shopify_ssw_package p ON s1.package_id = p.package_id
WHERE s1.status <> 'pending' AND s1.client_id = {$shop->sswclient_id}"; //s2.subscription_id IS NULL AND
      $rows = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      foreach ($rows as $row) {
        $lastPlans[$row['app']] = $row['title'];
      }


      $sql = "SELECT app FROM shopify_ssw_subscription WHERE client_id = {$shop->sswclient_id} AND status = 'active'  AND char_length(charge_id) >= 7";
      $subscriptions = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $charged = array();
      foreach ($subscriptions as $subscription) {
        $charged[$subscription['app']] = 1;
      }

      $sql = "SELECT package_id, title FROM shopify_ssw_package WHERE package_id IN ($package_ids_str)";
      $packageList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $packages = array('Free');
      foreach ($packageList as $package) {
        $packages[$package['package_id']] = $package['title'];
      }

      $sql = "SELECT name, title FROM shopify_ssw_app WHERE name IN ($applications_str)";
      $appList = $db->fetchAll($sql, Phalcon\Db::FETCH_ASSOC);
      $applications = array();
      foreach ($appList as $appInfo) {
        $applications[$appInfo['name']] = $appInfo['title'];
      }

      $sql = "SELECT charge_id, client_id FROM crm_epic_testmode WHERE client_id = {$shop->sswclient_id}";
      $row = $db->fetchOne($sql, Phalcon\Db::FETCH_ASSOC);
      $testMode = $row ? true : false;

      $shopsInfo[] = array(
        'id' => $site->shop_id,
        'apps' => $apps->toArray(),
        'charged' => $charged,
        'packages' => $packages,
        'lastPlans' => $lastPlans,
        'applications' => $applications
      );
    }


    $options = array(
      'ticket' => $ticket,
      'posts' => $posts,
      'assigns' => $assigns,
      'assignUsers' => $assignUsers,
      'viewer' => User::findFirst(Core::getViewerId()),
      'users' => User::find('status = 1'),
      'File' => new File(),
      'shopsInfo' => $shopsInfo
    );


    if (isset($user_for_post_email)) {
      $options['user_for_post_email'] = $user_for_post_email;
    }

    $this->view->setVars($options);
  }

  public function cleanUrlCustom($str)
  {
    $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';

    $txt = preg_replace_callback("#$pattern#i", function ($matches) {

      $res = str_replace('&nbsp', '', $matches[0]);
      $res = str_replace(' ', '', $res);
      return trim($res);

    }, $str);

    return $txt;
  }

  public function createAction()
  {

    $viewer_id = Core::getViewerId();
    $viewer = User::findFirst($viewer_id);
    $this->view->setVar("pageTitle", "CRM - New Ticket");
    $params = $this->dispatcher->getParams();
    $client_id = 0;
    if (isset($params[0]) && $params[0]) {
      $client_id = $params[0];
    }
    $client = Client::findFirst('client_id=' . $client_id);
    $this->view->setVars(array(
      'client_id' => $client_id,
      'client_name' => $client ? $client->name : '',
      'client_email' => $client ? $client->email : '',
      'subject' => '',
      'text' => '',
      'viewer' => $viewer
    ));

    $this->view->setVar('errors', array());
    $this->view->setVar('post_data', array());
    if (!$this->request->isPost()) {
      return;
    }

    $instagram_app = false;
    $post_data = $this->request->getPost();
    $isOnBoardingTicket = isset($post_data['template_type']) && $post_data['template_type'] == 'onboarding';
    if (isset($post_data['template_type'], $post_data['app'])) {
      $instagram_app = ($post_data['app'] == 'instagram');
      $ticketTemplate = Tickettemplates::findFirst(array(
        "conditions" => "type = ?0 AND app = ?1",
        "bind" => array($post_data['template_type'], $post_data['app'])
      ));
      if ($ticketTemplate) {
        $post_data['subject'] = $ticketTemplate->subject;
        $post_data['text'] = $ticketTemplate->html;
        if ($ticketTemplate->plain) {
          $post_data['text2'] = nl2br($ticketTemplate->plain);
        } else {
          $post_data['text'] = nl2br($post_data['text']);
        }
      }
    }

    $errors = array();
    if (!isset($post_data['subject']) || !$post_data['subject']) {
      $errors[] = 'Subject is required.';
    }

    if (!isset($post_data['client_id']) || $post_data['client_id'] == 0) {
      if ($post_data['client_email'] == '') {
        $errors[] = 'Client is required.';
      } else {
        $client = Client::findFirst(['conditions' => "email = ?0", 'bind' => [$post_data['client_email']]]);
        if (!$client)
          $client = Client::createProspectClient($post_data['client_email'], $post_data['client_name']);
        if ($client)
          $post_data['client_id'] = $client->client_id;
        else
          $errors[] = 'Client is required.';
      }

    }

    if (!isset($post_data['text']) || !$post_data['text']) {
      $errors[] = 'Message is required';
    }

    $ticket_type = 'default';
    if (isset($post_data['ticket_type'])) {
      $ticket_type = 'user_ticket';
    }

    if (count($errors) > 0) {
      $this->view->setVar('errors', $errors);
      $this->view->setVars($post_data);
      return;
    }

    if (!isset($post_data['type'])) {
      $post_data['type'] = 'private';
    }

    $ticket_status = 'closed';

    if ($post_data['type'] == 'private' || (isset($post_data['from_ssw']) && !isset($post_data['shop_tracking']))) {
      $ticket_status = 'open';
    }

    if ($post_data['subject'] === 'You are about to exceed your monthly limit') {
      $ticket_status = 'closed';
    }

    $instagram_app = $this->getParam('ticket-instagram', 'default');

    $ticket_data = array(
      'client_id' => $post_data['client_id'],
      'subject' => $post_data['subject'],
      'status' => $ticket_status,
      'app' => $instagram_app,
      'ticket_type' => $ticket_type,
      'priority' => 'low'
    );

    $ticket = new Ticket();
    if (!$ticket->save($ticket_data)) {
      $ticket_error_messages = $ticket->getMessages();
      print_slack('`=================================================================================================`', 'debug');
      print_slack('Unable to create a ticket', 'debug');
      print_slack($post_data, 'debug');
      print_slack($ticket_error_messages, 'debug');
      print_arr($post_data);
      print_die($ticket_error_messages);
    }

    //ticket logs
    $ticket_logs = new TicketLogs();
    $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);

    if (isset($post_data['from_ssw']) && $post_data['from_ssw']) {
      if (isset($post_data['shop_tracking'])) {
        $post_type = 'team';
      } else {
        $post_type = 'client';
      }
    } else {
      $post_type = $post_data['type'];
    }

    if ($isOnBoardingTicket) {
      $post_data['text'] = Mail::getTextWithValues($post_data['text'], $post_data);
      if (isset($post_data['text2'])) {
        $post_data['text2'] = Mail::getTextWithValues($post_data['text2'], $post_data);
      }
    }
    $text = isset($post_data['text2']) ? Core::txt2link($post_data['text2']) : Core::txt2link($post_data['text']);

    if (array_key_exists('ai_history_id', $post_data) && !empty($post_data['ai_history_id'])
      && array_key_exists('ssw_client_id', $post_data) && !empty($post_data['ssw_client_id'])) {
      $pattern = '#;history;#';
      $replacement = '<a href="/auth/redirectWithCode?url=https://ai.growave.io/client/'
        . $post_data['ssw_client_id'] . '/integrationResult/' . $post_data['ai_history_id'] . '">Auto integration history</a>';

      $text = preg_replace($pattern, $replacement, $text);
    }

    $data = array(
      'ticket_id' => $ticket->ticket_id,
      'text' => $text,
      'staff_id' => Core::getViewerId(),
      'type' => $post_type,
    );

    $post = new Post();
    if (!$post->save($data)) {
      $post_error_messages = $post->getMessages();
      print_slack('`=================================================================================================`', 'debug');
      print_slack('Unable to create a ticket post', 'debug');
      print_slack($post_data, 'debug');
      print_slack($post_error_messages, 'debug');
      print_die($post_error_messages);
    }

    //add tag integration
    if (isset($post_data['add_tag'])) {

      $ticket_tags = new TicketTags();

      $ticket_tag_arr = array(
        'ticket_id' => $ticket->ticket_id,
        'tag' => 'Integration'
      );

      if (!$ticket_tags->save($ticket_tag_arr)) {
        print_slack('error ticket tag', 'phptest');
        print_slack($ticket->ticket_id, 'phptest');
      }
    }

    if ($client->email !== $post_data['client_email']) {
      $client->email = $post_data['client_email'];
    }

    $files = isset($_FILES['files']) ? $_FILES['files'] : array();
    if ($post_type == 'team') {
      $client = $ticket->getClient();
      if (isset($ticketTemplate) && $ticketTemplate && !$isOnBoardingTicket) {
        $tplAutoEmails = array(
          "install" => "install",
          "uninstall" => "uninstall",
          "reinstall" => "reinstall",
          "uninstall_ssw" => "uninstallSSW",
          "uninstall_clean" => "uninstallClean",
          "not_finished" => "charge",
          "trial_expired" => "trialExpired",
          "noGallery" => "noGallery",
          "noIntegrated" => "noIntegrated"
        );
        if (isset($tplAutoEmails[$ticketTemplate->type])) {
          if ($ticketTemplate->app == 'instagram') {
            $data = array(
              'name' => $client->name,
              'email' => $client->email,
              'list' => 'rTZ7KJMt8C0h3tOknZOKEQ',
              'boolean' => 'true'
            );
            $aresData = array('email' => $client->email, 'listID' => 100);
          } else {
            $data = array(
              'name' => $client->name,
              'email' => $client->email,
              'list' => '4HwmBgCy0LI9763lHdz763p1fA',
              'boolean' => 'true'
            );
            $aresData = array('email' => $client->email, 'listID' => 101);
          }
          $auto_responder = $tplAutoEmails[$ticketTemplate->type];
          $data[$auto_responder] = 'yes';
          $data[$auto_responder . 'Fake'] = date('Y-m-d H:i:s');
          exit;

//          $result = $client->sendDataToSendySync('subscribe', $data); // https://trello.com/c/GmeE34Qo/582-pausing-sendy-emails
//          if (!$result) {
//            $data['error_message'] = "Subscribe failed!!!";
//            print_slack($data, 'pd');
//          }
//          $message_id = $client->sendDataToSendySync('myautoresponders', $aresData);
//          $message_id = trim($message_id) . '@email.amazonses.com';
//          if (!$message_id) {
//          }
        } else {
          $message_id = Mail::send($client, $ticket->subject, Core::txt2link($post_data['text']), $files);
        }
      } else {
        $ticket_app = $this->getParam('ticket-instagram', 'default');
        if (in_array($ticket_app, $this->supporters)) {
          $userENK = User::findFirst(array_search($ticket_app, $this->supporters));
          $message_id = Mail::sendHTML($userENK, $client, $ticket->subject, Core::txt2link($post_data['text']), $files);
        } else {

          $message_id = ($instagram_app)
            ? Mail::sendInstagramHTML($viewer, $client, $ticket->subject, Core::txt2link($post_data['text']), $post_data['from_user'], $files)
            : Mail::sendHTML($viewer, $client, $ticket->subject, Core::txt2link($post_data['text']), $files);
        }
      }
      $ticket->message_id = $message_id;
      $post->message_id = $message_id;
    }
    $ticket->save();
    $post->save();
    if (isset($_FILES['files']) && count($_FILES['files']) > 0) {
      $files = $_FILES['files'];
      foreach ($files['name'] as $key => $name) {
        $f = array(
          'name' => $name,
          'type' => $files['type'][$key],
          'tmp_name' => $files['tmp_name'][$key],
          'size' => $files['size'][$key],
        );

        $post->addFile($f, $ticket->client_id);
      }
    }

    // create shop staff if we know shop_id

    if (isset($post_data['client_id']) && $post_data['client_id']) {
      $client = Client::findFirst('client_id = ' . $post_data['client_id']);
    }
    if (isset($post_data['sswclient_id']) && $post_data['sswclient_id']) {
      $shop = Shops::findFirst('sswclient_id = ' . $post_data['sswclient_id']);

      if ($shop && isset($client) && $client) {
        Shopstaffs::createShopStaff($shop->shop_id, $client->client_id);
      }
    }

    // Auto reply
    if (!isset($client) || !$client) {
      $client = $ticket->getClient();
    }

    if ($client) {
      $isSend = false;
      $freePlanAiData = [];

      $clientAppIsDefault = SswClients::findFirst([
        'client_id = :client_id: and ssw = 1',
        'bind' => [
          'client_id' => $client->client_id
        ]
      ]);

      if (isset($shop) && $clientAppIsDefault) {
        $isSend = $this->sendAutoEmailForRequiredInfo($client, $shop, $post_data, $ticket, $viewer, $viewer_id, $files, $post);
      }
      if (array_key_exists('ai_data', $post_data) && !empty($post_data['ai_data'])) {
        $freePlanAiData = $post_data['ai_data'];
      }

      if (!$isSend) {
        $simpleView = new View();
        $simpleView->setDI($this->getDI());
        $simpleView->setViewsDir($this->view->getViewsDir());

        $ticket->autoReply('support@growave.io', $client->name, $post_data['client_email'], $simpleView, $freePlanAiData);
      }
    }

    $this->response->redirect($ticket->getHref(), true);
  }

  public function sendAutoEmailForRequiredInfo($client, $shop, $post_data, $ticket, $viewer, $viewer_id, $files, $post)
  {
    $error = false;
    $message_on = false;

    $lowercase_text = strtolower($post_data['text']);
    $search = 'pass';
    if (!preg_match("/{$search}/i", $lowercase_text)) {
      try {
        $api = new \Shopify\Client($shop);
        $shop_api_pass_info = $api->call('GET', "/admin/shop.json", array('fields' => 'password_enabled'));
        $shop->password_info = $shop_api_pass_info['password_enabled'];
        $this->shopException($shop->domain, $shop->password_info, '', 'password exception');
      } catch (Exception $e) {
        $error = true;
      }
      if ($shop->password_info != 1 && !$error) {
        try {
          $URL = $shop->domain;
          $URL = preg_replace("/^http:/i", "https:", $URL);
          if (strpos($URL, "https://") == false) {
            $ch = curl_init('https://' . $shop->domain . '/account/login');
          } else {
            $ch = curl_init($shop->domain . '/account/login');
          }
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_exec($ch);
          $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          $this->shopException($shop->domain, $shop->password_info, $http_code, 'before check http_code');

          if (!in_array($http_code, [500, 404, 200])) {
            $this->shopException($shop->domain, $shop->password_info, $http_code, 'after check http_code');
            $message_on = true;
            $message = "Hi there, {$client->name}
                  <br><br>
                  Hope you are in a good mood. :) 
                  <br><br>
                  I was just about to integrate our platform into your beautiful store, however, found out that the Shopify accounts are disabled and I’m not able to proceed. Could you please enable them? And I’ll integrate our apps as well as to adapt them to your site design in the best way!
                  <br><br>
                  Please go here-https://shopify.com/admin/settings/checkout 
                  Find Customer accounts field and enable `Accounts are optional` option.
                  <br><br>
                  Please let me know once it's done. I’m super excited to start working on your store!~
                  Cheers,<br>
                  Growave’s customer success team";
          }
        } catch (Exception $e) {
          $error = true;
          $this->shopException($shop->domain, $shop->password_info, '', 'error exception');
        }
      } else {
        $this->shopException($shop->domain, $shop->password_info, '', 'if isset shopify password');
        $message_on = true;
        $message = "Hi there, {$client->name}
                <br><br>
                Hope you are in a good mood. :) 
                <br><br>
                Thank you for your request! I just wanted to start the integration process, however, found out that your store is protected by a storefront password. I will be more than happy to integrate our products into your store and show you their advantages! So could you do me a little favor and provide a password?
                <br><br>
                You can find it here: https://shopify.com/admin/online_store/preferences or just disable storefront page. Thank you! I’m super excited to start working on your store! 
                <br><br>
                Cheers,<br>
                Growave’s customer success team";
      }
    }

    if ($message_on) {
      $posts = Post::find(array(
        "ticket_id={$ticket->ticket_id} AND type <> 'private'",
        'order' => 'post_id ASC'
      ));

      logException(new \Exception(json_encode(['client' => $client->name, 'email' => $client->email, 'subject' => $ticket->subject, 'body' => $message, 'info' => 'before send email to client'])));

      $message_id = Mail::sendHTML($viewer, $client, $ticket->subject, $message, $files, array(), $ticket->getReplyMessageId($post->post_id), $posts);
      $post->message_id = $message_id;
      $post->save();

      $data = array(
        'ticket_id' => $ticket->ticket_id,
        'text' => $message,
        'staff_id' => Core::getViewerId(),
        'type' => 'team',
        'subject' => 'Re: ' . $ticket->getLastSubject(),
        'from_id' => $viewer_id,
        'copies' => null,
      );
      $post = new Post();

      if (!$post->save($data)) {
        print_die($post->getMessages());
      }
    }

    return $message_on;
  }

  public function searchClientAction()
  {
    /** @var  $db \Phalcon\Db\Adapter\Pdo\Mysql */
    $db = $this->di->get('ssw_database');
    $search_by = $db->escapeIdentifier($this->getParam('by', 'name'));
    $search_by = trim($search_by, '`');
    $key_word = $this->getParam('query', '');

    $clients = Client::find([
      'conditions' => "$search_by LIKE :keyword:",
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

  public function searchTemplateAction()
  {
    $key_word = $this->getParam('query', '');
    $key_word = str_replace("'", "\'", $key_word);

    $templates = Template::find("keyword LIKE '%$key_word%' OR body LIKE '%$key_word%'");

    $response = array(
      'query' => $key_word,
      'suggestions' => array()
    );

    foreach ($templates as $template) {
      $response['suggestions'][] = array(
        'data' => nl2br($template->body),
        'value' => $template->keyword
      );
    }

    exit(json_encode($response));
  }

  public function createAjaxAction()
  {
    if (!$this->request->isAjax()) {
      exit(0);
    }

    $this->view->disable();
    $users = $this->getParam('users', []);
    $ticket_id = $this->getParam('ticket_id');
    $purpose = $this->getParam('purpose', false);

    if ($purpose != '0') {
      Purpose::setPurposeTicket($ticket_id, $purpose, $users);
    }
    $users = Assigns::setAssignUser($ticket_id, $users);

    exit(json_encode($users));
  }

  public function removeAssignAction()
  {
    $this->view->disable();
    $staff_id = $this->getParam('id');
    $ticket_id = $this->getParam('ticket_id');
    $assign = Assigns::findFirst("ticket_id={$ticket_id} AND staff_id={$staff_id}");
    if ($assign) {
      $assigns = Assigns::getAssignUserIds($ticket_id);
      NotificationService::ticketAssignmentChanged($ticket_id, $assigns);
      $assign->delete();

      $assigns_logs = new AssignsLogs();
      $assigns_logs->RemoveTicketLogs($ticket_id, $staff_id);

    }
    exit (json_encode(array('result' => 'success')));
  }

  public function changeStatusAction()
  {
    $ticket_id = $this->request->get('ticket_id');
    if ($ticket_id) {
      $ticket = Ticket::findFirst(intval($ticket_id));
      if ($ticket) {
        if ($ticket->status == 'closed') {
          $ticket->status = 'open';
          $response['delete'] = '0';
        } else {
          $ticket->status = 'closed';
          $this->ticketConversations->createConversation($ticket);
          $assigns = Assigns::find('ticket_id = ' . $ticket_id);

          $log_assigns = $assigns->toArray();
          foreach ($log_assigns as $log) {
            $remove_assign = new AssignsLogs();
            $remove_assign->RemoveTicketLogs($log['ticket_id'], $log['staff_id']);
          }

          foreach ($assigns as $assign) {
            $assign->delete();
          }
          $response['delete'] = '1';
        }

        //ticket logs
        $ticket_logs = new TicketLogs();
        $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);

        if ($ticket->save()) {
          $postForIntegration = Post::isLastPostForIntegration($ticket->ticket_id);

          if ($ticket->status == 'closed' and $ticket->subject == 'Growave Integration' and $ticket->ticket_type == 'default' and $postForIntegration) {
            $client = Client::findFirst($ticket->client_id);

            if ($client->email != 'farside312@gmail.com') {
              IntegratedLogs::createOrUpdate($ticket->client_id, $client->email, $client->name);
            }
          }

          $response ['result'] = 'Success';
        }
      }
      exit (json_encode($response));
    }
    exit (json_encode(array('error' => 'Ticket not found')));
  }

  public function deleteAction()
  {
    $ticket_id = intval($this->request->get('ticket_id'));
    if ($ticket_id) {
      $ticket = Ticket::findFirst(intval($ticket_id));
      if ($ticket) {
        $posts = Post::find("ticket_id={$ticket_id}");
        foreach ($posts as $post) {
          $post_id = $post->post_id;
          $files = File::find("parent_id={$post_id} AND parent_type = 'post'");
          foreach ($files as $file) {
            @unlink($file->path);
            $file->delete();
          }
          $post->delete();
        }
        $assigns = Assigns::find("ticket_id = {$ticket_id}");

        $log_assigns = $assigns->toArray();
        foreach ($log_assigns as $log) {
          $remove_assign = new AssignsLogs();
          $remove_assign->RemoveTicketLogs($log['ticket_id'], $log['staff_id']);
        }

        foreach ($assigns as $assign) {
          $assign->delete();
        }
        $notes = Notification::find("ticket_id={$ticket_id}");
        foreach ($notes as $note) {
          $note->delete();
        }
        $ticket->delete();
      }
    }
    exit (json_encode(array('result' => 'success')));
  }

  public function getPostBodyAction()
  {
    $post_id = $this->getParam('post_id');
    $response = array();
    if ($post_id) {
      $post = Post::findFirst(intval($post_id));
      if ($post) {
        $response['result'] = 'success';
        $response['body'] = nl2br($post->message_body);
      } else {
        $response['result'] = 'post with ' . $post_id . ' not found';
      }
    } else {
      $response['result'] = 'post_id not valid';
    }
    exit(json_encode($response));
  }

  public function editPostAction()
  {
    $post_id = $this->getParam('post_id');
    $post_body = $this->getParam('post_body');
    $response = array();
    if ($post_id) {
      $post = Post::findFirst(intval($post_id));
      if ($post) {
        $post->text = $post_body;
        $post->save();
        $response['result'] = 'success';
      } else {
        $response['result'] = 'error';
      }
    } else {
      $response['result'] = 'post_id not valid';
    }
    exit(json_encode($response));
  }

  public function getFileAction()
  {
    $file_id = intval(base64_decode(urldecode($this->getParam('f'))));
    if ($file_id) {
      $file = File::findFirst($file_id);
      if ($file) {
        if (strstr($file->path, '/team/') !== false || strstr($file->path, '/client/') !== false) {
          $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
          $s3Client = $aws->get("s3");
          $result = $s3Client->getObject([
            'Bucket' => 'crmgrowave',
            'Key' => $file->path,
          ]);

          $ctype = $result['ContentType'];
          $result['Body']->rewind();
          $content = '';
          while ($data = $result['Body']->read(1024)) {
            $content .= $data;
          }
        } else {
          $filename = DOCROOT . $file->path;
          if (!file_exists($filename)) {
            exit('File not found');
          }

          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $ctype = finfo_file($finfo, $filename);
          $content = file_get_contents($filename);
        }

        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // нужен для некоторых браузеров
        header("Content-Type: $ctype");
        header("Content-Disposition: attachment; filename=\"" . $file->name . "\";");
        header("Content-Transfer-Encoding: binary");
        ob_end_clean();
        ob_start();
        echo $content;
        ob_end_flush();
        exit();
      }
    }
    exit('File not found');
  }

  public function setHighPriorityAction()
  {
    if ($this->request->isPost()) {
      $client_id = $this->request->getPost('client_id', 'int');
      $status = $this->request->getPost('status', 'string');

      if ($client_id && $status) {
        $priority = 'low';

        if ($status === 'high') {
          $priority = 'high';
        }

        $client = Client::findFirst(['client_id = :id:', 'bind' => ['id' => $client_id]]);

        if ($client) {
          if ($client->high_priority !== $priority) {
            $client->high_priority = $priority;
            $client->save();
          }
          return json_encode(['result' => 'true', 'client_id' => $client_id, 'priority' => $priority]);
        }
      }
    }
    return json_encode(['result' => 'false']);
  }

  public function setTicketTagAction()
  {
    if ($this->request->isAjax() && $this->request->isPost()) {
      $ticket_id = $this->request->getPost('ticket_id', 'int');
      $tag = trim($this->request->getPost('tag_name', 'string'));

      if ($ticket_id && $tag) {

        $tags = TicketTags::findFirst(
          [
            'conditions' => 'ticket_id = :id: AND tag = :tag:',
            'bind' => ['id' => $ticket_id, 'tag' => $tag]
          ]
        );

        if (!$tags) {
          $tags_obj = new TicketTags();
          $tags_obj->ticket_id = $ticket_id;
          $tags_obj->tag = $tag;

          if ($tags_obj->save()) {
            return json_encode(
              [
                'result' => 'true',
                'params' => ['ticket_id' => $ticket_id, 'tag' => $tag]
              ]
            );
          }
        }
      }
    }

    return json_encode(['result' => 'false']);
  }

  public function deleteTicketTagAction()
  {
    if ($this->request->isAjax() && $this->request->isPost()) {

      $ticket_id = $this->request->getPost('ticket_id', 'int');
      $tag = trim($this->request->getPost('tag_name', 'string'));

      if ($ticket_id && $tag) {

        $tags_obj = TicketTags::findFirst(
          [
            'conditions' => 'ticket_id = :id: AND tag = :tag:',
            'bind' => ['id' => $ticket_id, 'tag' => $tag]
          ]
        );

        if ($tags_obj) {
          if ($tags_obj->delete()) {
            return json_encode(
              [
                'result' => 'true',
                'params' => ['ticket_id' => $ticket_id, 'tag' => $tag],
                'viewer_id' => Core::getViewerId()
              ]
            );
          }
        }
      }
    }

    return json_encode(['result' => 'false']);
  }

  public function getPopulyarTagsAction()
  {
    if ($this->request->isAjax()) {
      $tags = $this->getParam('tags');
      $tags = TicketTags::getPopulyarTags($tags);

      if ($tags) {
        return json_encode(
          [
            'result' => 'true',
            'tags' => $tags
          ]
        );
      }
    }
    return json_encode(['result' => 'false']);
  }

  public function setHighPriorityTicketAction()
  {
    if ($this->request->isAjax() && $this->request->isPost()) {
      $ticket_id = $this->getParam('ticket_id', false);
      if ($ticket_id) {
        $ticket = Ticket::findFirst(['ticket_id = :id:', 'bind' => ['id' => $ticket_id]]);
        if ($ticket) {
          if ($ticket->priority == 'low') {
            $high_priority = 'high';
          } else {
            $high_priority = 'low';
          }
          $ticket->priority = $high_priority;
          $ticket->save();
          return json_encode(['ticket_id' => $ticket_id, 'priority' => $high_priority]);
        }
      }
      return json_encode(['ticket_id' => $ticket_id, 'status' => 'false']);
    }
  }

  public function getStartupLinkAction()
  {
    $domain = 'https://growave.io';
    $growth_price = SswPackages::findFirst('package_id=3')->price;
    $shop = $this->request->getPost('shop', 'string');
    $price = $this->request->getPost('price', 'int', $growth_price);
    $special_discount = $this->request->getPost('give_discount', 'int', 0);
    $trial_days = $this->request->getPost('trial_days', 'int', 0);
    $package_id = 3;
    if ($this->request->isPost() && $shop) {
      $client = SswClients::findFirst([
        'conditions' => 'shop = ?0',
        'bind' => [$shop]
      ]);
      if ($client) {
        $request = SswRequests::findFirst([
          'conditions' => 'website = ?0 AND package_id = ?1',
          'bind' => [$client->shop, $package_id]
        ]);
        if (!$request) {
          $request = new SswRequests();
        }
        if ($special_discount && $trial_days) {
          $discount = new SswDiscount([
            'code' => dechex(time()),
            'trial_days' => $trial_days,
            'app' => 'default',
            'expiration_date' => date('Y-m-d H:i:s', strtotime('+ 1 year')),
            'min_package' => 3,
            'count' => 1,
            'note' => 'Auto generated for : ' . $client->shop
          ]);
          if (!$discount->save()) {
            throw  new \Exception($discount->getMessages()[0]->getMessage());
          }
        }

        if ($request->save([
          'website' => $client->shop,
          'email' => $client->email,
          'displayname' => $client->shop_owner,
          'revenue' => 'n/a',
          'funding' => 'n/a',
          'message' => 'CRM generated',
          'timestamp' => date('Y-m-d H:i:s'),
          'founded' => 0,
          'package_id' => $package_id,
          'discount_percent' => (1 - $price / $growth_price) * 100,
          'status' => 'accepted',
        ])) {
          $query_params = [
            'request_id' => $request->request_id
          ];
          if (isset($discount)) {
            $query_params['discount_code'] = $discount->code;
          }
          exit(json_encode([
            'success' => 1,
            'link' => $domain . '/startup?' . http_build_query($query_params)
          ]));
        }
      }
    }
    exit(json_encode([
      'error' => 1
    ]));
  }

  public function getEnterpriseLinkAction()
  {
    try {
      $domain = 'https://growave.io';
      $enterprise_price = 300;
      $shop = $this->request->getPost('shop', 'string');
      $price = $this->request->getPost('price', 'int', $enterprise_price);
      $usage_charge = $this->request->getPost('usage_charge', 'int', 0);
      $trial_days = $this->request->getPost('trial_days', 'int', 0);
      $package_id = 4;
      if ($this->request->isPost() && $shop) {
        $client = SswClients::findFirst([
          'conditions' => 'shop = ?0',
          'bind' => [$shop]
        ]);
        if ($client) {
          $request = SswRequests::findFirst([
            'conditions' => 'website = ?0 AND package_id = ?1',
            'bind' => [$client->shop, $package_id]
          ]);
          if ($trial_days) {
            $discount = new SswDiscount([
              'code' => dechex(time()),
              'trial_days' => $trial_days,
              'app' => 'default',
              'expiration_date' => date('Y-m-d H:i:s', strtotime('+ 1 year')),
              'min_package' => 4,
              'count' => 1,
              'note' => 'Auto generated for : ' . $client->shop
            ]);
            if (!$discount->save()) {
              throw  new \Exception($discount->getMessages()[0]->getMessage());
            }
          }
          if (!$request) {
            $request = new SswRequests();
          }
          if ($request->save([
            'website' => $client->shop,
            'email' => $client->email,
            'displayname' => $client->shop_owner,
            'revenue' => 'n/a',
            'funding' => 'n/a',
            'message' => 'CRM generated',
            'timestamp' => date('Y-m-d H:i:s'),
            'founded' => 0,
            'usage_charge' => $usage_charge ? 1 : 0,
            'package_id' => $package_id,
            'discount_percent' => (1 - $price / $enterprise_price) * 100,
            'status' => 'accepted',
          ])) {
            $query_params = [
              'request_id' => $request->request_id
            ];
            if (isset($discount)) {
              $query_params['discount_code'] = $discount->code;
            }
            exit(json_encode([
              'success' => 1,
              'link' => $domain . '/enterprise?' . http_build_query($query_params)
            ]));
          }
        }
      }
    } catch (\Exception $e) {
      exit(json_encode([
        'error' => 1,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]));
    }

  }

  public function purposeAction()
  {

    $session = $this->getSessionUser();
    if ($session['staff_role'] == 'admin' || $session['user_id'] == 37 || $session['user_id'] == 26) {


      $params = $this->_getAllParams();

      $page = isset($params['page']) ? $params['page'] : 1;
      $limit = isset($params['limit']) ? $params['limit'] : 100;

      $purpose = Purpose::getPurpose();
      $result = new Phalcon\Paginator\Adapter\Model(
        [
          'data' => $purpose,
          'limit' => $limit,
          'page' => $page
        ]
      );

      $users = User::find();
      $all_users = [];

      if ($users) {
        foreach ($users as $user) {
          $all_users[$user->user_id]['full_name'] = $user->full_name;
          $all_users[$user->user_id]['umg_url'] = $user->getPhotoPath();
        }
      }

      $this->view->setVars([
        'purposes' => $result->getPaginate(),
        'all_users' => $all_users
      ]);

    } else {
      return $this->response->redirect('tickets');
    }
  }

  public function getPassShopAction()
  {

    $result = ['success' => false, 'message' => 'Client not found'];
    $client_id = $this->getParam('client_id', 0);

    if (!$client_id) {
      exit(json_encode($result));
    }

    $client = Client::findFirst(['client_id = ?0', 'bind' => [$client_id]]);

    if (!$client) {
      $result['message'] = 'Client not found';
      exit(json_encode($result));
    }

    $shops = $client->getShops();

    if (!$shops) {
      $result['message'] = 'Shops not found';
      exit(json_encode($result));
    }

    $shop_ids = [];
    foreach ($shops as $shop) {
      $shop_ids[] = $shop->shop_id;
    }

    $shop_ids = implode(',', $shop_ids);
    $shops_ssw = Shops::find(["shop_id IN ({$shop_ids})", 'columns' => 'sswclient_id, url']);
    $ids = [];
    foreach ($shops_ssw as $item) {
      $ids[] = $item->sswclient_id;
    }

    $clients = SswClients::query()
      ->columns(['client_id', 'db_id'])
      ->inWhere('client_id', $ids)
      ->andWhere('new = 0 and status = 1', [])
      ->limit(100)
      ->execute();

    $result_map = [];

    foreach ($clients->toArray() as $client) {
      try {
        /** @var Phalcon\Db\Adapter\Pdo\Mysql $db */
        $db = Phalcon\DI::getDefault()->get('ssw_database' . $client['db_id']);
        $getPass = $db->query("SELECT * FROM {$client['client_id']}_shopify_core_setting WHERE name = 'storefront.password' LIMIT 1;")->fetchAll();

        if ($getPass && isset($getPass[0])) {
          $result_map[$client['client_id']] = $getPass[0]['value'];
        }
      } catch (\Exception $exception) {
      }
    }

    if (empty($result_map)) {
      $result['message'] = 'Passwords is empty';
      exit(json_encode($result));
    }

    $results = [];
    foreach ($shops_ssw as $item) {
      if (isset($result_map[$item['sswclient_id']])) {
        $results[] = ['url' => $item['url'], 'pass' => $result_map[$item['sswclient_id']]];
      }
    }

    return json_encode([
      'success' => true,
      'message' => $results
    ]);
  }

  protected function getModel()
  {
    return new Ticket();
  }

  protected function getAssignsLogsModel()
  {
    return new AssignsLogs();
  }

  public function shopException($domain, $password_info, $http_code, $text = '')
  {
    $data = [
      'shop' => $domain,
      'password_info' => $password_info,
      'http_code' => $http_code,
      'text' => $text
    ];
    logException(new \Exception(json_encode($data)));
  }

  public function instagramEmailRequestAction()
  {

  }

  public function checkNewPostAction()
  {
    $this->view->disable();
    if ($this->request->isPost() && $this->request->isAjax()) {
      $data = json_decode($this->getParam('data'));
      $response = Post::isThereAnyNewPost($data->ticket_id, $data->current_time);
      return (count($response)) ? json_encode($response) : null;
    }
  }

  public function getNightDutyDeveloper()
  {
    $todayDate = date('Y-m-d', time());
    $sql = "SELECT * FROM staff_duty WHERE (`start_date` = '$todayDate' OR `start_date` < '$todayDate') AND (`end_date` = '$todayDate' OR `end_date` > '$todayDate') LIMIT 1";
    $dutyList = $this->db->fetchOne($sql, Phalcon\Db::FETCH_ASSOC);
    $userId = $dutyList['dev_id'];
    $userSql = "SELECT * FROM crm_user WHERE staff_id='$userId' LIMIT 1";
    $user = $this->db->fetchOne($userSql, Phalcon\Db::FETCH_ASSOC);
    return $user['user_id'];
  }
}
