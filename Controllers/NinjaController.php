<?php

class NinjaController extends AbstractController
{

    public function createTicketLimitOrderAction()
    {
        $body = <<<TEXT
            Ne zakrivayte test idet
            Hi Bakhriddin,
            This is a friendly reminder that you have almost reached the allocated quota of orders this month on 100. 
            This means you are getting tons of people every day who are really in love with your brand and they are obsessed with the items you sale just like me, by the way! :) 
            Just in case you forgot, your current plan is limited to 100 orders per month and if the prescribed threshold is exceeded, you will be charged for {{charge_price}} for every additional {{per_orders}} orders. 
            You can check the number of available orders here 
            Please let me know if this inspires any questions. Happy Growaving! 
TEXT;
        $data = [
            'text' => $body,
            'subject' => 'You are about to exceed your monthly limit',
            'ssw_token' => md5('DoYouKnowThatMyUniqKwyIsFCBARCELONA' . intval((time()/60))),
            'ticket_type' => 1,
            'client_email' => 'bakhramov.96@gmail.com',
            'client_name' => 'Bakhriddin',
            'sswclient_id' => 56128
        ];

        $data['ssw_token'] = md5('DoYouKnowThatMyUniqKwyIsFCBARCELONA' . intval((time()/60)));
        $data = http_build_query($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://crmdev.growave.io/ticket/create');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200);
        $output = curl_exec($ch);
        curl_close($ch);
        print_die($output);
    }

