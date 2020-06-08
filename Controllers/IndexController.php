<?php

class IndexController extends AbstractController
{

  public function indexAction()
  {
    return $this->response->redirect('tickets');
  }

  public function show404Action()
  {
    print_die("Not Found - 404");
  }

  public function readAction()
  {
    $str = '12345';
    print_arr($str);
    print_die(is_numeric($str));
    print_die($_SERVER);

    $mbox = imap_open("{imap.gmail.com:993/imap/ssl}","j.adilets@gmail.com", "i.adilets@gmail.com" );
    $message_count = imap_num_msg($mbox);
    if ($message_count > 0) {
      $headers = imap_fetchheader($mbox, $message_count, FT_PREFETCHTEXT);
      $body = imap_body($mbox, $message_count);
      file_put_contents('/mnt/www/crm.growave.io/app/Controllers/hee.eml', $headers . "\n" . $body);
    }

    $mb = imap_open("{imap.gmail.com:993/imap/ssl}","j.adilets@gmail.com", "i.adilets@gmail.com" );
    $mails = imap_search($mb, 'UNSEEN');

    if ($mails) {
      foreach ($mails as $mail) {
        $header = imap_fetchheader($mb, $mail, FT_PREFETCHTEXT);
        $body = imap_body($mb, $mail);
        $parser = new PlancakeEmailParser($header . "\n" . $body);
        print_die($parser->getBody());
//        $structure = imap_fetchstructure($mb, $mail);
//        print_arr($structure);
//        print_arr($body);
//        print_die($header);
      }
    }
    print_die('end');
  }

  public function processListAction()
  {
    /** @var \Phalcon\Db\Adapter\MongoDB\Database $mongodb */
    $mongodb = $this->getDI()->get('mongodbTracker');
    $collection = $mongodb->selectCollection('sql_processlist');
    $cursor = $collection->find([], ['limit' => 100, 'sort' => ['_id' => -1]]);
    $data = [];
    foreach ($cursor as $item) {
      $data[] = [
        '_id' => $item->_id,
        'Id' => $item->Id,
        'User' => $item->User,
        'Host' => $item->Host,
        'db' => $item->db,
        'Command' => $item->Command,
        'Time' => $item->Time,
        'State' => $item->State,
        'Info' => strip_tags($item->Info),
        'Tracked Time' => $item->{'Tracked Time'}
      ];
    }
    echo json_encode($data);
    die;
  }

  private function getPlain($str, $boundary)
  {
    $lines = explode("\n", $str);

    $plain = false;
    $res = '';
    $start = false;
    foreach ($lines as $line) {

      if (strpos($line, 'text/plain') !== false) $plain = true;

      if (strlen($line) == 1 && $plain) {
        $start = true;
        $plain = false;
        continue;
      }

      if ($start && strpos($line, 'Content-Type') !== false) $start = false;
      if ($start)
        $res .= $line;

    }

    $res = substr($res, 0, strpos($res, '--' . $boundary));

    $res = base64_decode($res == '' ? $str : $res);

    return $res;
  }


}



