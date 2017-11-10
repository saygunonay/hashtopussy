<?php

use DBA\AgentBinary;
use DBA\QueryFilter;

class AgentBinaryHandler implements Handler {
  
  public function __construct($id = null) {
    //nothing
  }
  
  public function handle($action) {
    global $LANG;
    switch ($action) {
      case DAgentBinaryAction::NEW_BINARY:
        $this->newBinary();
        break;
      case DAgentBinaryAction::EDIT_BINARY:
        $this->editBinary();
        break;
      case DAgentBinaryAction::DELETE_BINARY:
        $this->deleteBinary();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private function deleteBinary() {
    global $FACTORIES, $LANG;
    
    $id = $_POST['id'];
    $agentBinary = $FACTORIES::getAgentBinaryFactory()->get($id);
    if ($agentBinary == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_doesnot_exist'));
      return;
    }
    $FACTORIES::getAgentBinaryFactory()->delete($agentBinary);
    unlink(dirname(__FILE__) . "/../../static/" . $agentBinary->getFilename());
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_agent_binary_deleted_successfully'));
  }
  
  private function editBinary() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $id = $_POST['id'];
    $type = $_POST['type'];
    $os = $_POST['os'];
    $filename = $_POST['filename'];
    $version = $_POST['version'];
    if (strlen($version) == 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_version_empty'));
      return;
    }
    else if (!file_exists(dirname(__FILE__) . "/../../static/$filename")) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_filename_doesnot_exist'));
      return;
    }
    $agentBinary = $FACTORIES::getAgentBinaryFactory()->get($id);
    if ($agentBinary == null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_doesnot_exist'));
      return;
    }
    
    $qF1 = new QueryFilter(AgentBinary::TYPE, $type, "=");
    $qF2 = new QueryFilter(AgentBinary::AGENT_BINARY_ID, $agentBinary->getId(), "<>");
    $result = $FACTORIES::getAgentBinaryFactory()->filter(array($FACTORIES::FILTER => array($qF1, $qF2)), true);
    if ($result != null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_cannot_have_same_type'));
      return;
    }
    
    $agentBinary->setType($type);
    $agentBinary->setOperatingSystems($os);
    $agentBinary->setFilename($filename);
    $agentBinary->setVersion($version);
    
    $FACTORIES::getAgentBinaryFactory()->update($agentBinary);
    Util::createLogEntry(DLogEntryIssuer::USER, $LOGIN->getUserID(), DLogEntry::INFO, "Binary " . $agentBinary->getFilename() . " was updated!");
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_agent_binary_updated_successfully'));
  }
  
  private function newBinary() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $type = $_POST['type'];
    $os = $_POST['os'];
    $filename = $_POST['filename'];
    $version = $_POST['version'];
    if (strlen($version) == 0) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_version_empty'));
      return;
    }
    else if (!file_exists(dirname(__FILE__) . "/../../static/$filename")) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_filename_doesnot_exist'));
      return;
    }
    $qF = new QueryFilter(AgentBinary::TYPE, $type, "=");
    $result = $FACTORIES::getAgentBinaryFactory()->filter(array($FACTORIES::FILTER => $qF), true);
    if ($result != null) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_binary_cannot_have_same_type'));
      return;
    }
    $agentBinary = new AgentBinary(0, $type, $version, $os, $filename);
    $FACTORIES::getAgentBinaryFactory()->save($agentBinary);
    Util::createLogEntry(DLogEntryIssuer::USER, $LOGIN->getUserID(), DLogEntry::INFO, "New Binary " . $agentBinary->getFilename() . " was added!");
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_agent_binary_added_successfully'));
  }
}