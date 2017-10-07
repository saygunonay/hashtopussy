<?php

use DBA\Agent;
use DBA\NotificationSetting;
use DBA\QueryFilter;
use DBA\Session;
use DBA\User;

class UsersHandler implements Handler {
  public function __construct($userId = null) {
    //nothing to do
  }
  
  public function handle($action) {
    global $LANG;
    switch ($action) {
      case DUserAction::DELETE_USER:
        $this->delete();
        break;
      case DUserAction::ENABLE_USER:
        $this->enable();
        break;
      case DUserAction::DISABLE_USER:
        $this->disable();
        break;
      case DUserAction::SET_RIGHTS:
        $this->setRights();
        break;
      case DUserAction::SET_PASSWORD:
        $this->setPassword();
        break;
      case DUserAction::CREATE_USER:
        $this->create();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private function create() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $username = htmlentities($_POST['username'], ENT_QUOTES, "UTF-8");
    $email = $_POST['email'];
    $group = $FACTORIES::getRightGroupFactory()->get($_POST['group']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) == 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_email'));
      return;
    }
    else if (strlen($username) < 2) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_username_short'));
      return;
    }
    else if ($group == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_group'));
      return;
    }
    $qF = new QueryFilter("username", $username, "=");
    $res = $FACTORIES::getUserFactory()->filter(array($FACTORIES::FILTER => array($qF)));
    if ($res != null && sizeof($res) > 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_username_used'));
      return;
    }
    $newPass = Util::randomString(10);
    $newSalt = Util::randomString(20);
    $newHash = Encryption::passwordHash($newPass, $newSalt);
    $user = new User(0, $username, $email, $newHash, $newSalt, 1, 1, 0, time(), 600, $group->getId(), 0, "", "", "", "");
    $FACTORIES::getUserFactory()->save($user);
    //$tmpl = new Template("email.creation");
    //$obj = array('username' => $username, 'password' => $newPass, 'url' => $_SERVER[SERVER_NAME] . "/");
    //Util::sendMail($email, "Account at Hashtopussy", $tmpl->render($obj));
    //TODO: send proper email for created user
    
    Util::createLogEntry("User", $LOGIN->getUserID(), DLogEntry::INFO, "New User created: " . $user->getUsername());
    $payload = new DataSet(array(DPayloadKeys::USER => $user));
    NotificationHandler::checkNotifications(DNotificationType::USER_CREATED, $payload);
    
    header("Location: users.php");
    die();
  }
  
  private function setPassword() {
    /** @var Login $LOGIN */
    global $FACTORIES, $LOGIN, $LANG;
    
    $user = $FACTORIES::getUserFactory()->get($_POST['user']);
    if ($user == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_user'));
      return;
    }
    else if ($user->getId() == $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_change_own_password'));
      return;
    }
    
    $newSalt = Util::randomString(20);
    $newHash = Encryption::passwordHash($_POST['pass'], $newSalt);
    $user->setPasswordHash($newHash);
    $user->setPasswordSalt($newSalt);
    $user->setIsComputedPassword(0);
    $FACTORIES::getUserFactory()->update($user);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_users_password_updated_successfully'));
  }
  
  private function setRights() {
    /** @var Login $LOGIN */
    global $FACTORIES, $LOGIN, $LANG;
    
    $group = $FACTORIES::getRightGroupFactory()->get($_POST['group']);
    $user = $FACTORIES::getUserFactory()->get($_POST['user']);
    if ($user == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_user'));
      return;
    }
    else if ($group == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_group'));
      return;
    }
    else if ($user->getId() == $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_cannot_change_own_rights'));
      return;
    }
    $user->setRightGroupId($group->getId());
    $FACTORIES::getUserFactory()->update($user);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_users_updated_rights_successfully'));
  }
  
  private function disable() {
    /** @var Login $LOGIN */
    global $FACTORIES, $LOGIN, $LANG;
    
    $user = $FACTORIES::getUserFactory()->get($_POST['user']);
    if ($user == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_user'));
      return;
    }
    else if ($user->getId() == $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_cannot_disable_yourself'));
      return;
    }
    
    $qF = new QueryFilter(Session::USER_ID, $user->getId(), "=");
    $uS = new UpdateSet(Session::IS_OPEN, "0");
    $FACTORIES::getSessionFactory()->massUpdate(array($FACTORIES::FILTER => array($qF), $FACTORIES::UPDATE => array($uS)));
    $user->setIsValid(0);
    $FACTORIES::getUserFactory()->update($user);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_users_user_disabled_successfully'));
  }
  
  private function enable() {
    global $FACTORIES, $LANG;
    
    $user = $FACTORIES::getUserFactory()->get($_POST['user']);
    if ($user == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_user'));
      return;
    }
    
    $user->setIsValid(1);
    $FACTORIES::getUserFactory()->update($user);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_users_user_enabled_successfully'));
  }
  
  private function delete() {
    /** @var Login $LOGIN */
    global $FACTORIES, $LOGIN, $LANG;
    
    $user = $FACTORIES::getUserFactory()->get($_POST['user']);
    if ($user == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_invalid_user'));
      return;
    }
    else if ($user->getId() == $LOGIN->getUserID()) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_users_cannot_delete_yourself'));
      return;
    }
    
    $payload = new DataSet(array(DPayloadKeys::USER => $user));
    NotificationHandler::checkNotifications(DNotificationType::USER_DELETED, $payload);
    
    $qF = new QueryFilter(NotificationSetting::OBJECT_ID, $user->getId(), "=");
    $notifications = $FACTORIES::getNotificationSettingFactory()->filter(array($FACTORIES::FILTER => $qF));
    foreach ($notifications as $notification) {
      if (DNotificationType::getObjectType($notification->getAction()) == DNotificationObjectType::USER) {
        $FACTORIES::getNotificationSettingFactory()->delete($notification);
      }
    }
    
    $qF = new QueryFilter(Agent::USER_ID, $user->getId(), "=");
    $uS = new UpdateSet(Agent::USER_ID, null);
    $FACTORIES::getAgentFactory()->massUpdate(array($FACTORIES::FILTER => array($qF), $FACTORIES::UPDATE => array($uS)));
    $qF = new QueryFilter(Session::USER_ID, $user->getId(), "=");
    $FACTORIES::getSessionFactory()->massDeletion(array($FACTORIES::FILTER => array($qF)));
    $FACTORIES::getUserFactory()->delete($user);
    
    header("Location: users.php");
    die();
  }
}