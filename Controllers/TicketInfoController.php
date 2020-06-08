<?php

class TicketInfoController extends AbstractController{
    public function updateInfoAction(){

        $shop_id = $this->request->getPost('shop_id');
        $action = $this->request->getPost('action');

        $ticket_info_model = new TicketInfo();
        $info = $ticket_info_model->updateInfo($shop_id,$action);

        return json_encode($info);
    }
}