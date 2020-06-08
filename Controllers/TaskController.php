<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 18.07.14
 * Time: 10:02
 */

require_once(__DIR__ . '/../library/htmlSql/snoopy.class.php');
require_once(__DIR__ . '/../library/htmlSql/htmlsql.class.php');

class TaskController extends AbstractController
{

  private static $count_duty_ticket = array();
  private static $bot_id = 64;

  public function indexAction()
  {
    $task = new Tasks();
    $task->updateShopStatus();
  }

  private function microtime_float()
  {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }

  public function runAction()
  {

    $this->pushDuty();

    ///--------------------- Attach event listener ------------------------
    $this->eventsManager->attach('shopify-payments:paymentExportSuccess', new PartnerBalanceListener());
    ///--------------------------------------------------------------------
    $time_start = $this->microtime_float();
    if ($_GET['from'] == 'hehe') {
      global $config;
      $server = $config->email->server;
      $username = $config->email->username;
      $password = $config->email->password;

      try {
        $mailbox = new PhpImap\Mailbox($server, $username, $password, DOCROOT . DIRECTORY_SEPARATOR . 'ticket_files' . DIRECTORY_SEPARATOR);
        $mailsIds = $mailbox->searchMailBox('UNSEEN');
      } catch (\Exception $e) {
        if ($e->getCode() == 2) {
          sleep(5);
          $mailbox = new PhpImap\Mailbox($server, $username, $password, DOCROOT . DIRECTORY_SEPARATOR . 'ticket_files' . DIRECTORY_SEPARATOR);
          $mailsIds = $mailbox->searchMailBox('UNSEEN');
        } else {
          throw $e;
        }
      }
      $mailsInfoList = $mailbox->getMailsInfo($mailsIds);
      $mailsInfo = array();
      foreach ($mailsInfoList as $info) {
        $mailsInfo[$info->uid] = $info;
      }
      $this->handleEmail($mailsIds, $mailbox, $mailsInfo);

    }
    $time_end = $this->microtime_float();
    $time = $time_end - $time_start;
    //print_slack("TIME: ".$time,'kalyskin_debug');
    exit(0);
  }

  private function handleEmail(array $mailsIds, PhpImap\Mailbox $mailbox, array $mailsInfo)
  {
    foreach ($mailsIds as $mailId) {
      $mail = $mailbox->getMail($mailId);
      $parseReviewResult = $this->checkMailReview($mail);
      if($parseReviewResult && $parseReviewResult != "SUCCSESS !") {
        print_slack([$parseReviewResult], 'alik');
      }
      $messageId = trim($mail->messageId, '<>');
      $from = ($mail->fromAddress) ? $mail->fromAddress : '';
      $fromName = ($mail->fromName) ? $mail->fromName : '';
      $mail->textPlain = ($mail->textPlain) ? $mail->textPlain : Mail::htmlToText($mail->textHtml);
      $message = $this->getOriginalMailMessage($mail);
      $info = isset($mailsInfo[$mailId]) ? $mailsInfo[$mailId] : false;
      $inReplyTo = ($info && isset($info->in_reply_to) && $info->in_reply_to) ? trim($info->in_reply_to, '<>') : false;

      if ($from == 'partners@shopify.com' && $info->subject == 'Payment export completed') {
        foreach ($mail->getAttachments() as $attachment) {
          if (strstr($attachment->filePath, '.csv') !== false || strstr($attachment->filePath, '.zip') !== false) {
            Payment::parsePayments($attachment->filePath);
            ///--------------------- Fire event ------------
            $this->eventsManager->fire('shopify-payments:paymentExportSuccess', $this);
            ///-----------------------------------------------
            print_slack("Payment export - OK - Finished");
          } else {
            print_slack("Payment export - Problem - No file");
          }
        }
        continue;
      }

      if ($from == 'no-reply@chatra.io') {
        $pattern = '#\[(.+)\]\s*\(new\)[\w\W]+\:\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})#';
        $matches = array();
        $chatra = array();
        preg_match_all($pattern, $message, $matches);

        $chatra['fromName'] = isset($matches[1][0]) ? trim($matches[1][0]) : false;
        $chatra['from'] = isset($matches[2][0]) ? trim($matches[2][0]) : false;

        if ($chatra['from']) {
          $from = $chatra['from'];
          $fromName = $chatra['fromName'];
          $mail->subject = 'Regarding a request on the app';
          $messageId = 0;
        }
      }

      $from = $this->checkManagedMail($from, $info->subject);

      $to = ($mail->toString) ? $mail->toString : '';

      if (strstr($to, 'insta@socialshopwave.com'))
        $app = 'instagram';
      elseif (strstr($to, 'azem@socialshopwave.com'))
        $app = 'azem';
      elseif (strstr($to, 'nurila@socialshopwave.com'))
        $app = 'nurila';
      elseif (strstr($to, 'nargiz@socialshopwave.com'))
        $app = 'nargiz';
      elseif (strstr($to, 'emma@socialshopwave.com'))
        $app = 'emma';
      elseif (strstr($to, 'nicole@socialshopwave.com'))
        $app = 'nicole';
      elseif (strstr($to, 'kalys@socialshopwave.com'))
        $app = 'kalys';
      elseif (strstr($to, 'chyngyz@socialshopwave.com'))
        $app = 'chyngyz';
      elseif (strstr($to, 'tursunai@socialshopwave.com'))
        $app = 'tursunai';
      // groWave.io
        elseif (strstr($to, 'insta@growave.io'))
        $app = 'instagram';
        elseif (strstr($to, 'azem@growave.io'))
        $app = 'azem';
        elseif (strstr($to, 'nurila@growave.io'))
        $app = 'nurila';
        elseif (strstr($to, 'nargiz@growave.io'))
        $app = 'nargiz';
        elseif (strstr($to, 'emma@growave.io'))
        $app = 'emma';
        elseif (strstr($to, 'nicole@growave.io'))
        $app = 'nicole';
        elseif (strstr($to, 'kalys@growave.io'))
        $app = 'kalys';
        elseif (strstr($to, 'chyngyz@growave.io'))
        $app = 'chyngyz';
        elseif (strstr($to, 'tursunai@growave.io'))
        $app = 'tursunai';
      elseif (strstr($to, 'timur@growave.io'))
        $app = 'timur';
      elseif (strstr($to, 'alice@growave.io'))
        $app = 'aiperi';
      elseif (strstr($to, 'anton@growave.io'))
        $app = 'anton';
      elseif (strstr($to, 'aida@growave.io'))
        $app = 'aida';
      elseif (strstr($to, 'aibek@growave.io'))
        $app = 'aibek';
      elseif (strstr($to, 'tina@growave.io'))
        $app = 'tina';
      elseif (strstr($to, 'sales@growave.io'))
        $app = 'sales';
      elseif (strstr($to, 'aiza@growave.io'))
        $app = 'aiza';
      else
        $app = 'default';

      $client = Client::findFirst("email = '{$from}'");
      if (!$client) {
        $client = Client::createProspectClient($from, $fromName);
      }

      $ticket = false;
      if ($inReplyTo) {
        $post = Post::findFirst("message_id='{$inReplyTo}'");
        $ticket_id = ($post) ? $post->ticket_id : 0;
        $ticket = Ticket::findFirst("ticket_id = {$ticket_id}");
      }

      $creation_date = (strtotime($mail->date) < time())
        ? date("Y-m-d H:i:s", strtotime($mail->date))
        : date("Y-m-d H:i:s");

      if (!$ticket) {
        $ticket = new Ticket();
        $ticket->save(array(
          'client_id' => $client->client_id,
          'subject' => $mail->subject,
          'status' => 'open',
          'app' => $app,
          'creation_date' => $creation_date,
          'ticket_type' => (strpos(trim(strtoupper($mail->subject)), 'RE: ') === 0 && strpos($mail->textPlain, 'sendy.socialshopwave.com') !== false) ? 'default' : 'user_ticket',
          'priority' => 'low'
        ));

        //ticket logs
        $ticket_logs = new TicketLogs();
        $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);

      } else {
        $oldStatus = $ticket->status;
        $ticket->status = 'open';
        $ticket->save();

        //ticket logs
        if ($oldStatus == 'closed') {
          $ticket_logs = new TicketLogs();
          $ticket_logs->setLogs($ticket->ticket_id, $ticket->status);
        }
      }

      $ticket->message_id = $messageId;
      $ticket->updated_date = $creation_date;
      $ticket->save();

      $empty_message = false;
      if (!$message) {
        $message = 'Post is empty';
        $empty_message = true;
      }
      $post = new Post();
      $post->save(array(
        'ticket_id' => $ticket->ticket_id,
        'text' => $message,
        'staff_id' => 0,
        'type' => 'client',
        'message_id' => $messageId,
        'creation_date' => $creation_date,
        'message_body' => $mail->textPlain,
        'subject' => $mail->subject
      ));

      $aws = \Aws\Common\Aws::factory('../app/config/amazon.php');
      $s3Client = $aws->get("s3");

      // attachments
      $internalFiles = array();
      foreach ($mail->getAttachments() as $attachment) {
        $filePath = str_replace(DOCROOT, '', $attachment->filePath);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $attachment->filePath);

        $need_save = false;
        if ($empty_message && strstr($attachment->filePath, 'mail.plain') !== false) {
          $post->text = file_get_contents($attachment->filePath);
          $need_save = true;

        } else if (empty($mail->textHtml) && empty($mail->textPlain) && $mail->fromAddress == 'noreply@shopify.com') {

          if (substr($attachment->id, 0, 10) === "text/plain") {
            $post->text = file_get_contents($attachment->filePath);
            $need_save = true;
          }
          if (substr($attachment->id, 0, 9) === "text/html") {
            $post->message_body = file_get_contents($attachment->filePath);
            $need_save = true;
          }
        }
        if ($need_save) {
          $post->save();
        }

        $ts = uniqid();
        $ext = pathinfo($attachment->filePath, PATHINFO_EXTENSION);

        $s3FilePath = "/client/ticket-{$ticket->ticket_id}-{$ts}.{$ext}";
        $s3Client->putObject(array(
          'Bucket' => 'crmgrowave',
          'Key' => $s3FilePath,
          'ACL' => 'private',
          'Body' => fopen($attachment->filePath, 'r+'),
          'CacheControl' => 'max-age=604800'
        ));

        $file = new File();
        $file->save(array(
          'parent_type' => 'post',
          'parent_id' => $post->post_id,
          'type' => $type,
          'ext' => $ext,
          'name' => $attachment->name,
          'size' => filesize($attachment->filePath),
          'hash' => '',
          'path' => $s3FilePath
        ));

        @unlink($attachment->filePath);

        $internalFiles[$attachment->id] = $file->getIdentity();
      }

