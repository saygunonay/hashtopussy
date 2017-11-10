<?php

use DBA\Agent;
use DBA\HashcatRelease;
use DBA\QueryFilter;

class HashcatHandler implements Handler {
  public function __construct($hashcatId = null) {
    //nothing
  }
  
  public function handle($action) {
    /** @var $LOGIN Login */
    global $LOGIN, $LANG;
    
    switch ($action) {
      case DHashcatAction::DELETE_RELEASE:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->delete();
        break;
      case DHashcatAction::CREATE_RELEASE:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->newHashcat();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private static function newHashcat() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    // new hashcat release
    $version = $_POST["version"];
    $url = $_POST["url"];
    $rootdir = $_POST["rootdir"];
    if (strlen($version) == 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashcat_specify_version'));
      return;
    }
    
    $hashcat = new HashcatRelease(0, $version, time(), $url, $rootdir);
    $hashcat = $FACTORIES::getHashcatReleaseFactory()->save($hashcat);
    if ($hashcat == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashcat_not_create'));
    }
    else {
      Util::createLogEntry("User", $LOGIN->getUserID(), DLogEntry::INFO, "New hashcat release was created: " . $version);
      header("Location: hashcat.php");
      die();
    }
  }
  
  private static function delete() {
    global $FACTORIES, $LANG;
    
    // delete hashcat release
    $release = $FACTORIES::getHashcatReleaseFactory()->get($_POST['release']);
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $qF = new QueryFilter(Agent::HC_VERSION, $release->getVersion(), "=");
    $agents = $FACTORIES::getAgentFactory()->filter(array($FACTORIES::FILTER => $qF));
    if (sizeof($agents)) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashcat_not_delete'));
      return;
    }
    $FACTORIES::getHashcatReleaseFactory()->delete($release);
    $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
    Util::refresh();
  }
}