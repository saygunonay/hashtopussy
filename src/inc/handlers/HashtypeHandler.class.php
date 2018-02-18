<?php

use DBA\Hashlist;
use DBA\HashType;
use DBA\QueryFilter;

class HashtypeHandler implements Handler {
  public function __construct($hashtypeId = null) {
    //we need nothing to load
  }
  
  public function handle($action) {
    global $LANG;
    switch ($action) {
      case DHashtypeAction::DELETE_HASHTYPE:
        $this->delete();
        break;
      case DHashtypeAction::ADD_HASHTYPE:
        $this->add();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private function add() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $hashtype = $FACTORIES::getHashTypeFactory()->get($_POST['id']);
    if ($hashtype != null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashtype_hash_number_already_used'));
      return;
    }
    $desc = htmlentities($_POST['description'], ENT_QUOTES, "UTF-8");
    if (strlen($desc) == 0 || $_POST['id'] < 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_inputs'));
      return;
    }
    
    $salted = 0;
    if ($_POST['isSalted']) {
      $salted = 1;
    }
    
    $hashtype = new HashType($_POST['id'], $desc, $salted);
    if ($FACTORIES::getHashTypeFactory()->save($hashtype) == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashtype_failed_to_add'));
      return;
    }
    Util::createLogEntry("User", $LOGIN->getUserID(), DLogEntry::INFO, "New Hashtype added: " . $hashtype->getDescription());
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_hashtype_created_successfully'));
  }
  
  private function delete() {
    global $FACTORIES, $LANG;
    
    $hashtype = $FACTORIES::getHashTypeFactory()->get($_POST['type']);
    if ($hashtype == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashtype_invalid_hashtype'));
      return;
    }
    
    $qF = new QueryFilter(Hashlist::HASH_TYPE_ID, $hashtype->getId(), "=");
    $hashlists = $FACTORIES::getHashlistFactory()->filter(array($FACTORIES::FILTER => array($qF)));
    if (sizeof($hashlists) > 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_hashtype_cannot_delete_hashtype'));
      return;
    }
    
    $FACTORIES::getHashTypeFactory()->delete($hashtype);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_hashtype_deleted_successfully'));
  }
}
