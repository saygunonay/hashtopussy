<?php

use DBA\Agent;
use DBA\AgentError;
use DBA\Assignment;
use DBA\Chunk;
use DBA\ContainFilter;
use DBA\Hash;
use DBA\HashBinary;
use DBA\HashlistAgent;
use DBA\NotificationSetting;
use DBA\QueryFilter;
use DBA\RegVoucher;

class AgentHandler implements Handler {
  private $agent;
  
  public function __construct($agentId = null) {
    global $FACTORIES, $LANG;
    
    if ($agentId == null) {
      $this->agent = null;
      return;
    }
    
    $this->agent = $FACTORIES::getAgentFactory()->get($agentId);
    if ($this->agent == null) {
      UI::printError("FATAL", $LANG->get('handler_message_agent_not_found', [$agentId]));
    }
  }
  
  public function handle($action) {
    /** @var Login $LOGIN */
    global $LOGIN, $LANG;
    
    switch ($action) {
      case DAgentAction::CLEAR_ERRORS:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->clearErrors();
        break;
      case DAgentAction::RENAME_AGENT:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER && $this->agent->getUserId() != $LOGIN->getUserID()) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->rename();
        break;
      case DAgentAction::SET_OWNER:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->changeOwner();
        break;
      case DAgentAction::SET_TRUSTED:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->changeTrusted();
        break;
      case DAgentAction::SET_IGNORE:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER && $this->agent->getUserId() != $LOGIN->getUserID()) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->changeIgnoreErrors();
        break;
      case DAgentAction::SET_PARAMETERS:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER && $this->agent->getUserId() != $LOGIN->getUserID()) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->changeCmdParameters();
        break;
      case DAgentAction::SET_ACTIVE:
        $this->toggleActive();
        break;
      case DAgentAction::DELETE_AGENT:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->delete();
        break;
      case DAgentAction::ASSIGN_AGENT:
        $this->assign();
        break;
      case DAgentAction::CREATE_VOUCHER:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->createVoucher();
        break;
      case DAgentAction::DELETE_VOUCHER:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->deleteVoucher();
        break;
      case DAgentAction::DOWNLOAD_AGENT:
        $this->downloadAgent();
        break;
      case DAgentAction::SET_CPU:
        if ($LOGIN->getLevel() < DAccessLevel::SUPERUSER && $this->agent->getUserId() != $LOGIN->getUserID()) {
          UI::printError("ERROR", $LANG->get('handler_message_no_rights'));
        }
        $this->setAgentCpu();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private function setAgentCpu() {
    global $FACTORIES;
    
    $cpuOnly = 0;
    if ($_POST['cpuOnly'] == 1) {
      $cpuOnly = 1;
    }
    $this->agent->setCpuOnly($cpuOnly);
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function downloadAgent() {
    global $FACTORIES, $binaryId, $LANG;
    
    $agentBinary = $FACTORIES::getAgentBinaryFactory()->get($binaryId);
    if ($agentBinary == null) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_binary_invalid'));
    }
    $filename = $agentBinary->getFilename();
    if (!file_exists(dirname(__FILE__) . "/../../static/" . $filename)) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_binary_not_present'));
    }
    header("Content-Type: application/force-download");
    header("Content-Description: " . $filename);
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    echo file_get_contents(dirname(__FILE__) . "/../../static/" . $filename);
    die();
  }
  
  private function assign() {
    global $FACTORIES, $LANG;
    
    if ($this->agent == null) {
      $this->agent = $FACTORIES::getAgentFactory()->get($_POST['agent']);
    }
    
    if (intval($_POST['task']) == 0) {
      //unassign
      $qF = new QueryFilter(Agent::AGENT_ID, $this->agent->getId(), "=");
      $FACTORIES::getAssignmentFactory()->massDeletion(array($FACTORIES::FILTER => array($qF)));
      if (isset($_GET['task'])) {
        header("Location: tasks.php?id=" . intval($_GET['task']));
        die();
      }
      return;
    }
    
    $this->agent = $FACTORIES::getAgentFactory()->get($_POST['agentId']);
    if ($this->agent == null) {
      $this->agent = $FACTORIES::getAgentFactory()->get($_POST['agent']);
    }
    if ($this->agent == null) {
      UI::printError("FATAL", $LANG->get('handler_message_agent_not_found', [ $_POST['agentId'] ]));
    }
    
    $task = $FACTORIES::getTaskFactory()->get(intval($_POST['task']));
    if (!$task) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_task'));
    }
    
    $qF = new QueryFilter(Assignment::TASK_ID, $task->getId(), "=");
    $assignments = $FACTORIES::getAssignmentFactory()->filter(array($FACTORIES::FILTER => $qF));
    if ($task->getIsSmall() && sizeof($assignments) > 0) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_assign_limit'));
    }
    
    $qF = new QueryFilter(Agent::AGENT_ID, $this->agent->getId(), "=");
    $assignments = $FACTORIES::getAssignmentFactory()->filter(array($FACTORIES::FILTER => array($qF)));
    
    $benchmark = 0;
    if (sizeof($assignments) > 0) {
      for ($i = 1; $i < sizeof($assignments); $i++) { // clean up if required
        $FACTORIES::getAssignmentFactory()->delete($assignments[$i]);
      }
      $assignment = $assignments[0];
      $assignment->setTaskId($task->getId());
      $assignment->setBenchmark($benchmark);
      $FACTORIES::getAssignmentFactory()->update($assignment);
    }
    else {
      $assignment = new Assignment(0, $task->getId(), $this->agent->getId(), $benchmark);
      $FACTORIES::getAssignmentFactory()->save($assignment);
    }
    if (isset($_GET['task'])) {
      header("Location: tasks.php?id=" . intval($_GET['task']));
      die();
    }
  }
  
  private function delete() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $this->agent = $FACTORIES::getAgentFactory()->get($_POST['agent']);
    if ($this->agent == null) {
      UI::printError("FATAL",  $LANG->get('handler_message_agent_not_found', [ $_POST['agent'] ]));
    }
    $name = $this->agent->getAgentName();
    $agent = $this->agent;
    
    $payload = new DataSet(array(DPayloadKeys::AGENT => $agent));
    NotificationHandler::checkNotifications(DNotificationType::DELETE_AGENT, $payload);
    
    if ($this->deleteDependencies($this->agent)) {
      $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
      Util::createLogEntry("User", $LOGIN->getUserID(), DLogEntry::INFO, "Agent " . $name . " got deleted.");
    }
    else {
      $FACTORIES::getAgentFactory()->getDB()->query("ROLLBACK");
      UI::printError("FATAL", $LANG->get('handler_message_agent_delete_error'));
    }
  }
  
  private function deleteVoucher() {
    global $FACTORIES;
    
    $voucher = $FACTORIES::getRegVoucherFactory()->get(intval($_POST["voucher"]));
    $FACTORIES::getRegVoucherFactory()->delete($voucher);
  }
  
  private function createVoucher() {
    global $FACTORIES;
    
    $key = htmlentities($_POST["newvoucher"], ENT_QUOTES, "UTF-8");
    $voucher = new RegVoucher(0, $key, time());
    $FACTORIES::getRegVoucherFactory()->save($voucher);
  }
  
  private function deleteDependencies($agent) {
    global $FACTORIES, $LANG;
    
    if ($agent == null) {
      $agent = $FACTORIES::getAgentFactory()->get($_POST['agent']);
      if ($agent == null) {
        UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_agent'));
      }
    }
    
    $qF = new QueryFilter(Assignment::AGENT_ID, $agent->getId(), "=");
    $FACTORIES::getAssignmentFactory()->massDeletion(array($FACTORIES::FILTER => $qF));
    $qF = new QueryFilter(NotificationSetting::OBJECT_ID, $agent->getId(), "=");
    $notifications = $FACTORIES::getNotificationSettingFactory()->filter(array($FACTORIES::FILTER => $qF));
    foreach ($notifications as $notification) {
      if (DNotificationType::getObjectType($notification->getAction()) == DNotificationObjectType::AGENT) {
        $FACTORIES::getNotificationSettingFactory()->delete($notification);
      }
    }
    $qF = new QueryFilter(AgentError::AGENT_ID, $agent->getId(), "=");
    $FACTORIES::getAgentErrorFactory()->massDeletion(array($FACTORIES::FILTER => $qF));
    $qF = new QueryFilter(HashlistAgent::AGENT_ID, $agent->getId(), "=");
    $FACTORIES::getHashlistAgentFactory()->massDeletion(array($FACTORIES::FILTER => $qF));
    //TODO: delete from Zap
    $uS = new UpdateSet(Chunk::CHUNK_ID, null);
    $chunks = $FACTORIES::getChunkFactory()->filter(array($FACTORIES::FILTER => $qF));
    $chunkIds = array();
    foreach ($chunks as $chunk) {
      $chunkIds[] = $chunk->getId();
    }
    if (sizeof($chunks) > 0) {
      $containFilter = new ContainFilter(Hash::CHUNK_ID, $chunkIds);
      $FACTORIES::getHashFactory()->massUpdate(array($FACTORIES::FILTER => $containFilter, $FACTORIES::UPDATE => $uS));
      $containFilter = new ContainFilter(HashBinary::CHUNK_ID, $chunkIds);
      $FACTORIES::getHashBinaryFactory()->massUpdate(array($FACTORIES::FILTER => $containFilter, $FACTORIES::UPDATE => $uS));
      $uS = new UpdateSet(Chunk::AGENT_ID, null);
      $FACTORIES::getChunkFactory()->massUpdate(array($FACTORIES::FILTER => $qF, $FACTORIES::UPDATE => $uS));
    }
    $FACTORIES::getAgentFactory()->delete($agent);
    return true;
  }
  
  private function toggleActive() {
    global $FACTORIES, $LANG;
    
    if ($this->agent == null) {
      $this->agent = $FACTORIES::getAgentFactory()->get($_POST['agent']);
      if ($this->agent == null) {
        UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_agent'));
      }
    }
    
    if ($this->agent->getIsActive() == 1) {
      $this->agent->setIsActive(0);
    }
    else {
      $this->agent->setIsActive(1);
    }
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function changeCmdParameters() {
    global $FACTORIES, $LANG;
    
    $pars = htmlentities($_POST["cmdpars"], ENT_QUOTES, "UTF-8");
    
    if (Util::containsBlacklistedChars($pars)) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_agent_no_blacklisted_characters'));
      return;
    }
    $this->agent->setCmdPars($pars);
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function changeIgnoreErrors() {
    global $FACTORIES, $LANG;
    
    $ignore = intval($_POST["ignore"]);
    if ($ignore != 0 && $ignore != 1) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_ignore_state'));
    }
    $this->agent->setIgnoreErrors($ignore);
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function changeTrusted() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    $trusted = intval($_POST["trusted"]);
    if ($trusted != 0 && $trusted != 1) {
      UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_trusted_state'));
    }
    $this->agent->setIsTrusted($trusted);
    Util::createLogEntry(DLogEntryIssuer::USER, $LOGIN->getUserID(), DLogEntry::INFO, "Trust status for agent " . $this->agent->getAgentName() . " was changed to " . $this->agent->getIsTrusted());
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function changeOwner() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN, $LANG;
    
    if ($_POST['owner'] == 0) {
      $this->agent->setUserId(null);
      $username = "NONE";
      $FACTORIES::getAgentFactory()->update($this->agent);
    }
    else {
      $user = $FACTORIES::getUserFactory()->get(intval($_POST["owner"]));
      if (!$user) {
        UI::printError("ERROR", $LANG->get('handler_message_agent_invalid_user_selected'));
      }
      $username = $user->getUsername();
      $this->agent->setUserId($user->getId());
    }
    Util::createLogEntry(DLogEntryIssuer::USER, $LOGIN->getUserID(), DLogEntry::INFO, "Owner for agent " . $this->agent->getAgentName() . " was changed to " . $username);
    $FACTORIES::getAgentFactory()->update($this->agent);
  }
  
  private function clearErrors() {
    global $FACTORIES;
    
    $qF = new QueryFilter(AgentError::AGENT_ID, $this->agent->getId(), "=");
    $FACTORIES::getAgentErrorFactory()->massDeletion(array($FACTORIES::FILTER => array($qF)));
  }
  
  private function rename() {
    global $FACTORIES;
    
    $name = htmlentities($_POST['name'], ENT_QUOTES, "UTF-8");
    if (strlen($name) > 0) {
      $this->agent->setAgentName($name);
      $FACTORIES::getAgentFactory()->update($this->agent);
    }
  }
}