      $fetchedHtml = $mail->textHtml;
      $baseUri = 'https://crm.growave.io/ticket/getFile?f=';
      foreach ($mail->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
        if (isset($internalFiles[$attachmentId])) {
          $fetchedHtml = str_replace($placeholder, $baseUri . urlencode(base64_encode($internalFiles[$attachmentId])), $fetchedHtml);
        }
      }

      if ($fetchedHtml != $mail->textHtml) {
        $post->message_body = $fetchedHtml;
        $post->save();
      }

      // Auto reply
      if (!$client || ($client && $client->client_id !== 42716)) {
        $ticket->autoReply($to);
      } else {
        print_slack($client->client_id, 'phptest');
      }

      try {
        $rr = $this->checkMailReview($mail);
      } catch (Exception $e) {
        print_slack($e->getMessage(), 'kalyskin_debug');
        print_slack($e->getMessage(), 'aidar-debug');
      }

    }
  }

  private function getOriginalMailMessage(PhpImap\IncomingMail $mail, $text = false)
  {
    $body = ($text) ? $text : $mail->textPlain;
    $fromEmail = preg_quote($mail->fromAddress);
    $fromName = preg_quote($mail->fromName);
    $toKeys = array_keys($mail->to);
    $toEmail = preg_quote(reset($toKeys));
    $toName = preg_quote(reset($mail->to));

    $body_array = explode("\n", $body);
    $message = "";
    try {
      foreach ($body_array as $value) {
        if ($value == "_________________________________________________________________") {
          break;
        } elseif (preg_match("/^-*(.*)Original Message(.*)-*/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^On(.*)wrote:(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^On(.*)$fromName(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^On^(e )(.*)$toName(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^(.*)$toEmail(.*)wrote:(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^(.*)$fromEmail(.*)wrote:(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/<(.*)$fromEmail(.*)>/i", $value, $matches)) {
          break;
        } elseif (preg_match("/<(.*)$toEmail(.*)>/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^>(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^---(.*)On(.*)wrote:(.*)/i", $value, $matches)) {
          break;
        } elseif (preg_match("/^From:(.*)($toEmail)/", $value, $matches)) {
          break;
        } elseif (preg_match("/^From:(.*)($fromEmail)/", $value, $matches)) {
          break;
        } else {
          $message .= "$value\n";
        }
      }
    } catch (\Exception $e) {
      if (strpos($e->getMessage(), 'Compilation failed: nothing to repeat at offset 2') !== false) {
        print_slack([
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'code' => $e->getCode(),
          'toEmail' => $toEmail,
          'fromEmail' => $fromEmail,
          'fromName' => $fromName,
          'toKeys' => $toKeys,
          'toName' => $toName,
          'body_array' => $body_array
        ], 'burya');
      }
      throw $e;
    }

    return trim($message);
  }

  public function twitterAction()
  {
    try {
      $leads = Lead::getActiveLeads();
      foreach ($leads as $lead) {
        if (Shops::findFirst("url = '" . $lead->site_url . "'")) {
          $lead->status = "client";
          $lead->last_checked_time = time();
          $lead->checked_count = $lead->checked_count + 1;
          $lead->save();
          continue;
        }
        $result = preg_match("/https?:\/\/(www\.)?twitter\.com\/(#!\/)?@?([^\/]*)/", $lead->tw_url, $matches);
        if ($result == 1) {
          $tw_username = $matches[3];
          if (strtolower($tw_username) == 'share' || stripos($tw_username, 'home?') || strtolower($tw_username) == 'shopify') {
            $twitter_account = $this->getTwitterAccount($lead->site_url);
            if ($twitter_account === false) {
              $lead->status = "ignored";
              $lead->last_checked_time = time();
              $lead->checked_count = $lead->checked_count + 1;
              $lead->save();
              continue;
            }

            $lead->tw_url = $twitter_account['tw_url'];
            $lead->following = $twitter_account['following'];
            $lead->followers = $twitter_account['followers'];
            $tw_username = $twitter_account['tw_username'];
          } else {
            $tw_user = Twitter::getUser($tw_username);
            if (isset($tw_user->errors) || $tw_user->protected) {
              $twitter_account = $this->getTwitterAccount($lead->site_url);
              if ($twitter_account === false) {
                $lead->status = "ignored";
                $lead->last_checked_time = time();
                $lead->checked_count = $lead->checked_count + 1;
                $lead->save();
                continue;
              }
              $lead->tw_url = $twitter_account['tw_url'];
              $lead->following = $twitter_account['following'];
              $lead->followers = $twitter_account['followers'];
              $tw_username = $twitter_account['tw_username'];
            }
          }

        } else {
          // try to get twitter account again
          $twitter_account = $this->getTwitterAccount($lead->site_url);
          if ($twitter_account === false) {
            $lead->status = "ignored";
            $lead->last_checked_time = time();
            $lead->checked_count = $lead->checked_count + 1;
            $lead->save();
            continue;
          }

          $lead->tw_url = $twitter_account['tw_url'];
          $lead->following = $twitter_account['following'];
          $lead->followers = $twitter_account['followers'];
          $tw_username = $twitter_account['tw_username'];
        }

        /*if($lead->checked_count == 0){
          // Fave status
          Twitter::faveLastTweet($lead->lead_id, $tw_username);
        }
        elseif($lead->checked_count == 1){
          // Reply tweet
          Twitter::replyTweet($lead->lead_id, $tw_username);
        }
        elseif($lead->checked_count == 2){
          // Fave status and completed for this lead
          Twitter::faveLastTweet($lead->lead_id, $tw_username);
          $lead->status = 'completed';
        }*/

        /*if($lead->checked_count == 0){
          // Fave status
          Twitter::faveLastTweet($lead->lead_id, $tw_username);
        }
        elseif($lead->checked_count == 1){
          // Fave status and completed for this lead
          Twitter::faveLastTweet($lead->lead_id, $tw_username);
          $lead->status = 'completed';
        }
        else{
          $lead->status = 'completed';
        }*/

        if ($lead->checked_count == 0) {
          // Follow user
          Twitter::followUser($lead->lead_id, $tw_username);
          $lead->last_checked_time = time();
          $lead->checked_count = $lead->checked_count + 1;
        } elseif ($lead->checked_count == 1) {
          // Reply tweet
          $last_tweet_time = intval(Settings::getSetting('last_tweet_time', time()));
          if ($last_tweet_time + 86400 < time()) {
            $message = $this->getRandomMessage();
            Twitter::replyTweet($lead->lead_id, $tw_username, $message);
            Settings::setSetting('last_tweet_time', time());
            $lead->last_checked_time = time();
            $lead->checked_count = $lead->checked_count + 1;
          }
        } else {
          // Fave status and completed for this lead
          Twitter::faveLastTweet($lead->lead_id, $tw_username);
          $lead->status = 'completed';
          $lead->last_checked_time = time();
          $lead->checked_count = $lead->checked_count + 1;
        }

        $lead->save();
      }
      exit(0);
    } catch (\Exception $e) {
      $message = 'An error occured from Twitter Api,' . '.
      Message: ' . $e->getMessage() . ',
      File:' . $e->getFile() . '. On line: ' . $e->getLine() . ',' .
        'Trace: ' . $e->getTraceAsString() . '.';
      mail('burya1988@gmail.com', 'Error from Twitter Api', $message);
      exit(0);
    }
  }

  public function getTwitterAccount($site_url)
  {
    $html = file_get_contents($site_url);
    $wsql = new htmlsql();
    // connect to a URL
    if (!$wsql->connect('string', $html)) {
      return false;
    }
    if (!$wsql->query('SELECT * FROM a WHERE stripos($href, "twitter.com/") !== false')) {
      return false;
    }
    $twitter_accounts = $wsql->fetch_array();
    $account = array();
    foreach ($twitter_accounts as $twitter_account) {
      $result = preg_match("/https?:\/\/(www\.)?twitter\.com\/(#!\/)?@?([^\/]*)/", $twitter_account['href'], $matches);
      if ($result == 1) {
        $tw_username = $matches[3];

        // Check for fake account
        if (strtolower($tw_username) == 'share' || stripos($tw_username, 'home?') || strtolower($tw_username) == 'shopify')
          continue;

        // Check account for exists and protected
        $tw_user = Twitter::getUser($tw_username);
        if (isset($tw_user->errors) || $tw_user->protected)
          continue;

        $account = array(
          'tw_username' => $tw_username,
          'tw_url' => $twitter_account['href'],
          'following' => $tw_user->friends_count,
          'followers' => $tw_user->followers_count
        );
        break;
      }
    }

    if (!empty($account)) {
      return $account;
    }

    return false;
  }

  public function getRandomMessage()
  {
    $messages = array(
      /*"Collect email addresses by allowing customers to register using their Facebook account: https://growave.io/app/social-login?utm_source=twitter&utm_medium=loginmsg",
      "Have you ever considered social login for your site? It lets users log in using social accounts: https://growave.io/app/social-login?utm_source=twitter&utm_medium=loginmsg1",
      "Encourage users to share your site on social media and email using Social Sharing app: https://growave.io/app/social-sharing?utm_source=twitter&utm_medium=sharingmsg",
      "Need more traffic from social media? Reward your customers for sharing: https://growave.io/app/social-sharing?utm_source=twitter&utm_medium=sharingmsg1",
      "Visitors are leaving your site with no purchase? Earn their trust using Social Reviews: https://growave.io/app/social-reviews?utm_source=twitter&utm_medium=reviewsmsg",
      "Check out the app that helps collect reviews from your Facebook page: https://growave.io/app/social-reviews?utm_source=twitter&utm_medium=reviewsmsg1",
      "Earn your audience trust and grow sales by publishing reviews on Facebook and Twitter: https://growave.io/app/social-reviews?utm_source=twitter&utm_medium=reviewsmsg2",
      "Have you ever tried wishlists for your customers? Learn more here: https://growave.io/app/wishlist?utm_source=twitter&utm_medium=wishlistmsg",
      "What about letting your customers collect and compare favorite products? https://growave.io/app/wishlist?utm_source=twitter&utm_medium=wishlistmsg1",
      "Frustrated with low traffic from social media? What about to change it using SocialShopWave app: https://growave.io/?utm_source=twitter&utm_medium=appmsg",
      "Looking for a way to grow traffic, sales and loyalty? Try our app for free: https://growave.io/?utm_source=twitter&utm_medium=appmsg1"*/

      "How do your visitors collect favorites and compare them prior to purchase?",
      "Do you notify users when their liked items are back in stock?",
      "What do you think about notifying customers when their loved items are on sale?",
      "How do you earn your store visitors trust? Reviews? Instagram photos? UGC?",
      "Do your visitors see reviews scores in Google search results? Like here: https://growave.io/public/ssw/img/apps/review/reviews-in-google-search.jpg",
      "Do you use reviews on your site? If yes, then how you are collecting them?",
      "What do you think about allowing users to log in using their Facebook, Instagram, Amazon accounts? Instead of email/pass...",
      "What about replacing your outdated email/pass login to this: https://growave.io/public/ssw/img/apps/login/social-login-dropdown.jpg?",
      "Why don't you use the form like this to collect email addresses? https://growave.io/public/ssw/img/apps/login/discount-for-signup.png",
      "\"Give $20 to Get $20\" is quite popular on many stores. Have you ever tried such campaigns?",
      "I see sharing icons on your product. Do you track and analyze them?"
    );
    $key = rand(0, (count($messages) - 1));
    return $messages[$key];
  }

  public function crawlerAction()
  {
    /*try{
      $app_id  = intval(Settings::getSetting("shopify_app_id", 1));
      if($app_id == 139){
        // Next app
        $app_id++;
        Settings::setSetting("shopify_app_id", $app_id);
        Settings::setSetting("crawler_page", 1);
        exit(0);
      }


      Settings::setSetting("shopify_app_id", $app_id);
      $app = Shopifyapp::findFirst("app_id = " . $app_id);
      if($app){
        $page = intval(Settings::getSetting("crawler_page", 1));
        $html = file_get_contents($app->url . "?page=" . $page);
        if(!$html){
          // Next app
          $app_id++;
          Settings::setSetting("shopify_app_id", $app_id);
          Settings::setSetting("crawler_page", 1);
          exit(0);
        }
        $wsql = new htmlsql();
        // connect to a URL
        if (!$wsql->connect('string', $html)){
          mail("burya1988@gmail.com", "Error from Crawler Script", 'Error while connecting: ' . $wsql->error);
          exit(0);
        }

        //write-first-review
        if (!$wsql->query('SELECT * FROM p WHERE $id == "write-first-review"')){
          mail("burya1988@gmail.com", "Error from Crawler Script", "Query error: " . $wsql->error);
          exit(0);
        }

        $noReviews = $wsql->fetch_array();
        if(empty($noReviews)){
          if (!$wsql->query('SELECT * FROM figure WHERE $class == "resourcesreviews-reviews-star"')){
            mail("burya1988@gmail.com", "Error from Crawler Script", "Query error: " . $wsql->error);
            exit(0);
          }

          $reviews = $wsql->fetch_array();
          if(empty($reviews)){
            // Next app
            $app_id++;
            Settings::setSetting("shopify_app_id", $app_id);
            Settings::setSetting("crawler_page", 1);
            exit(0);
          }
          unset($html);

          $list = Twlist::findFirst("count < 500");
          if(!$list){
            $list = new Twlist();
            $list->save(array(
              'name' => '',
              'status' => 'inactive',
              'count' => 0,
              'created' => time()
            ));
            $list->name = 'list' . $list->list_id;
            $list->save();
          }

          foreach($reviews as $review)
          {
            if(strpos($review['text'], "appcard-rating-star-5") !== false || strpos($review['text'], "appcard-rating-star-4") !== false){
              $wsql->connect('string', $review['text']);
              if (!$wsql->query('SELECT * FROM a WHERE $itemprop == "author"')){
                mail("burya1988@gmail.com", "Error from Crawler Script", "Query error: " . $wsql->error);
                exit(0);
              }
              $sites = $wsql->fetch_array();
              $shopUrl = str_replace("http", "https", $sites[0]['href']);
              if(Shops::findFirst("url = '" . $shopUrl . "'") || Lead::findFirst("site_url = '" . $shopUrl . "'")) continue;


              $data = array(
                'name' => $sites[0]['text'],
                'site_url' => $shopUrl,
                'tw_url' => null,
                'fb_url' => null,
                'list_id' => $list->list_id,
                'following' => 0,
                'followers' => 0,
                'status' => 'active',
                'created' => time(),
                'note' => null,
                'last_checked_time' => 0,
                'checked_count' => 0
              );
              $html = file_get_contents($sites[0]['href']);
              $wsql->connect('string', $html);
              unset($html);
              //$wsql->select('body');
              if (!$wsql->query('SELECT * FROM a WHERE strpos($href, "twitter.com/") !== false and strpos($href, "twitter.com/shopify") === false')){
                mail("burya1988@gmail.com", "Error from Crawler Script", "Query error: " . $wsql->error);
                exit(0);
              }
              $twitter_accounts = $wsql->fetch_array();
              if(!empty($twitter_accounts) && isset($twitter_accounts[0]['href'])){
                $result = preg_match("/https?:\/\/(www\.)?twitter\.com\/(#!\/)?@?([^\/]*)/", $twitter_accounts[0]['href'], $matches);
                if($result == 1){
                  $tw_username = $matches[3];
                  $tw_user = Twitter::getUser($tw_username);
                  $data['tw_url'] = $twitter_accounts[0]['href'];
                  $data['following'] = $tw_user->friends_count;
                  $data['followers'] = $tw_user->followers_count;
                }
              }
              if(!isset($data['following']) || !$data['following']){
                $data['following'] = 0;
              }
              if(!isset($data['followers']) || !$data['followers']){
                $data['followers'] = 0;
              }

              if (!$wsql->query('SELECT * FROM a WHERE strpos($href, "facebook.com/") !== false and strpos($href, "facebook.com/shopify") === false')){
                mail("burya1988@gmail.com", "Error from Crawler Script", "Query error: " . $wsql->error);
                exit(0);
              }

              $facebook_accounts = $wsql->fetch_array();
              if(!empty($facebook_accounts) && isset($facebook_accounts[0]['href'])){
                $data['fb_url'] = $facebook_accounts[0]['href'];
              }

              if(is_null($data['tw_url']) && is_null($data['fb_url'])) continue;

              $lead = new Lead();
              if($lead->save($data)){
                $list->count = $list->count + 1;
                $list->save();
                if($list->count == 500){
                  $list = Twlist::findFirst("count < 500");
                  if(!$list){
                    $list = new Twlist();
                    $list->save(array(
                      'name' => '',
                      'status' => 'inactive',
                      'count' => 0,
                      'created' => time()
                    ));
                    $list->name = 'list' . $list->list_id;
                    $list->save();
                  }
                }
              }else{
                // mail report to me
                $message = "An error occured from Crawler Script: " . "\n";
                foreach($lead->getMessages() as $msg)
                {
                  $message .= $msg . "\n";
                }
                mail('burya1988@gmail.com', 'Error from Crawler Script', $message);
              }

            }else{
              continue;
            }
          }
          //mail("burya1988@gmail.com", "Success crawler script", "Success! app_id: " . $app->app_id . ", app_url: " . $app->url . ", page: " . $page);
          // Next page
          $page++;
          Settings::setSetting("crawler_page", $page);
        }
        else{
          // Next app
          $app_id++;
          Settings::setSetting("shopify_app_id", $app_id);
          Settings::setSetting("crawler_page", 1);
        }
      }else{
        if(!Settings::getSetting("no_app_crawler", 0)){
          mail("burya1988@gmail.com", "No app for crawler script", "No app for crawler script. app_id: " . $app_id);
          Settings::setSetting("no_app_crawler", 1);
        }
      }

      exit(0);
    }
    catch(\Exception $e){
      $message = 'An error occured from Crawler Script,' . '.
      Message: ' . $e->getMessage(). ',
      File:' . $e->getFile(). '. On line: '. $e->getLine(). ',' .
        'Trace: ' . $e->getTraceAsString() . '.';
      mail('burya1988@gmail.com', 'Error from Crawler Script', $message);
    }*/
  }

  public function getEmailsAction()
  {
    exit(0);
    $page = intval(Settings::getSetting("get_email_page", 1));
    $leads = Lead::getLeadsPaginator(array(
      'ipp' => 10,
      'page' => $page,
      'not_status' => 'client'
    ));
    $pattern = "/(?:[A-Za-z0-9!#$%&'*+=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+=?^_`{|}~-]+)*|\"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*\")@(?:(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[A-Za-z0-9-]*[A-Za-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/";
    foreach ($leads->items as $lead) {
      if ($lead->email || Shops::findFirst("url = '" . $lead->site_url . "'"))
        continue;
      $html = @file_get_contents($lead->site_url . "/pages/contact-us");
      if (!$html) {
        $html = @file_get_contents($lead->site_url . "/pages/about-us");
      }

      if ($html) {
        preg_match_all($pattern, $html, $matches);
        if (!empty($matches)) {
          $emails = array();
          foreach ($matches[0] as $email) {
            if (!in_array($email, $emails) && strpos($email, 'example.com') === false && strpos($email, '@email.com') === false && strpos($email, '@2x.png') === false) {
              if (!Leademails::checkLeadEmail($lead->lead_id, $email)) {
                $leadEmail = new Leademails();
                $leadEmail->save(array(
                  'lead_id' => $lead->lead_id,
                  'email' => $email
                ));
              }
            }
          }
          if (!empty($emails)) {
            $lead->email = $emails[0];
            $lead->save();
          }
        }
      }
    }
    $page++;
    Settings::setSetting("get_email_page", $page);
    exit(0);
  }

  public function notifyAction()
  {
    exit(0);
    if ($this->getParam('from', '') == 'hehe') {
      try {
        global $config;
        $server = $config->notify_email->server;
        $username = $config->notify_email->username;
        $password = $config->notify_email->password;

        $imap = imap_open($server, $username, $password) or die("imap connection error");
        $from = array(
          'UNSEEN FROM "zabbix@zabbix.socialshopwave.com"',
          'UNSEEN FROM "no-reply@sns.amazonaws.com" SUBJECT "CHECK-ALB-5xx-"',
        );

        // Load twilio
        require __DIR__ . '/../../vendor/autoload.php';

        // Twilio API Credentials
        $sid = 'AC6f3df53080e80b834308ad66b7208f9b';
        $token = 'd78b0ef19c90c3c46edabc350e454da2';
        $twilioClient = new Twilio\Rest\Client($sid, $token);
        $numbers = [
          'Ermek' => '+996558995099',
          'Ravshan' => '+996558121513',
          'Ulan' => '+996559092995',
          'Azamat' => '+996558038783'
        ];
        foreach ($from as $criteria) {
          $mails = imap_search($imap, $criteria);
          if ($mails) {
            foreach ($mails as $mail) {
              $header = imap_header($imap, $mail);
              $subject = isset($header->subject) && !empty($header->subject) ? imap_utf8($header->subject) : 'Subject is empty';
              $message = $subject;

              $structure = imap_fetchstructure($imap, $mail);
              $body = imap_body($imap, $mail);
              if ($structure->encoding == 3) {
                $body = imap_base64($body);
              } elseif ($structure->encoding == 4) {
                $body = imap_qprint($body);
              } else {
                $body = imap_utf8($body);
              }
              if (strstr($criteria, '@zabbix.socialshopwave.com') !== false) {
                $host = strtok($body, "\n");
                $message = $message . ". " . $host;
              } else {
                $post1 = strpos($body, "datapoint (");
                if ($post1 !== false) {
                  $post2 = strpos($body, ")", $post1);
                  if ($post2 !== false) {
                    $dataPoint = round(substr($body, $post1 + 11, $post2 - $post1 - 11));
                    $message = $message . " .  Datapoint: " . $dataPoint;
                  }
                }
              }
              $message = str_replace("socialshopwave.com", "ssw", $message);
              $message = str_replace("db.ssw", "db-ssw", $message);
              $message = str_replace("beta.ssw", "beta-ssw", $message);
              $message = str_replace("dev.db-ssw", "dev-db-ssw", $message);
              $message = str_replace("dev.ssw", "dev-ssw", $message);

              if (trim($message) == 'SSW-Server - PROBLEM: Load Average too high on BETA' || trim($message) == 'SSW-Server - OK: Load Average too high on BETA' || strpos($message, "Autoscaling-Hooks") !== false || strpos(strtoupper($message), "DBREPLICA") !== false) {
                continue;
              }

              if (strpos($message, 'CHECK-ALB-5xx-') !== false) {
                preg_match("/\"CHECK-ALB-5..-(\w*)\"/", $message, $msgParts);
                $message = "Too many 5XX errors on {$msgParts[1]} LB";
              } else if (strpos($message, ' on ') === false) {
                preg_match("/Host\:\s([\w\.]*).com/", $body, $hosts);
                $host = (isset($hosts[1]) && $hosts[1] != 'socialshopwave.com') ? strtoupper(str_replace('.socialshopwave', '', $hosts[1])) : 'SSW';
                $message = "{$host}" . str_replace('SSW-Server', '', $message);
              }

              /*if (strpos($message, "Autoscaling-Hooks") !== false) {
                Twitter::postDirectMessage("aivarkataev", $message);
              } else {
                foreach ($users as $user) {
                  Twitter::postDirectMessage($user->twitter, $message);
                }
              }*/
              $message_sum = md5(trim($message));
              $last_sent_time = Settings::getSetting($message_sum, 0);
              if ($last_sent_time < (time() - 1800)) {
                foreach ($numbers as $number) {
                  $result = $twilioClient->messages->create(
                    $number,
                    array(
                      'from' => '+18305051824',
                      'body' => $message
                    )
                  );
                }

                Settings::setSetting($message_sum, time());
              }
            }
          }
        }
        imap_close($imap);

      } catch (\Exception $e) {
        $message = 'An error occured from Notify Api,' . '.
        Message: ' . $e->getMessage() . ',
        File:' . $e->getFile() . '. On line: ' . $e->getLine() . ',' .
          'Trace: ' . $e->getTraceAsString() . '.';
        mail('burya1988@gmail.com, ermechkin@gmail.com', 'Error from Notify Api', $message);
        exit(0);
      }
    }
  }

  public function sendCampaignAction()
  {
    try {
      // get campaign, which status is progress
      $campaign = Campaigns::findFirst("status = 'process'");
      if ($campaign) {
        $list = CampaignStatus::find(array(
          "status = 0 AND campaign_id = " . $campaign->campaign_id,
          "limit" => 20
        ));
        if (count($list) == 0) {
          $campaign->status = 'completed';
          $campaign->save();
        } else {
          foreach ($list as $item) {
            $token = md5(uniqid($item->campaign_id . $item->status_id, true));
            $unsubscribe_url = 'https://growave.io/unsubscribe/' . $token;
            $body = $campaign->body;
            $body = str_replace("unsibscribe-link", $unsubscribe_url, $body);
            Mail::sendSimpleMessage($item->email, $campaign->subject, $body);
            $item->status = 1;
            $item->unsubscribe_token = $token;
            $item->save();
          }
        }
      }
    } catch (\Exception $e) {
      $message = 'An error occured from Send Campaign,' . '.
        Message: ' . $e->getMessage() . ',
        File:' . $e->getFile() . '. On line: ' . $e->getLine() . ',' .
        'Trace: ' . $e->getTraceAsString() . '.';
      mail('burya1988@gmail.com, ermechkin@gmail.com', 'Error from Send Campaign', $message);
      exit(0);
    }
  }

  private function checkMailReview($mail)
  {
    if ($mail->fromAddress != 'noreply@shopify.com') {
      if (preg_match('/^[\S]+@shopify\.com/iu', $mail->fromAddress)) {
        print_slack("incorrect mail:" . $mail->fromAddress, 'debug');
      }
      return "INCORECT MAIL :" . $mail->fromAddress;
    }
    $isReview = false;
    $reviewUpdate = false;
    $rateMatch = [];
    $shop_name = explode(' by ', $mail->subject);
    $shop_name = array_pop($shop_name);
    $app_name = preg_match('/Shop Instagram & UGC/iu', $mail->subject) ? 'instagram-photos' : 'growave';

    if (preg_match('/New (?<rate>[0-5])[-]star review for (?<app>.+) by (?<shop>.+)/iu', $mail->subject, $rateMatch)) {
      $rate = $rateMatch['rate'];
      $isReview = true;
    } else if (preg_match('/Review[\s]*updated/iu', $mail->subject)) {
      $reviewUpdate = true;
      $isReview = true;
    } else {
      print_slack("Incorrect subject:" . $mail->subject, 'debug');
      return "INCORECT SUBJECT :" . $mail->subject;
    }

    foreach ($mail->getAttachments() as $attachment) {
      if (strpos($attachment->name, "plain")) {
        $text = file_get_contents($attachment->filePath);
        break;
      }
    }

    if ($isReview) {
      if(preg_match('/Review[\s]*updated/iu', $mail->subject)){
        $regexp = '/Here\’s\stheir\supdated\sreview:((?s).*)The\sprevious.*\s(.*)\s.*Here\’s\sthe\sold\sreview:/m';
      } else {
        $regexp = '/Here\’s\stheir\sreview:((?s).*)Reply\sto\sreview/m';
      }
      preg_match_all($regexp, $mail->textPlain, $matches, PREG_SET_ORDER, 0);
      $text = $matches[0][1] ?? '';
      $text = Mail::htmlToText($text);
      if ($text == '') {
        return 'Body is empty, shop = '. $shop_name;
      }
      $sswClient = SswClients::findFirst([
        'conditions' => 'name = :shop_name:',
        'bind' => ['shop_name' => $shop_name]
      ]);
      $shop = false;
      if ($sswClient) {
        $shop = Shops::findFirst([
          'conditions' => 'sswclient_id = :sswclient_id:',
          'bind' => ['sswclient_id' => $sswClient->client_id]
        ]);
      }

      if ($shop) {
        $appReview = AppReviews::findfirst(['conditions' => 'sswclient_id = ?0', 'bind'=>[$sswClient->client_id]]);
        if ($appReview) { //update review
          if ($reviewUpdate) {
            $oldReview = AppReviews::findFirst([
              'sswclient_id = :client_id: AND app = :app_name: AND shop = :shop_name:',
              'bind' => ['client_id' => $sswClient->client_id, 'app_name' => $app_name, 'shop_name' => $shop_name]
            ]);
            if (count($oldReview) == 0) {
              print_slack('Review not found in update review, shop = ' . $shop_name, 'debug');
              return "Review not found";
            }
            $appReview->rate = $oldReview->rate;
          } else {
            $appReview->rate = (int)$rate;
          }
          if($appReview->body != $text) {
            $appReview->body = $text;
            if(!$appReview->save()) {
              return 'ERROR WHILE SAVING REVIEW';
            }
          }
        } else { //create review
          $review = new AppReviews();
          $review->body = $text;
          $review->app = $app_name ?? 'unknown';
          $review->name = $shop_name;
          $review->shop = $shop_name;
          $review->posted_date = date('Y-m-d H:i:s');
          $review->sswclient_id = $sswClient->client_id;
          $review->rate = (int)$rate;
          if ($review->save()) {
            $shop->reviewed = 1;
            $shop->save();
          } else {
            return 'ERROR WHILE SAVING REVIEW';
          }
        }
        return "SUCCSESS !";
      }
    } else {
      print_slack('Not a review: $isReview = ' . $isReview . ',  SHOP_NAME: $shop_name = ' . $shop_name, 'debug');
      return "ISREVIEW :" . $isReview . " SHOP_NAME: " . $shop_name;
    }
    return "NO ACTION";
  }

  private function getShopUrlByName($shopName)
  {
    $url = "https://apps.shopify.com/socialshopwave";
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_URL, $url);
    $output = curl_exec($ch);
    curl_close($ch);
    $shop_name = $shopName;
    preg_match("/href\=\"http[s]*\:\/\/(?<shop_url>[a-z0-9-_]+\.myshopify\.com)\"(.*)>" . $shop_name . "<\/a>/", $output, $output_array);

    return isset($output_array['shop_url']) ? $output_array['shop_url'] : false;
  }

  private function pushDuty()
  {
    $server_timeZone = date_default_timezone_get();
    date_default_timezone_set("Asia/Bishkek");
    $h = date('H');
    try {
      if ($h >= 9 && $h <= 12 && (Settings::getSetting('morning_push', 0) != 0)) {
        FirebaseApi::pushNotificationForDuties();
        Settings::setSetting('night_push', 0);
        Settings::setSetting('morning_push', 0);
        Settings::setSetting('afternoon_push', 1);

      }

      if ($h >= 12 && $h <= 16 && (Settings::getSetting('afternoon_push', 0) != 0)) {
        FirebaseApi::pushNotificationForDutiesTomorrow();
        Settings::setSetting('afternoon_push', 0);
        Settings::setSetting('morning_push', 0);
        Settings::setSetting('night_push', 1);
      }

      if ($h >= 16 && (Settings::getSetting('night_push', 0) != 0)) {
        FirebaseApi::pushNotificationForDutiesTomorrow();
        Settings::setSetting('night_push', 0);
        Settings::setSetting('afternoon_push', 0);
        Settings::setSetting('morning_push', 1);
      }
    } catch (\Exception $e) {
      print_slack($e->getMessage(), 'omurbek_test');
    }
    date_default_timezone_set($server_timeZone);
  }

  private function checkManagedMail($from, $subject)
  {
    $subjectlApproved = 'has '.'approved'.' your request';
    $subjectRejected = 'has '.'rejected'.' your request';

    $isApproved  =  strpos($subject, $subjectlApproved);
    $isRejected  =  strpos($subject, $subjectRejected);

    if ($from == 'partners@shopify.com' && ($isApproved !== false || $isRejected !== false)) {

      $shopNameApproved = str_replace($subjectlApproved, '', $subject);
      $shopNameRejected = str_replace($subjectRejected, '', $subject);

      $shopName = $isApproved ? $shopNameApproved : $shopNameRejected;
      $shop = Shops::findFirst([
        'conditions' => 'name = :name:',
        'bind' => [
          'name' => $shopName
        ]
      ]);

      $from = $shop->primary_email ? $shop->primary_email : $shop->owner;

      if ($isApproved) {
        $shop->managed = 1;
      } else {
        $shop->managed = 0;
      }

      $shop->save();

      return $from;
    }
    return $from;
  }

  public static function setCountTicketsDuty()
  {

    //Вытащим дежурных онлайн
    $users = User::find("status = 1 AND department = 'development'");
    $duty_users = Ticket::getdevelopersDutyWorkDays($users);


    // Если сегодня нет дежурных
    if (empty($duty_users['duty'])) {
      return false;
    }

    $online_duty = array();
    foreach ($duty_users['duty'] as $key => $value) {
      if (isset($duty_users['works'][$key])) {
        $online_duty[$key] = $value;
      }
    }


    // Если дежурных нет онлайн
    if (empty($online_duty)) {
      return false;
    }

    //Узнаем кол-во тикетов на дежурных
    $duty_ids_arr = array_keys($online_duty);

    $count_tickets_duty = User::query()
      ->columns(['User.user_id, User.frontend, count(Ticket.ticket_id) as count'])
      ->inWhere('User.user_id', $duty_ids_arr)
      ->andWhere("Ticket.status = 'open'")
      ->leftJoin('Assigns', 'Assigns.staff_id = User.user_id')
      ->leftJoin('Ticket', 'Ticket.ticket_id = Assigns.ticket_id')
      ->groupBy('User.user_id')
      ->orderBy('count')
      ->limit(100)
      ->execute();


    $array_ticket_count_duty = array();
    if (!empty($count_tickets_duty->toArray())) {
      foreach ($count_tickets_duty->toArray() as $value) {
        $array_ticket_count_duty[$value['user_id']] = $value;
      }
    }

    $free_duty = array();
    foreach ($online_duty as $value) {
      if (!isset($array_ticket_count_duty[$value['user_id']])) {
        $free_duty[$value['user_id']]['user_id'] = $value['user_id'];
        $free_duty[$value['user_id']]['count'] = 0;

        $user = User::findFirst([
            'columns' => 'frontend',
            'conditions' => 'user_id = :userId:',
            'bind' => [
                'userId' => $value['user_id']
            ]
        ]);

        $free_duty[$value['user_id']]['frontend'] = $user->frontend;
      }
    }

    $all_duty_count = array_merge($free_duty, $array_ticket_count_duty);

    foreach ($all_duty_count as $value) {
      self::$count_duty_ticket[$value['user_id']] = $value;
    }

    return true;
  }

  public function StartAssignTicketsAction()
  {
    $clientIds = array();
    $ticketIds = array();
    $ticket_priority = ['Urgent' => [], 'Top' => [], 'High' => [], 'Medium' => [], 'trial30' => [], 'Low' => []];

    $this->view->disable();

    $params = [
      "page" => 1,
      "ipp" => 20,
      "assign" => self::$bot_id,
      "status" => 'open',
      "keyword" => ''
    ];

    $paginators[] = Ticket::getTicketsPaginator($params);

    // Если нету тикетов то выйдем от сюда
    if ($paginators[0]->total_items <= 0) {
      return false;
    }

    if ($paginators[0]->total_pages > 1) {
      for($i = 1; $i < $paginators[0]->total_pages; $i++) {
        $params['page']++;
        $paginators[] = Ticket::getTicketsPaginator($params);
      }
    }

    // Построим приоритеты тикетов
    foreach ($paginators as $paginator) {
      foreach ($paginator->items as $item) {
        $clientIds[] = $item->client_id;
        $ticketIds[] = $item->ticket_id;
      }
    }

    if (count($ticketIds) == 0 && count($clientIds) == 0 )  {
      return false;
    }

    $implode = implode(',', $ticketIds);
    $last_post = Post::lastTicketsPosts($implode);

    Ticket::preparePriorities($clientIds);
    $getPriority = [];
    $priority = [];

    foreach ($paginators as $paginator) {
      $getPriority = array_merge($getPriority, Ticket::priority($paginator));
    }

    foreach ($getPriority as $value) {
      $priority[$value['ticket_id']] = $value;
    }

    $ticketTags = TicketTags::getByTicketIds($ticketIds);
    $groupedTicketTags = [];

    foreach ($ticketTags as $ticketTag) {
      $groupedTicketTags[$ticketTag['ticket_id']][] = $ticketTag;
    }

    foreach ($priority as $key => $value) {
      if ($value['priority'] >= 50) {
        $ticket_priority['Urgent'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['Urgent'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      } else if ($value['priority'] >= 40) {
        $ticket_priority['Top'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['Top'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      } else if ($value['priority'] >= 30) {
        $ticket_priority['High'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['High'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      } else if ($value['priority'] >= 20) {
        $ticket_priority['Medium'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['Medium'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      } else if ($value['priority'] >= 10) {
        $ticket_priority['trial30'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['trial30'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      } else {
        $ticket_priority['Low'][$key] = $value;
        if (isset($last_post[$key])) {
          $ticket_priority['Low'][$key]['last_staff_id'] = $last_post[$key]['staff_id'];
        }
      }
    }

    $online_duty = self::setCountTicketsDuty();

    if (!$online_duty) {
      return false;
    }

    if (!empty($ticket_priority['Urgent'])) {
      foreach ($ticket_priority['Urgent'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }
    if (!empty($ticket_priority['Top'])) {
      foreach ($ticket_priority['Top'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }
    if (!empty($ticket_priority['High'])) {
      foreach ($ticket_priority['High'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }
    if (!empty($ticket_priority['Medium'])) {
      foreach ($ticket_priority['Medium'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }
    if (!empty($ticket_priority['trial30'])) {
      foreach ($ticket_priority['trial30'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }
    if (!empty($ticket_priority['Low'])) {
      foreach ($ticket_priority['Low'] as $ticket) {
        $ticketTags = (isset($groupedTicketTags[$ticket['ticket_id']])) ? $groupedTicketTags[$ticket['ticket_id']] : [];
        self::assignTicketDuty($ticket, $ticketTags);
      }
    }

    return true;
  }

  public static function assignTicketDuty($ticket, $ticketTags)
  {
    $hasTicketFrontendTag = false;

    if (isset($ticket['last_staff_id'])) {
      if (isset(self::$count_duty_ticket[$ticket['last_staff_id']])) {
        if (self::$count_duty_ticket[$ticket['last_staff_id']]['count'] <= 2) {
          Assigns::setAssignUser($ticket['ticket_id'], [0 => $ticket['last_staff_id']], self::$bot_id);
          self::$count_duty_ticket[$ticket['last_staff_id']]['count']++;
          return true;
        }
      }
    }
    foreach ($ticketTags as $ticketTag) {
      if ($ticketTag['tag'] == 'frontend') {
        $hasTicketFrontendTag = true;
      }
    }

    foreach (self::$count_duty_ticket as $key => $value) {
      $assignPermission = false;

      if ($hasTicketFrontendTag) {
        if ($value['frontend'] == 1) {
          $assignPermission = true;
        }
      } else {
        $assignPermission = true;
      }

      if ($assignPermission && $value['count'] < 2) {
        Assigns::setAssignUser($ticket['ticket_id'], [0 => $value['user_id']], self::$bot_id);
        self::$count_duty_ticket[$key]['count']++;
        break;
      }
    }

    return true;
  }

  public function findShopsWithOldMetafieldsAction()
  {
    if ($_GET['from'] == 'hehe') {
      $mongodb = $this->getDI()->get('mongodbTrackerDev');
      $crmDB = Phalcon\DI::getDefault()->get('db');

      $collection = $mongodb->selectCollection('apaccik_shops_with_old_metafields');

      /* Clean collection and start again */

//    $collection->deleteMany(['type'=>'shops_with_errors']);
//    $collection->deleteMany(['type'=>'shops_with_old_metafields']);
//    $check1 = $collection->find(['type'=>'shops_with_old_metafields']);
//    foreach ($check1 as $item) {
//      print_arr($item);
//    }
//    $check2 = $collection->find(['type'=>'shops_with_errors']);
//    foreach ($check2 as $item) {
//      print_arr($item);
//    }
//    $collection->updateOne([
//        'name' => 'last_checked_shop'
//    ], ['$set' => [
//        'name' => 'last_checked_shop',
//        'value' => 0
//    ]], ['upsert' => true]);
//    $collection->updateOne([
//        'name' => 'last_checked_client'
//    ], ['$set' => [
//        'name' => 'last_checked_client',
//        'value' => 0
//    ]], ['upsert' => true]);
//    $collection->updateOne([
//        'name' => 'finished_notified_times'
//    ], ['$set' => [
//        'name' => 'finished_notified_times',
//        'value' => 0
//    ]], ['upsert' => true]);
//    $last_shop_id = $collection->findOne(['name' => 'last_checked_shop'])->value;
//    print_arr($last_shop_id);
//    print_die('checkpoint');

      /* End Clear collection and start again */

      $last_shop_id = $collection->findOne(['name' => 'last_checked_shop'])->value;
      $last_client_id = $collection->findOne(['name' => 'last_checked_client'])->value;
      $finished_notified_times = $collection->findOne(['name' => 'finished_notified_times'])->value;

      $clients = SswClients::find([
          'conditions' => 'ssw = 1 AND status = 1 and new = 0 and client_id > :client_id:',
          'bind' => [
              'client_id' => $last_client_id
          ],
          'order' => 'client_id asc',
          'limit' => 100,
      ]);

      $client_ids = [];
      foreach ($clients as $client) {
        $client_ids[] = $client->client_id;
      }

      if (empty($client_ids)) {
        if($finished_notified_times < 5) {
          print_slack('[CRON] CHECK FOR OLD METAFIELDS FINISHED ! ! ! ', 'apaccik_debug');
          $collection->updateOne([
              'name' => 'finished_notified_times'
          ], ['$set' => [
              'name' => 'finished_notified_times',
              'value' => ($finished_notified_times + 1)
          ]], ['upsert' => true]);
        }
        print_die('finished');
      }

      $whereClientIds = implode(',',$client_ids);
      $builder = $this->modelsManager->createBuilder()
          ->from('Shops')
          ->columns('shop_id, status, sswclient_id, url')
          ->andWhere('shop_id > ' . $last_shop_id)
          ->andWhere('status = "installed"')
          ->andWhere("sswclient_id IN ({$whereClientIds}) ")
          ->orderBy('shop_id asc')
          ->limit(100);

      $shops = $builder->getQuery()->execute()->toArray();
      if (empty($shops)) {
        $collection->updateOne([
            'name' => 'last_checked_client'
        ], ['$set' => [
            'name' => 'last_checked_client',
            'value' => $client_ids[count($client_ids) - 1]
        ]], ['upsert' => true]);
        print_die('next 100 clients');
      }
      $shop_ids = [];
      $shop_info = [];
      foreach ($shops as $shop) {
        $shop_ids[] = $shop['shop_id'];
        $shop_info[$shop['shop_id']]['client_id'] = $shop['sswclient_id'];
        $shop_info[$shop['shop_id']]['url'] = $shop['url'];
      }
      $last_shop_id = $shops[count($shops) - 1]['shop_id'];


      $builder = $this->modelsManager->createBuilder()
          ->from('Modifications')
          ->columns('max(id) id, file, theme_id')
          ->inWhere("shop_id",$shop_ids)
          ->groupBy('theme_id, file')
          ->limit(100);
      $last_modifications = $builder->getQuery()->execute()->toArray();
      $last_modification_ids = [];
      foreach ($last_modifications as $last_modification) {
        $last_modification_ids[] = $last_modification['id'];
      }
      if (empty($last_modification_ids)) {
        $collection->updateOne([
            'name' => 'last_checked_shop'
        ], ['$set' => [
            'name' => 'last_checked_shop',
            'value' => $last_shop_id
        ]], ['upsert' => true]);
        print_die('No modifications found for current step');
      }

      $whereModificationIds = implode(',',$last_modification_ids);

      $builder = $this->modelsManager->createBuilder()
          ->from('Modifications')
          ->columns('id, shop_id, file, theme_id')
          ->andWhere("id IN ({$whereModificationIds})")
          ->andWhere("value like '%product.id | append: \'_rate_data\'%'")
          ->orderBy('shop_id asc')
          ->limit(100);
      $modifications = $builder->getQuery()->execute()->toArray();

      $filtered_modifications = [];
      foreach ($modifications as $modification) {
        $clientApp = \SswClientApp::findFirst("client_id = " . $shop_info[$modification['shop_id']]['client_id'] . " AND status = 1");
        if ($clientApp) {
          $app = SswApp::findFirst(array(
              "conditions" => "name = :app:",
              "bind" => array('app' => $clientApp->app)
          ));
          if ($app) {
            $api = new \Shopify\Client($this, array(
                'api_key' => $app->key,
                'secret' => $app->secret,
                'token' => $clientApp->token,
                'shop' => $shop_info[$modification['shop_id']]['url']
            ));

            if ($api) {
              try {
                $ssw_content = $api->call('GET', "/admin/themes/{$modification['theme_id']}/assets.json", array('id' => $modification['theme_id'], 'asset[key]' => $modification['file']));
              }
              catch (\Shopify\ApiException $e) {
                continue;
              }
              $found = false;
              if(array_key_exists('value', $ssw_content) && $ssw_content['value'] != "") {
                if(strpos($ssw_content['value'], "product.id | append: '_rate_data'") !== false) {
                  $found = true;
                }
                if(strpos($ssw_content['value'], "product.id | append: '_unite_rate_data'") !== false) {
                  $found = true;
                }
              }
              if (!$found) {
                continue;
              }
              $filtered_modifications[] = $modification;

            }
            else{
              $collection->updateOne([
                  'type' => 'shops_with_errors'
              ], ['$set' => [
                  'type' => 'shops_with_errors',
                  'id' => $modification['id'],
                  'shop_id' => $modification['shop_id'],
                  'file' => $modification['file'],
                  'theme_id' => $modification['theme_id'],
                  'message' => 'No API'
              ]], ['upsert' => true]);
              print_die('api error');
            }
          }
          else {
            $collection->updateOne([
                'type' => 'shops_with_errors'
            ], ['$set' => [
                'type' => 'shops_with_errors',
                'id' => $modification['id'],
                'shop_id' => $modification['shop_id'],
                'file' => $modification['file'],
                'theme_id' => $modification['theme_id'],
                'message' => 'No APP'
            ]], ['upsert' => true]);
            print_die('api error');
          }
        }
        else{
          $collection->updateOne([
              'type' => 'shops_with_errors'
          ], ['$set' => [
              'type' => 'shops_with_errors',
              'id' => $modification['id'],
              'shop_id' => $modification['shop_id'],
              'file' => $modification['file'],
              'theme_id' => $modification['theme_id'],
              'message' => 'No ClientApp'
          ]], ['upsert' => true]);
          print_die('api error');
        }
      }

      foreach ($filtered_modifications as $filtered_modification) {
        $collection->insertOne([
            'type' => 'shops_with_old_metafields',
            'id' => $filtered_modification['id'],
            'shop_id' => $filtered_modification['shop_id'],
            'file' => $filtered_modification['file'],
            'theme_id' => $filtered_modification['theme_id'],
            'message' => 'Success'
        ]);
      }
      print_arr($last_shop_id);
      $collection->updateOne([
          'name' => 'last_checked_shop'
      ], ['$set' => [
          'name' => 'last_checked_shop',
          'value' => $last_shop_id
      ]], ['upsert' => true]);
    }
    exit(0);
  }

  public function shopsWithOldMetafieldsAction()
  {
    $mongodb = $this->getDI()->get('mongodbTrackerDev');
    $collection = $mongodb->selectCollection('apaccik_shops_with_old_metafields');
    $shops = $collection->find(['type'=>'shops_with_old_metafields'], ['limit' => 500]);
    $prevShop = 0;
    $prevTheme = 0;
    foreach ($shops as $shop) {
      if ($shop->shop_id == $prevShop && $shop->theme_id == $prevTheme) {
        echo '<div style="width: 40%;margin: 0;margin-bottom: 5px;float:left;">-</div>';
      }
      else {
        echo '<div style="width: 40%;margin: 0;margin-bottom: 5px;float:left;">';
        echo '<a target="_blank" href="https://crm.growave.io/shop/' . $shop->shop_id . '">' . Shops::findFirst($shop->shop_id)->getName() . '</a>'; echo '  |  ';
        echo '<a target="_blank" href="https://crm.growave.io/shops/testkalys?id=' . $shop->shop_id . '&theme_id=' . $shop->theme_id . '">editor</a>'; echo '  |  ';
        echo '</div>';
      }
      $prevShop = $shop->shop_id;
      $prevTheme = $shop->theme_id;
      echo '<div style="width: 50%;margin: 0;margin-bottom: 5px;float:left;">';
      echo $shop->file;
      echo '</div>';
    }
  }
}
