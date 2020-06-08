<?php

use Phalcon\Mvc\View\Simple as SimpleView;

class ResponsivenessController extends AbstractController
{
  protected $oldDeletedSpentTimes = [];

  protected $simpleView;

  public function initialize()
  {
    $this->simpleView =  new SimpleView();
    $this->simpleView->setDI($this->getDI());
  }

  public function responsivenessAction()
  {
    if ($this->request->isAjax()) {
      $interval = $this->getParam('interval');
      $endDate = date('Y-m-d');
      $readyItems = [];
      $intervalItem = '';
      $this->simpleView->setViewsDir($this->view->getViewsDir());

      switch ($interval) {
        case 7:
          $startDate = date('Y-m-d', strtotime('-7 days'));
          $previousStartDate = date('Y-m-d', strtotime('-14 days'));
          $previousEndDate = $startDate;
          $intervalItem = 'day';

          break;
        case 1:
          $thisMonthDate = new DateTime('first day of this month');
          $previousMonthDate = new DateTime('first day of previous month');

          $previousStartDate = $previousMonthDate->format('Y-m-d');
          $previousEndDate = date('Y-m-d', strtotime('-1 month'));
          $startDate = $thisMonthDate->format('Y-m-d');
          $intervalItem = 'day';

          break;
        case 28:
          $startDate = date('Y-m-d', strtotime('-28 days'));
          $previousStartDate = date('Y-m-d', strtotime('-56 days'));
          $previousEndDate = $startDate;
          $intervalItem = 'week';

          break;
        case 84:
          $buildInterval = $this->buildIntervalByChunk(84, $endDate, 21);
          $previousBuildInterval = $this->buildIntervalByChunk(84, $buildInterval[3]['start_date'], 21);
          $startDate = $buildInterval[3]['start_date'];
          $intervalItem = 'week';

          $currentCacheDataKeys = [
              'data_key' => 'interval_conversations',
              'last_end' => 'interval_last_end'
          ];
          $previousCacheDataKeys = [
              'data_key' => 'previous_conversations',
              'last_end' => 'previous_last_end'
          ];
          $breakdownCacheDataKeys = [
              'data_key' => 'breakdown_conversations',
              'last_end' => 'breakdown_last_end'
          ];

          break;
      }

      if (in_array($interval, [7, 1, 28])) {
        $previousConversations = Conversations::getByEndTime($previousStartDate, $previousEndDate, true);
        $conversations = Conversations::getByEndTime($startDate, $endDate, true);
        $breakdownConversations = Conversations::getByEndTime($startDate, $endDate);
      } else {
        $conversations = $this->getIntervalConversations($buildInterval, $currentCacheDataKeys, false, true);
        $previousConversations = $this->getIntervalConversations($previousBuildInterval, $previousCacheDataKeys, true, true);
        $breakdownConversations = $this->getIntervalConversations($buildInterval, $breakdownCacheDataKeys, false);
      }

      if (in_array($interval, [7, 1])) {
        $groupedSpentTimes = $this->groupByDays($conversations);
      } else {
        $groupedSpentTimes = $this->groupByWeeks($conversations, $interval / 7, $endDate);
      }

      $readyItems = $this->getReadyItems($previousConversations, $conversations, $groupedSpentTimes, $intervalItem);
      $readyItems['interval_dates'] = date('d.M.Y', strtotime($startDate)) . ' - ' . date('d.M.Y', strtotime($endDate));
      $readyItems['ttc_breakdown'] = $this->simpleView->render('responsiveness/responsiveness', [
          'breakdownConversations' => $this->groupForBreakdown($breakdownConversations)
      ]);

      return json_encode($readyItems);
    }

    exit(1);
  }

  /**
   * Суммирует spent time-ы тикетов и собирает по дату
   *
   * @param $groupedSpentTimes
   * @return array
   */
  private function getBuiltSpentTimes($groupedSpentTimes)
  {
    $buildConversations = [];

    foreach ($groupedSpentTimes as $date => $groupedSpentTime) {
      foreach ($groupedSpentTime as $spentTimes) {
        $buildConversations[$date][] = array_sum($spentTimes);
      }
    }

    return $buildConversations;
  }

