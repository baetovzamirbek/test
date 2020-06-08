<?php

class CoolController extends AbstractController {

  const SHOPS_FILE = 'shops.txt';
  const TEMP_DIR = '/mnt/www/crm.growave.io/public/temp/';
  const SITES_DIR = '/mnt/www/crm.growave.io/public/temp/sites/';
  public function indexAction()
  {
    $do = $this->getParam('do', 'nothing');

    /**
     *  1) shops
     *  2) download
     *  3) scan
     *  4) check
     *  5) helper
     */

    if ($do == 'shops') {
      $shops_count = $this->_getShops('count');
      print_die($shops_count);
    } else if ($do == 'download') {
      $shops = $this->_getShops('all');
      $this->_getShopsHTML($shops);
    } else if ($do == 'scan') {
      $shops = $this->_getShops('all');
      $this->_scanShops($shops);
      $shops = $this->_getShops('count');
      print_die($shops);
    } else if ($do == 'check') {
      $shops = $this->_getShops('active');
      $this->_checkShops($shops);
      $shops = $this->_getShops('count');
      print_die($shops);
    } else if ($do == 'content') {
      $shop = $this->getParam('shop', false);
      if (!$shop) print_die('Undefined shop');
      $content = $this->_getShopContent($shop);
      print_die($content);
    } else if ($do == 'helper') {
      $result = array();
      $shops = $this->_getShops('full');
      $shops['in'] = array_diff($shops['in'], $shops['our']);
      print_arr('in - ' . count($shops['in']));
      print_arr('active - ' . count($shops['active']));
      $active_in = array_intersect($shops['in'], $shops['active']);
      print_arr('in-active - ' . count($active_in));
      $result['no_helper'] = array_diff($active_in, $shops['integrated']);
      $result['no_helper_count'] = count($result['no_helper']);
      $result['active_client'] = array_intersect($active_in, $shops['integrated']);
      $result['active_client_count'] = count($result['active_client']);
      $result['protected'] = array_intersect($shops['protected'], $shops['in']);
      $result['protected_count'] = count($result['protected']);
//      $result['left_helper'] = array_diff(array_intersect($shops['un'], $shops['active']), $shops['integrated']);
      print_die($result);
    }
/*    if ($do == 'events') {
      $this->_readEvents();
    } elseif ($do == 'scan') {
      $this->_scanShops();
    } elseif ($do == 'collections') {
      $this->_readEvents('/collections/all');
      $do = 'events';
    }*/

//    $this->_scanShops();

//    $content = $this->_getShopContent('111-clothing.myshopify.com__collections__all');
//    print_die($content);

/*    $failed = array();
    $protected = array();
    $no_products = array();
    $not_shopify = array();
    $shops = array('111-clothing.myshopify.com__collections__all',  '23newman.myshopify.com__collections__all',  '23retroavenue-com.myshopify.com__collections__all',  '2611-2.myshopify.com__collections__all',  '2612.myshopify.com__collections__all',  '2748brit.myshopify.com__collections__all',  '365-in-love.myshopify.com__collections__all',  '50-shades-of-dresses.myshopify.com__collections__all',  '54-floral-clothing.myshopify.com__collections__all',  '60-seconds.myshopify.com__collections__all',  '69vintage.myshopify.com__collections__all',  '723vapor-com.myshopify.com__collections__all',  '7298juli.myshopify.com__collections__all',  '7teen-gold.myshopify.com__collections__all',  'abandon.myshopify.com__collections__all',  'abundancy.myshopify.com__collections__all',  'abundantearth.myshopify.com__collections__all',  'acdtest.myshopify.com__collections__all',  'acdtestpartner.myshopify.com__collections__all',  'actionfoo.myshopify.com__collections__all',  'actordeals-com.myshopify.com__collections__all',  'adika.myshopify.com__collections__all',  'adika2.myshopify.com__collections__all',  'adiva-jewelry.myshopify.com__collections__all',  'adrenalina-total.myshopify.com__collections__all',  'aedev.myshopify.com__collections__all',  'aerryel-perymon-boutique.myshopify.com__collections__all',  'afroditesloveshop-se.myshopify.com__collections__all',  'afrogiftsnstuff.myshopify.com__collections__all',  'air-plant-decor.myshopify.com__collections__all',  'alam1234.myshopify.com__collections__all',  'alberto-torresi-2.myshopify.com__collections__all',  'alchemy-of-bliss-the-shop.myshopify.com__collections__all',  'alenistesting.myshopify.com__collections__all',  'alexa-pope-2.myshopify.com__collections__all',  'alexissimone.myshopify.com__collections__all',  'alialfital.myshopify.com__collections__all',  'alien-game-keys.myshopify.com__collections__all',  'alkila.myshopify.com__collections__all',  'allycatfashions.myshopify.com__collections__all',  'alpha-28.myshopify.com__collections__all',  'alphadext05.myshopify.com__collections__all',  'altlook.myshopify.com__collections__all',  'amashirts.myshopify.com__collections__all',  'amatrixwedding.myshopify.com__collections__all',  'ambrosia-5.myshopify.com__collections__all',  'american-barbell.myshopify.com__collections__all',  'amharaa.myshopify.com__collections__all',  'amiraskincare.myshopify.com__collections__all',  'amit-rawal.myshopify.com__collections__all',  'amm-test-store.myshopify.com__collections__all',  'amorai.myshopify.com__collections__all',  'amour-scents.myshopify.com__collections__all',  'amys-country-candles.myshopify.com__collections__all',  'andy-w.myshopify.com__collections__all',  'angeljackson.myshopify.com__collections__all',  'angeloqtr.myshopify.com__collections__all',  'animedakimakurapillow.myshopify.com__collections__all',  'anomalie-accessories.myshopify.com__collections__all',  'anti-aging-advancement.myshopify.com__collections__all',  'antonio-9.myshopify.com__collections__all',  'apnaemarket.myshopify.com__collections__all',  'app-playground.myshopify.com__collections__all',  'applepiepieces.myshopify.com__collections__all',  'apps-267.myshopify.com__collections__all',  'appstore-31.myshopify.com__collections__all',  'archiesdesignerwear.myshopify.com__collections__all',  'arduino-shop.myshopify.com__collections__all',  'areios-defense.myshopify.com__collections__all',  'areyouclothesminded.myshopify.com__collections__all',  'arielora.myshopify.com__collections__all',  'arintest.myshopify.com__collections__all',  'arishan.myshopify.com__collections__all',  'arissia.myshopify.com__collections__all',  'arrowma.myshopify.com__collections__all',  'art-creations-god-comes-first.myshopify.com__collections__all',  'arthur-and-pearl.myshopify.com__collections__all',  'artisanal-sorbets.myshopify.com__collections__all',  'artisans-of-newyork.myshopify.com__collections__all',  'artisansexchange.myshopify.com__collections__all',  'artistia.myshopify.com__collections__all',  'artistri-india.myshopify.com__collections__all',  'artmusicsex.myshopify.com__collections__all',  'artteststore.myshopify.com__collections__all',  'asto.myshopify.com__collections__all',  'atelier-edele.myshopify.com__collections__all',  'athena-apparel.myshopify.com__collections__all',  'athleticworx.myshopify.com__collections__all',  'atisundar.myshopify.com__collections__all',  'atomic-strength-nutrition.myshopify.com__collections__all',  'atrion.myshopify.com__collections__all',  'audibene.myshopify.com__collections__all',  'aurotest.myshopify.com__collections__all',  'auspaullorenzo.myshopify.com__collections__all',  'autohubver2.myshopify.com__collections__all',  'avaofficemx.myshopify.com__collections__all',  'aybat.myshopify.com__collections__all',  'ayn-syanis.myshopify.com__collections__all',  'azkara.myshopify.com__collections__all',  'b-g-innovations.myshopify.com__collections__all');
    foreach ($shops as $shop) {
      $content = $this->_getShopContent($shop);
      $matches = array();
      preg_match_all('|href\="([\w\-\:\/\.]*\/products\/[\w\-\:\/\.]+)"|', $content, $matches);
      $product_url = isset($matches[1][0]) ? $matches[1][0] : '';
      $product_urls[] = $product_url;
      if (!$product_url) {
        if (strstr($content, '//cdn.shopify.com') === false) {
          $not_shopify[] = $shop;
        } else if (strstr($content, '//cdn.shopify.com/s/assets/storefront/opening_soon') !== false) {
          $protected[] = $shop;
        } else {
          $failed[] = $shop;
        }
      }
      if ($product_url == '/admin/products/new') {
        $no_products[] = $shop;
      }
    }
    print_arr(array('$failed' => $failed));
    print_arr(array('$protected' => $protected));
    print_arr(array('$not_shopify' => $not_shopify));
    print_arr(array('$no_products' => $no_products));
    print_arr(array('$product_urls' => $product_urls));
    print_die(1);*/

    $this->view->setVar('action', $do);
  }

