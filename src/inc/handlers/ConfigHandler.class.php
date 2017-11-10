<?php

use DBA\Chunk;
use DBA\Config;
use DBA\ContainFilter;
use DBA\Hash;
use DBA\Hashlist;
use DBA\JoinFilter;
use DBA\QueryFilter;
use DBA\Task;
use DBA\TaskFile;

class ConfigHandler implements Handler {
  public function __construct($configId = null) {
    //we need nothing to load
  }
  
  public function handle($action) {
    global $LANG;
    switch ($action) {
      case DConfigAction::UPDATE_CONFIG:
        $this->updateConfig();
        break;
      case DConfigAction::REBUILD_CACHE:
        $this->rebuildCache();
        break;
      case DConfigAction::RESCAN_FILES:
        $this->scanFiles();
        break;
      case DConfigAction::CLEAR_ALL:
        $this->clearAll();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
  }
  
  private function clearAll() {
    /** @var $LOGIN Login */
    global $FACTORIES, $LOGIN;
    
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $FACTORIES::getHashFactory()->massDeletion(array());
    $FACTORIES::getHashBinaryFactory()->massDeletion(array());
    $FACTORIES::getAssignmentFactory()->massDeletion(array());
    $FACTORIES::getAgentErrorFactory()->massDeletion(array());
    $FACTORIES::getChunkFactory()->massDeletion(array());
    $FACTORIES::getZapFactory()->massDeletion(array());
    $qF = new QueryFilter(Task::HASHLIST_ID, null, "<>");
    $tasks = $FACTORIES::getTaskFactory()->filter(array($FACTORIES::FILTER => $qF));
    $taskIds = array();
    foreach ($tasks as $task) {
      $taskIds[] = $task->getId();
    }
    if (sizeof($taskIds) > 0) {
      $containFilter = new ContainFilter(TaskFile::TASK_ID, $taskIds);
      $FACTORIES::getTaskFileFactory()->massDeletion(array($FACTORIES::FILTER => $containFilter));
      $FACTORIES::getTaskFactory()->massDeletion(array($FACTORIES::FILTER => $qF));
    }
    $FACTORIES::getHashlistAgentFactory()->massDeletion(array());
    $FACTORIES::getHashlistFactory()->massDeletion(array());
    $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
    Util::createLogEntry("User", $LOGIN->getUserID(), DLogEntry::WARN, "Complete clear was executed!");
  }
  
  private function scanFiles() {
    global $FACTORIES, $LANG;
    
    $allOk = true;
    $files = $FACTORIES::getFileFactory()->filter(array());
    foreach ($files as $file) {
      $absolutePath = dirname(__FILE__) . "/../../files/" . $file->getFilename();
      if (!file_exists($absolutePath)) {
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_config_file_not_exist', [$file->getFilename()]));
        $allOk = false;
        continue;
      }
      $size = Util::filesize($absolutePath);
      if ($size == -1) {
        $allOk = false;
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_config_cannot_determine_filesize', [$file->getFilename()]));
      }
      else if ($size != $file->getSize()) {
        $allOk = false;
        UI::addMessage(UI::WARN, $LANG->get('handler_message_config_filesize_mismatch_corrected', [$file->getFilename()]));
        $file->setSize($size);
        $FACTORIES::getFileFactory()->update($file);
      }
    }
    if ($allOk) {
      UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_config_file_scan_successful'));
    }
  }
  
  private function rebuildCache() {
    global $FACTORIES, $LANG;
    
    $correctedChunks = 0;
    $correctedHashlists = 0;
    
    //check chunks
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $jF1 = new JoinFilter($FACTORIES::getTaskFactory(), Task::TASK_ID, Chunk::TASK_ID, $FACTORIES::getChunkFactory());
    $jF2 = new JoinFilter($FACTORIES::getHashlistFactory(), Hashlist::HASHLIST_ID, Task::HASHLIST_ID, $FACTORIES::getTaskFactory());
    $joined = $FACTORIES::getChunkFactory()->filter(array($FACTORIES::JOIN => array($jF1, $jF2)));
    for ($i = 0; $i < sizeof($joined[$FACTORIES::getChunkFactory()->getModelName()]); $i++) {
      /** @var $chunk Chunk */
      $chunk = $joined[$FACTORIES::getChunkFactory()->getModelName()][$i];
      /** @var $hashlist Hashlist */
      $hashlist = $joined[$FACTORIES::getHashlistFactory()->getModelName()][$i];
      $hashFactory = $FACTORIES::getHashFactory();
      if ($hashlist->getFormat() == DHashlistFormat::SUPERHASHLIST) {
        $hashlists = Util::checkSuperHashlist($hashlist);
        if ($hashlists[0]->getFormat() != DHashlistFormat::PLAIN) {
          $hashFactory = $FACTORIES::getHashBinaryFactory();
        }
      }
      $qF1 = new QueryFilter(Hash::CHUNK_ID, $chunk->getId(), "=");
      $qF2 = new QueryFilter(Hash::IS_CRACKED, "1", "=");
      $count = $hashFactory->countFilter(array($FACTORIES::FILTER => array($qF1, $qF2)));
      if ($count != $chunk->getCracked()) {
        $correctedChunks++;
        $chunk->setCracked($count);
        $FACTORIES::getChunkFactory()->update($chunk);
      }
    }
    $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
    
    //check hashlists
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $qF = new QueryFilter(Hashlist::FORMAT, DHashlistFormat::SUPERHASHLIST, "<>");
    $hashlists = $FACTORIES::getHashlistFactory()->filter(array($FACTORIES::FILTER => $qF));
    foreach ($hashlists as $hashlist) {
      $qF1 = new QueryFilter(Hash::HASHLIST_ID, $hashlist->getId(), "=");
      $qF2 = new QueryFilter(Hash::IS_CRACKED, "1", "=");
      $hashFactory = $FACTORIES::getHashFactory();
      if ($hashlist->getFormat() != DHashlistFormat::PLAIN) {
        $hashFactory = $FACTORIES::getHashBinaryFactory();
      }
      $count = $hashFactory->countFilter(array($FACTORIES::FILTER => array($qF1, $qF2)));
      if ($count != $hashlist->getCracked()) {
        $correctedHashlists++;
        $hashlist->setCracked($count);
        $FACTORIES::getHashlistFactory()->update($hashlist);
      }
    }
    $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
    
    //check superhashlists
    $FACTORIES::getAgentFactory()->getDB()->query("START TRANSACTION");
    $qF = new QueryFilter(Hashlist::FORMAT, DHashlistFormat::SUPERHASHLIST, "=");
    $hashlists = $FACTORIES::getHashlistFactory()->filter(array($FACTORIES::FILTER => $qF));
    foreach ($hashlists as $hashlist) {
      $children = Util::checkSuperHashlist($hashlist);
      $cracked = 0;
      foreach ($children as $child) {
        $cracked += $child->getCracked();
      }
      if ($cracked != $hashlist->getCracked()) {
        $correctedHashlists++;
        $hashlist->setCracked($cracked);
        $FACTORIES::getHashlistFactory()->update($hashlist);
      }
    }
    $FACTORIES::getAgentFactory()->getDB()->query("COMMIT");
    
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_config_updated_chunks_hashlists', [$correctedChunks, $correctedHashlists]));
  }
  
  private function updateConfig() {
    global $OBJECTS, $FACTORIES, $LANG;
    
    /** @var DataSet $CONFIG */
    $CONFIG = $OBJECTS['config'];
    foreach ($_POST as $item => $val) {
      if (substr($item, 0, 7) == "config_") {
        $name = substr($item, 7);
        $CONFIG->addValue($name, $val);
        $qF = new QueryFilter(Config::ITEM, $name, "=");
        $config = $FACTORIES::getConfigFactory()->filter(array($FACTORIES::FILTER => array($qF)), true);
        if ($config == null) {
          $config = new Config(0, $name, $val);
          $FACTORIES::getConfigFactory()->save($config);
        }
        else {
          $config->setValue($val);
          $FACTORIES::getConfigFactory()->update($config);
        }
      }
    }
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_config_updated'));
    $OBJECTS['config'] = $CONFIG;
  }
}