    public function indexAction()
    {
        print_die('Soon this feature will be enabled back!');

        $app_position = (int)$this->getParam('position', 20);
        $category_id = (int)$this->getParam('category', 3);
        $period = (int)$this->getParam('period', 10);
        $period_start = $this->getParam('period_start', date("Y-m-d", strtotime('-7 day')));
        $period_end = $this->getParam('period_end', date("Y-m-d", time()));

        $categories = NinjaCategories::find();

        /**
         * @var Phalcon\Db\Adapter\Pdo\Mysql $db
         */
        $db = Phalcon\DI::getDefault()->get('ninja_database');

        $where_period = "c.track_date BETWEEN '{$period_start}' AND '{$period_end}'";

        // get day list
        $days = $db->fetchAll("SELECT DISTINCT c.track_date FROM stat_charts AS c
INNER JOIN apps AS a ON (c.app = a.handle)
WHERE a.category_id = {$category_id} AND  c.position <= {$app_position} AND {$where_period}
ORDER BY c.track_date DESC");
        $dayList = [];
        $appInfoSample = ['title' => ''];
        foreach ($days as $day) {
            $dayList[] = $day['track_date'];
            $appInfoSample[$day['track_date']] = ['v' => 0, 'f' => 'no data'];
        }

        // get app list
        $apps = $db->fetchAll("SELECT DISTINCT a.app_id FROM stat_charts AS c
INNER JOIN apps AS a ON (c.app = a.handle)
WHERE a.category_id = {$category_id} AND  c.position <= {$app_position} AND {$where_period}
ORDER BY c.track_date DESC, c.position");
        $app_ids = [0];
        foreach ($apps as $app) {
            $app_ids[] = $app['app_id'];
        }

        $app_ids_str = implode(',', $app_ids);
        $appsData = $db->fetchAll("SELECT a.title, a.app_id, a.category_id, c.track_date, c.review_count, c.review_rate, c.position FROM stat_charts AS c
INNER JOIN apps AS a ON (c.app = a.handle)
WHERE a.app_id IN ({$app_ids_str}) AND {$where_period}
ORDER BY c.track_date DESC, c.position");

        $appTempList = [];
        $sswAppTitle = '';
        $instaAppTitle = '';
        foreach ($appsData as $app) {
            $appInfo = (!isset($appTempList[$app['app_id']])) ? $appInfoSample : $appTempList[$app['app_id']];
            $appInfo['title'] = $app['title'];
            $appInfo[$app['track_date']] = ['v' => (int)$app['position'], 'f' => "{$app['review_count']} ({$app['position']})"];
            $appTempList[$app['app_id']] = $appInfo;
            if (!$sswAppTitle && $app['app_id'] == 596) {
                $sswAppTitle = $app['title'];
            }
            if (!$instaAppTitle && $app['app_id'] == 605) {
                $instaAppTitle = $app['title'];
            }
        }

        $appList = [];
        foreach ($appTempList as $app_id => $info) {
            $appList[] = array_values($info);
        }

        $this->view->setVar('period_start', $period_start);
        $this->view->setVar('period_end', $period_end);
        $this->view->setVar('position', $app_position);
        $this->view->setVar('categories', $categories);
        $this->view->setVar('selected_category', $category_id);
        $this->view->setVar('appListJS', json_encode($appList));
        $this->view->setVar('dayListJS', json_encode($dayList));
        $this->view->setVar('sswAppJS', json_encode($sswAppTitle));
        $this->view->setVar('instaAppJS', json_encode($instaAppTitle));
        $this->getAppPositions();
    }

    public function getAppPositions()
    {
        $keywords = NinjaKeywords::find();

        $data = [];
        $columns = [
            [
                'title' => 'App',
                'type' => 'string',
                'key' => 'app'
            ],
            [
                'title' => 'Keyword',
                'type' => 'string',
                'key' => 'keyword'
            ],
        ];

        foreach ($keywords as $keyword) {
            $data[$keyword->id] = [
                "keyword" => $keyword->keyword,
                "app" => $keyword->app,
            ];
        }
        $d = new DateTime('first day of this month');

        $period_start = $this->getParam('date_start', $d->format('Y-m-d H:i:s'));
        $period_end = $this->getParam('date_end', date("Y-m-d H:i:s", time()));

        //print_die($d->format('Y-m-d') );
        $this->view->setVar('ap_period_start', $period_start);
        $this->view->setVar('ap_period_end', $period_end);

        $dates = [
            'mindate' => $period_start,
            'maxdate' => $period_end,
        ];

        $app_positions = NinjaAppPositions::query()
            ->orderBy('date DESC')
            ->where('date >= :mindate: and date <= :maxdate:', $dates)
            ->limit(count($keywords) * 4)
            ->execute();

        //print_die($app_positions->toArray());

        $dates = [];
        $days = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');
        foreach ($app_positions as $position) {
            if (!isset($dates[date('Y_m_d', strtotime($position->date))])) {
                $dates[date('Y_m_d', strtotime($position->date))] = true;
                $columns[] = [
                    'title' => date('Y-M-d', strtotime($position->date)) . ' ' . $days[date('w', strtotime($position->date))],
                    'type' => 'number',
                    'key' => date('Y_m_d', strtotime($position->date))
                ];
            }
            if (isset($data[$position->keyword_id])) {
                $data[$position->keyword_id][date('Y_m_d', strtotime($position->date))] = $position->position;
            }
        }

        $tmp_columns = [];
        foreach ($columns as $column) {
            if ($column['key'] == 'app') {
                $tmp_columns[1] = $column;
            } else if ($column['key'] == 'keyword') {
                $tmp_columns[2] = $column;
            } else {
                $tmp_columns[strtotime(str_replace('_', '-', $column['key']))] = $column;
            }
        }
        ksort($tmp_columns);
        $columns = [];
        foreach ($tmp_columns as $column) {
            $columns[] = $column;
        }

        $_data = [];
        foreach ($data as $datum) {
            $row = [];
            foreach ($columns as $column) {
                if ($column['type'] == 'number') {
                    if (isset($datum[$column['key']]))
                        $pos = (int)$datum[$column['key']];
                    else
                        $pos = -1;
                    $s = $pos . '';
                    if ($pos < 0) {
                        $pos = 999999;
                        $s = '-';
                    }
                    $row[] = [
                        'v' => $pos,
                        'f' => $s,
                    ];
                } else {
                    if (isset($datum[$column['key']]))
                        $row[] = $datum[$column['key']];
                    else
                        $row[] = "-";
                }

            }
            $_data[] = $row;
        }

        //print_arr($columns);
        //print_die($_data);

        $this->view->setVar('ap_columns', json_encode($columns));
        $this->view->setVar('ap_data', json_encode($_data));
    }

}