<?php

/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 15.08.14
 * Time: 16:19
 */
class TestController extends AbstractController
{
  public function ticketCloseAction()
  {
    $tickets = Ticket::find([
      'conditions' => "creation_date > '2019-10-26 22:26:13' AND status = 'open'"
    ]);
    foreach ($tickets as $ticket) {
      $lastLog = TicketLogs::findFirst([
        'conditions' => "ticket_id = :ticket_id:",
        'bind' => ['ticket_id' => $ticket->ticket_id],
        'order' => 'date DESC'
      ]);
      if ($lastLog && $lastLog->status_ticket == 'closed') {
        $ticket->status = 'closed';
        $ticket->save();
      }
    }
  }
  public function heheAction()
  {
    $whereCond = $this->getParam('all', false) ? '1' : "i.verified = 'success'";
    /**
     * @var $db Phalcon\Db\Adapter\Pdo\Mysql
     */
    $db = $this->getDI()->get('ssw_database');

    $connections = $db->fetchAll("SELECT i.client_id, c.client_id AS connected_id FROM shopify_ssw_interconnection AS i
INNER JOIN shopify_ssw_client AS c
	ON (i.shop = c.shop)
WHERE {$whereCond} ORDER BY interconnection_id");
    $groups = [];
    $clientIds = [];
    foreach ($connections as $connection) {
      $assigned = false;
      foreach ($groups as $key => $group) {
        if (in_array($connection['client_id'], $group) || in_array($connection['connected_id'], $group)) {
          $groups[$key][] = $connection['client_id'];
          $groups[$key][] = $connection['connected_id'];
          $groups[$key] = array_values(array_unique($groups[$key]));
          $assigned = true;
        }
      }
      if ($assigned === false) {
        $groups[] = [$connection['client_id'], $connection['connected_id']];
      }
      $clientIds[] = $connection['client_id'];
      $clientIds[] = $connection['connected_id'];
    }

    $clientIds = array_values(array_unique($clientIds));
    $clientIdsStr = implode(',', $clientIds);

    $clients = $db->fetchAll("SELECT c.client_id, c.shop, IF(p.type, p.type, 'free') AS type, c.status
FROM shopify_ssw_client AS c 
LEFT JOIN shopify_ssw_package AS p 
	ON (p.app = 'default' AND p.package_id = c.package_id)
WHERE c.client_id IN ({$clientIdsStr})");
    $clientsInfo = [];
    foreach ($clients as $client) {
      $clientsInfo[$client['client_id']] = $client;
    }

    $detailedGroups = [];
    foreach ($groups as $key => $group) {
      foreach ($group as $client_id) {
        $detailedGroups[$key][$client_id] = $clientsInfo[$client_id];
      }
    }

    print_die($detailedGroups);
    print_die(2323232323);
  }

  private function handleEmail(array $mailsIds, PhpImap\Mailbox $mailbox, array $mailsInfo)
  {
    foreach ($mailsIds as $mailId) {
      $mail = $mailbox->getMail($mailId);
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

//      $from = $this->checkManagedMail($from, $info->subject);

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
      else
        $app = 'default';

      $client = Client::findFirst("email = '{$from}'");
      if (!$client) {
        $client = Client::createProspectClient($from, $fromName);
      }

      $ticket = false;
      $post = false;
      if ($inReplyTo) {
        $post = Post::findFirst("message_id='{$inReplyTo}'");
        $ticket_id = ($post) ? $post->ticket_id : 0;
        $ticket = Ticket::findFirst("ticket_id = {$ticket_id}");
      }



      if (!$ticket && $post) {
        $creation_date = date("Y-m-d H:i:s");
        if (strtotime($mail->date) < time()) {
          $creation_date = date("Y-m-d H:i:s", strtotime($mail->date));
        } else {
          $firstPost = Post::findFirst([
            'conditions' => 'ticket_id = ?0',
            'bind' => [$post->ticket_id],
            'order' => 'creation_date'
          ]);
          if ($firstPost) {
            $creation_date = $firstPost->creation_date;
          }
        }
        $ticket = new Ticket();
        $ticket->save(array(
          'ticket_id' => $post->ticket_id,
          'client_id' => $client->client_id,
          'subject' => $mail->subject,
          'status' => 'open',
          'app' => $app,
          'creation_date' => $creation_date,
          'ticket_type' => (strpos(trim(strtoupper($mail->subject)), 'RE: ') === 0 && strpos($mail->textPlain, 'sendy.socialshopwave.com') !== false) ? 'default' : 'user_ticket',
          'priority' => 'low'
        ));

        $ticket->message_id = $messageId;
        $ticket->updated_date = '1988-10-01 00:00:00';
        $ticket->save();
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
}