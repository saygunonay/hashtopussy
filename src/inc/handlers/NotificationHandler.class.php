<?php

use DBA\NotificationSetting;
use DBA\QueryFilter;

class NotificationHandler implements Handler {
  
  public function __construct($id = null) {
    // nothing required here
  }
  
  public function handle($action) {
    global $LANG;
    switch ($action) {
      case DNotificationAction::CREATE_NOTIFICATION:
        $this->create();
        break;
      case DNotificationAction::SET_ACTIVE:
        $this->toggleActive();
        break;
      case DNotificationAction::DELETE_NOTIFICATION:
        $this->delete();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  /**
   * @param $action
   * @param $payload DataSet
   */
  public static function checkNotifications($action, $payload) {
    /** @var $NOTIFICATIONS HashtopussyNotification[] */
    global $FACTORIES, $NOTIFICATIONS;
    
    $qF1 = new QueryFilter(NotificationSetting::ACTION, $action, "=");
    $qF2 = new QueryFilter(NotificationSetting::IS_ACTIVE, "1", "=");
    $notifications = $FACTORIES::getNotificationSettingFactory()->filter(array($FACTORIES::FILTER => array($qF1, $qF2)));
    foreach ($notifications as $notification) {
      if ($notification->getObjectId() != null) {
        $obj = 0;
        switch (DNotificationType::getObjectType($notification->getAction())) {
          case DNotificationObjectType::USER:
            $obj = $payload->getVal(DPayloadKeys::USER)->getId();
            break;
          case DNotificationObjectType::AGENT:
            $obj = $payload->getVal(DPayloadKeys::AGENT)->getId();
            break;
          case DNotificationObjectType::HASHLIST:
            $obj = $payload->getVal(DPayloadKeys::HASHLIST)->getId();
            break;
          case DNotificationObjectType::TASK:
            $obj = $payload->getVal(DPayloadKeys::TASK)->getId();
            break;
        }
        if ($obj == 0 || $obj != $notification->getObjectId()) {
          continue;
        }
      }
      if ($action == DNotificationType::OWN_AGENT_ERROR) {
        if ($payload->getVal(DPayloadKeys::AGENT)->getUserId() != $notification->getUserId()) {
          continue;
        }
      }
      $NOTIFICATIONS[$notification->getNotification()]->execute($action, $payload, $notification);
    }
  }
  
  private function delete() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $notification = $FACTORIES::getNotificationSettingFactory()->get($_POST['notification']);
    if ($notification == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_found'));
      return;
    }
    else if ($notification->getUserId() != $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_allowed_delete'));
      return;
    }
    $FACTORIES::getNotificationSettingFactory()->delete($notification);
  }
  
  private function toggleActive() {
    /** @var Login $LOGIN */
    global $FACTORIES, $LOGIN, $LANG;
    
    $notification = $FACTORIES::getNotificationSettingFactory()->get($_POST['notification']);
    if ($notification == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_found'));
      return;
    }
    else if ($notification->getUserId() != $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_no_access'));
      return;
    }
    if ($notification->getIsActive() == 1) {
      $notification->setIsActive(0);
    }
    else {
      $notification->setIsActive(1);
    }
    $FACTORIES::getNotificationSettingFactory()->update($notification);
  }
  
  private function create() {
    /** @var Login $LOGIN */
    global $FACTORIES, $NOTIFICATIONS, $LOGIN, $LANG;
    
    $actionType = $_POST['actionType'];
    $notification = $_POST['notification'];
    $receiver = trim($_POST['receiver']);
    
    if (!isset($NOTIFICATIONS[$notification])) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_available'));
      return;
    }
    else if (!in_array($actionType, DNotificationType::getAll())) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_actiontype_not_available'));
      return;
    }
    else if (strlen($receiver) == 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_fill_receiver'));
      return;
    }
    else if (DNotificationType::getRequiredLevel($actionType) > $LOGIN->getLevel()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_allowed_actiontype'));
      return;
    }
    $objectId = null;
    switch (DNotificationType::getObjectType($actionType)) {
      case DNotificationObjectType::USER:
        if ($LOGIN->getLevel() < DAccessLevel::ADMINISTRATOR) {
          UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_not_allowed_user_actiontypes'));
          return;
        }
        if ($_POST['users'] == "ALL") {
          break;
        }
        $user = $FACTORIES::getUserFactory()->get($_POST['users']);
        if ($user == null) {
          UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_invalid_user'));
          return;
        }
        $objectId = $user->getId();
        break;
      case DNotificationObjectType::AGENT:
        if ($_POST['agents'] == "ALL") {
          break;
        }
        $agent = $FACTORIES::getAgentFactory()->get($_POST['agents']);
        if ($agent == null) {
          UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_invalid_agent'));
          return;
        }
        $objectId = $agent->getId();
        break;
      case DNotificationObjectType::HASHLIST:
        if ($_POST['hashlists'] == "ALL") {
          break;
        }
        $hashlist = $FACTORIES::getHashlistFactory()->get($_POST['hashlists']);
        if ($hashlist == null) {
          UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_invalid_hashlist'));
          return;
        }
        $objectId = $hashlist->getId();
        break;
      case DNotificationObjectType::TASK:
        if ($_POST['tasks'] == "ALL") {
          break;
        }
        $task = $FACTORIES::getTaskFactory()->get($_POST['tasks']);
        if ($task == null) {
          UI::addMessage(UI::ERROR, $LANG->get('handler_message_notification_invalid_task'));
          return;
        }
        $objectId = $task->getId();
        break;
    }
    
    $notificationSetting = new NotificationSetting(0, $actionType, $objectId, $notification, $LOGIN->getUserID(), $receiver, 1);
    $FACTORIES::getNotificationSettingFactory()->save($notificationSetting);
  }
}