  public function paymentAction()
  {
    $csv_file = $this->getParam('file', 'events.csv');
    if (!file_exists(self::TEMP_DIR . $csv_file)) {
      print_die('File not found');
    }

    $handle = fopen(self::TEMP_DIR . $csv_file, "r");
    if ($handle === false) {
      print_die('File not available');
    }

    $events = array();
    while (($data = fgetcsv($handle)) !== FALSE) {
      $events[] = $data;
    }
    fclose($handle);
    array_shift($events);

    $activated = array();
    $our_emails = array('farside312@gmail.com', 'ermekcs@gmail.com', 'test.assurence@gmail.com', 'aybat.d@gmail.com', 'eldarbox@gmail.com');
    foreach ($events as $event) {
      if (in_array($event[5], $our_emails)) {
        continue;
      }

      if ($event[1] != 'recurring app charge activation' &&  $event[1] != 'recurring app charge cancellation' && $event[1] != 'app uninstall' && $event[1] != 'app install') {
        continue;
      }

      $activated[$event[6]][] = $event[1];
    }

    $paid_clients = array();
    foreach ($activated as $shopId => $actions) {
      $status = -1;
      $charge = -1;
      foreach ($actions as $action) {
        if ($status < 0 && in_array($action, array('app uninstall', 'app install'))) {
          $status = (int)($action == 'app install');
        }

        if ($charge < 0 && in_array($action, array('recurring app charge activation', 'recurring app charge cancellation'))) {
          $charge = (int)($action == 'recurring app charge activation');
        }
      }

      if ($status == 1 && $charge == 1) {
        $paid_clients[] = $shopId;
      }
    }

    print_arr($paid_clients);
    print_arr("'https://" . implode("','https://", $paid_clients) . "'");
    print_die(1);
  }