  /**
   * @param $conversations
   * @return array
   */
  private function groupByDays($conversations)
  {
    $groupedSpentTimes = [];

    foreach ($conversations as $conversation) {
      $itemDate = date('Y-m-d', strtotime($conversation['end_time']));
      $groupedSpentTimes[$itemDate][$conversation['ticket_id']][] = $conversation['spent_time'];
    }

    ksort($groupedSpentTimes);

    return $groupedSpentTimes;
  }

  /**
   * @param $conversations
   * @param $countWeeks
   * @param $endDate
   * @return array
   */
  private function groupByWeeks($conversations, $countWeeks, $endDate)
  {
    $intervals = $this->buildIntervalByChunk($countWeeks * 7, $endDate, 7);
    $groupedSpentTimes = [];

    foreach ($conversations as $conversation) {
      $dateInterval = $this->checkForInIntervalAndGetDate($intervals, $conversation['end_time']);
      $groupedSpentTimes[$dateInterval][$conversation['ticket_id']][] = $conversation['spent_time'];
    }

    return $groupedSpentTimes;
  }

  /**
   * @param $spentTimes
   * @return float|int
   */
  private function getMedian($spentTimes)
  {
    sort($spentTimes);
    $countSpentTimes = count($spentTimes);

    if ($countSpentTimes % 2 == 0) {
      $median = ($spentTimes[($countSpentTimes / 2) - 1] + $spentTimes[($countSpentTimes / 2)]) / 2;
    } else {
      $median = $spentTimes[floor($countSpentTimes / 2)];
    }

    return $median;
  }

  /**
   * n дней делить на интервалы который разница между start_date и end_date равно на chunk дней
   *
   * @param $countDays
   * @param $endDate
   * @param $chunk
   * @return array
   */
  private function buildIntervalByChunk($countDays, $endDate, $chunk)
  {
    $intervals = [];
    $temporaryDate = $endDate;

    for ($i = $chunk; $i <= $countDays; $i+=$chunk) {
      $strTime = $endDate .' -' . $i . ' days';

      $intervals[] = [
        'start_date' => date('Y-m-d', strtotime($strTime)),
        'end_date' => $temporaryDate
      ];

      $temporaryDate = date('Y-m-d', strtotime($strTime));
    }

    return $intervals;
  }

  /**
   * Проверяет дату в интервале и возвращает интервал дату ввиде строки
   *
   * @param $intervals
   * @param $date
   * @return string
   */
  private function checkForInIntervalAndGetDate($intervals, $date)
  {
    $strIntervalDate = '';

    foreach ($intervals as $interval) {
      if (strtotime($interval['start_date']) < strtotime($date) && strtotime($interval['end_date']) > strtotime($date)) {
        $strIntervalDate = date('d M', strtotime($interval['start_date'])) . ' - ' . date('d M', strtotime($interval['end_date']));
      }
    }

    return $strIntervalDate;
  }

  /**
   * Строим массив для диаграмму
   *
   * @param $groupedSpentTimes
   * @param $interval
   * @return array
   */
  private function getBuiltData($groupedSpentTimes, $interval)
  {
    $buildSpentTimes = $this->getBuiltSpentTimes($groupedSpentTimes);
    $readyItems = [];

    foreach ($buildSpentTimes as $date => $spentTimes) {
      $readyItems[] = [
        'name' => ($interval === 'day') ? date('M d', strtotime($date)) : $date,
        'y' => $this->getMedian($spentTimes),
        'color' => '#FBC916'
      ];
    }

    return $readyItems;
  }

