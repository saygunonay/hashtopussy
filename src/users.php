<?php

require_once(dirname(__FILE__) . "/inc/load.php");

/** @var Login $LOGIN */
/** @var array $OBJECTS */

if (!$LOGIN->isLoggedin()) {
  header("Location: index.php?err=4" . time() . "&fw=" . urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']));
  die();
}
else if ($LOGIN->getLevel() < DAccessLevel::ADMINISTRATOR) {
  $TEMPLATE = new Template("errors/restricted");
  die($TEMPLATE->render($OBJECTS));
}

$TEMPLATE = new Template("users/index");
$MENU->setActive("users_list");

//catch actions here...
if (isset($_POST['action']) && Util::checkCSRF($_POST['csrf'])) {
  $usersHandler = new UsersHandler();
  $usersHandler->handle($_POST['action']);
  if (UI::getNumMessages() == 0) {
    Util::refresh();
  }
}

if (isset($_GET['new'])) {
  $TEMPLATE = new Template("users/new");
  $MENU->setActive("users_new");
  $groups = $FACTORIES::getRightGroupFactory()->filter(array());
  foreach ($groups as $group) {
    $group->setGroupName($LANG->get("user_group_name_" . $group->getId()));
  }
  $OBJECTS['groups'] = $groups;
}
else if (isset($_GET['id'])) {
  $user = $FACTORIES::getUserFactory()->get($_GET['id']);
  if ($user == null) {
    UI::printError("ERROR", "Invalid user!");
  }
  else {
    $OBJECTS['user'] = $user;
    $groups = $FACTORIES::getRightGroupFactory()->filter(array());
    foreach ($groups as $group) {
      $group->setGroupName($LANG->get("user_group_name_" . $group->getId()));
    }
    $OBJECTS['groups'] = $groups;
    $TEMPLATE = new Template("users/detail");
  }
}
else {
  $users = array();
  $res = $FACTORIES::getUserFactory()->filter(array());
  foreach ($res as $entry) {
    $set = new DataSet();
    $set->addValue('user', $entry);
    $group = $FACTORIES::getRightGroupFactory()->get($entry->getRightGroupId());
    $group->setGroupName($LANG->get("user_group_name_" . $group->getId()));
    $set->addValue('group', $group);
    $users[] = $set;
  }
  
  $OBJECTS['allUsers'] = $users;
  $OBJECTS['numUsers'] = sizeof($users);
}

echo $TEMPLATE->render($OBJECTS);




