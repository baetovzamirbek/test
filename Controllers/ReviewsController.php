<?php

class ReviewsController extends AbstractController
{
  public function indexAction()
  {
    $this->view->setVar("pageTitle", "Reviews");

    $params = array(
      'page' => $this->getParam('page', 1),
      'ipp' => 20,
      'assign' => 'all',
      'status' => $this->getParam('status', ''),
      'keyword' => $this->getParam('keyword', ''),
      'find_reviews' => 'yes',
    );

    $paginator = Ticket::getTicketsPaginator($params);
    $selectedTickets = $paginator->items;

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

    $this->view->setVars(array(
      'priority_ticket' => $priority,
      'paginator' => $paginator,
      'params' => $params,
      'tickets' => $tickets,
    ));
  }
}