  /**
   * @param $buildInterval
   * @param $cacheKeys
   * @param bool $previous
   * @param bool $reply
   * @return array|mixed|null
   */
  private function getIntervalConversations($buildInterval, $cacheKeys, $previous = false, $reply = false)
  {
    $conversations = CacheApi::_()->getCache($cacheKeys['data_key'], 86400);
    $newIntervalStart = CacheApi::_()->getCache($cacheKeys['last_end'], 86400);

    if ($conversations && $newIntervalStart) {
      if (strtotime($newIntervalStart) < strtotime($buildInterval[0]['end_date'])) {
        $deletedSpentTimes = [];

        foreach ($conversations as $key => $conversation) {
          if (strtotime($conversation['end_time']) < strtotime($buildInterval[3]['start_date'])) {
            if (!$previous) {
              $deletedSpentTimes[] = $conversation;
            }

            unset($conversations[$key]);
          }
        }

        if ($previous) {
          $newTicketItems = $this->oldDeletedSpentTimes;
        } else {
          $newTicketItems = Conversations::getByEndTime($newIntervalStart, date('Y-m-d'), $reply);
          $this->oldDeletedSpentTimes = $deletedSpentTimes;
        }

        $conversations = array_merge($conversations, $newTicketItems);

        CacheApi::_()->setCache($cacheKeys['data_key'], $conversations, 86400);
        CacheApi::_()->setCache($cacheKeys['last_end'], $buildInterval[0]['end_date'], 86400);
      }
    } else {
      $conversations1 = Conversations::getByEndTime($buildInterval[0]['start_date'], $buildInterval[0]['end_date'], $reply);
      $conversations2 = Conversations::getByEndTime($buildInterval[1]['start_date'], $buildInterval[1]['end_date'], $reply);
      $conversations3 = Conversations::getByEndTime($buildInterval[2]['start_date'], $buildInterval[2]['end_date'], $reply);
      $conversations4 = Conversations::getByEndTime($buildInterval[3]['start_date'], $buildInterval[3]['end_date'], $reply);

      $conversations = array_merge($conversations4, $conversations3, $conversations2, $conversations1);
      CacheApi::_()->setCache($cacheKeys['data_key'], $conversations, 86400);
      CacheApi::_()->setCache($cacheKeys['last_end'], $buildInterval[0]['end_date'], 86400);
    }

    return $conversations;
  }

  /**
   * @param $previousConversations
   * @param $conversations
   * @param $groupedSpentTimes
   * @param $interval
   * @return array
   */
  private function getReadyItems($previousConversations, $conversations, $groupedSpentTimes, $interval)
  {
    $medianForPreviousAllInterval = $this->getMedianForAllInterval($previousConversations);
    $medianForAllInterval = $this->getMedianForAllInterval($conversations);

    $readyItems = [
      'median_all_interval' => $medianForAllInterval,
      'median_difference' => $medianForAllInterval - $medianForPreviousAllInterval,
      'medians' => $this->getBuiltData($groupedSpentTimes, $interval)
    ];

    return $readyItems;
  }

  /**
   * Возвращает медиану за полный интервал
   *
   * @param $conversations
   * @return float|int
   */
  private function getMedianForAllInterval($conversations)
  {
    $spentTimesForAllInterval = [];

    foreach ($conversations as $conversation) {
      $spentTimesForAllInterval[] = $conversation['spent_time'];
    }

    return $this->getMedian($spentTimesForAllInterval);
  }

  /**
   * @param $breakdownConversations
   * @return array
   */
  private function groupForBreakdown($breakdownConversations)
  {
    $groupedBreakdownSpentTimes = [];
    $groupedBreakdownConversations = [];
    $breakdownConversationsCount = count($breakdownConversations);

    foreach ($breakdownConversations as $breakdownConversation) {
      $key = $this->determineGroup($breakdownConversation['spent_time']);
      $groupedBreakdownSpentTimes[$key][] = $breakdownConversation['spent_time'];
    }

    foreach ($groupedBreakdownSpentTimes as $groupKey => $spentTimes) {
      $groupedBreakdownConversations[$groupKey] = [
          'percent' => round(count($spentTimes) / ($breakdownConversationsCount / 100), 2) . '%',
          'tooltip' => count($spentTimes)  . ' of ' . $breakdownConversationsCount . ' tickets'
      ];
    }

    return $groupedBreakdownConversations;
  }

  /**
   * @param $time
   * @return bool|mixed|string
   */
  private function determineGroup($time)
  {
    $groupKey = false;
    $timeIntervals = [
        ['max' => 300, 'min' => 0, 'group_key' => '< 5m'],
        ['max' => 1800, 'min' => 300, 'group_key' => '5m - 30m'],
        ['max' => 3600, 'min' => 1800, 'group_key' => '30m - 1h'],
        ['max' => 28800, 'min' => 3600, 'group_key' => '1h - 8h']
    ];

    foreach ($timeIntervals as $key => $timeInterval) {
      if ($time >= $timeInterval['min'] && $time < $timeInterval['max']) {
        $groupKey = $timeInterval['group_key'];
      }
    }

    if (!$groupKey) {
      $groupKey = '> 8h';
    }

    return $groupKey;
  }
}