  private function _scanShops($shops, $url = '')
  {
    $protected = array();
    $not_shopify = array();
    $other = array();
    $closed = array();
    foreach ($shops as $key => $shop) {
      $content = $this->_getShopContent($shop, false, $url);
      if (strstr($content, 'class="storefront-password-form"') !== false) {
        $protected[] = $shop;
      } else if (strstr($content, '//cdn.shopify.com') === false && strstr($content, 'http://www.bohemiancoding.com/sketch/ns') !== false) {
        $closed[] = $shop;
      } else if (strstr($content, '//cdn.shopify.com') === false) {
        $not_shopify[] = $shop;
      } else if (strstr($content, '//cdn.shopify.com/s/assets/storefront/opening_soon') !== false) {
        $protected[] = $shop;
      }  else if (strstr($content, '<div id="shop-not-found">') !== false) {
        $not_shopify[] = $shop;
      } else {
        $other[] = $shop;
      }
    }

    $shops = $this->_getShops('full');
    $shops['not_shopify'] = $not_shopify;
    $shops['protected'] = $protected;
    $shops['closed'] = $closed;
    $shops['active'] = $other;
    file_put_contents(self::TEMP_DIR . self::SHOPS_FILE, json_encode($shops));
  }

  private function _checkShops($shops, $url = '')
  {
    $liquid = array();
    foreach ($shops as $key => $shop) {
      $content = $this->_getShopContent($shop, false, $url);
      $matches = array();
      preg_match_all('|Liquid\s+error\:\s+Could\s+not\s+find\s+asset\s+([\w\-\/]*\.liquid)|', $content, $matches);
      $error_liquid = isset($matches[1][0]) ? $matches[1][0] : '';
      if ($error_liquid) {
        $liquid[$error_liquid][] = $shop;
      }

      if (strstr($content, 'var sswJqLoaded = false;') !== false) {
        $integrated[] = $shop;
      } else {
        $not_integrated[] = $shop;
      }
    }

    $shops = $this->_getShops('full');
    $shops['liquid'] = $liquid;
    $shops['integrated'] = $integrated;
    $shops['not_integrated'] = $not_integrated;

    file_put_contents(self::TEMP_DIR . self::SHOPS_FILE, json_encode($shops));
  }

  /**
   * @param string $flag 'full'|'all'|'count'|'our'|'un'|'in'|'client'|'not_shopify'|'protected'|'closed'|'active'|'liquid'|'integrated'|'not_integrated'
   * @return array
   */
  private function _getShops($flag = 'all')
  {
    if (!file_exists(self::TEMP_DIR . self::SHOPS_FILE)) {
      $this->_readEvents();
    }

    $shops_json = file_get_contents(self::TEMP_DIR . self::SHOPS_FILE);
    $shops = json_decode($shops_json, true);

    if ($flag == 'count') {
      $result = array();
      $properties = array('all', 'our', 'in', 'un', 'not_shopify', 'protected', 'closed', 'active', 'liquid', 'integrated', 'not_integrated');
      foreach ($properties as $key) {
        if (isset($shops[$key])) {
          $result[$key] = count($shops[$key]);
        }
      }
      return $result;
    } else if ($flag == 'client') {
      return array_diff($shops['all'], $shops['our']);
    } else if ($flag == 'full') {
      return $shops;
    }

    return $shops[$flag];
  }

  private function _readEvents()
  {
    $csv_file = $this->getParam('file', 'events.csv');
    if (!file_exists(self::TEMP_DIR . $csv_file)) {
      print_die('File not found');
    }

    $handle = fopen(self::TEMP_DIR . $csv_file, "r");
    if ($handle === false) {
      print_die('File not available');
    }

    $events = array();
    while (($data = fgetcsv($handle)) !== FALSE) {
      $events[] = $data;
    }
    fclose($handle);
    array_shift($events);

    $all_shops = array();
    $our_shops = array();
    $in_shops = array();
    $un_shops = array();
    $shop_events = array();
    $our_emails = array('farside312@gmail.com', 'ermekcs@gmail.com', 'test.assurence@gmail.com', 'aybat.d@gmail.com', 'eldarbox@gmail.com');
    foreach ($events as $event) {

      $all_shops[$event[6]] = 1;

      if (in_array($event[5], $our_emails)) {
        $our_shops[$event[6]] = 1;
      }

      if ($event[1] != 'app install' &&  $event[1] != 'app uninstall') {
        continue;
      }

      $shop_events[$event[6]] = isset($shop_events[$event[6]]) ? $shop_events[$event[6]] : $event[1];
      if ($shop_events[$event[6]] == 'app install') {
        $in_shops[$event[6]] = 1;
      } else {
        $un_shops[$event[6]] = 1;
      }
    }

    $shops = array(
      'in' => array_keys($in_shops),
      'un' => array_keys($un_shops),
      'our' => array_keys($our_shops),
      'all' => array_keys($all_shops),
    );

    file_put_contents(self::TEMP_DIR . self::SHOPS_FILE, json_encode($shops));
  }

  private function _getShopsHTML($shops, $url = '')
  {
    $page = $this->getParam('page', 1);

    $limit = 100;
    $start = ($page - 1) *  $limit;
    foreach ($shops as $i => $shop) {
      if ($i < $start) continue;
      if (($start + $limit) == $i) break;

      $this->_getShopContent($shop, false, $url);
    }

    $this->view->setVar('page', $page);
    $this->view->setVar('done', $i);
    $this->view->setVar('left', count($shops) - $i - 1);
  }

  private function _getShopContent($shop, $flush = false, $url = '')
  {
    $file = self::SITES_DIR . $shop . str_replace('/', '__', $url);
    $shop_url = $shop . $url;
    if (!$flush && file_exists($file)) {
      $content = file_get_contents($file);
    } else {
      $content = $this->_getContent($shop_url);
      file_put_contents($file, $content);
    }

    return $content;
  }

  private function _getContent($shop)
  {
    $url = "http://{$shop}";

    $curl = curl_init();

    $header[] = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
    $header[] = "Cache-Control: max-age=0";
    $header[] = "Connection: keep-alive";
    $header[] = "Keep-Alive:timeout=5, max=100";
    $header[] = "Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.3";
    $header[] = "Accept-Language:es-ES,es;q=0.8";
    $header[] = "Pragma: ";

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11');
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_REFERER, 'http://www.google.com');
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate,sdch');
    curl_setopt($curl, CURLOPT_AUTOREFERER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);

    $html = curl_exec($curl);
    curl_close($curl);
    return $html;
  }
}