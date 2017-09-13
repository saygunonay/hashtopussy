<?php

require_once(dirname(__FILE__) . "/inc/load.php");

/** @var Login $LOGIN */
/** @var array $OBJECTS */

if (!$INSTALL) {
  header("Location: install/");
  die("Forward to <a href='install'>Install</a>");
}

$TEMPLATE = new Template("static/index");
$message = "";

if (isset($_GET['err'])) {
  $err = $_GET['err'];
  $time = substr($err, 1);
  if (time() - $time < 10) {
    switch ($err[0]) {
      case '1':
        $message = "<div class='alert alert-danger'>".$LANG->get('index_alert_description_invalid_form')."</div>";
        break;
      case '2':
        $message = "<div class='alert alert-danger'>".$LANG->get('index_alert_description_fill_both')."</div>";
        break;
      case '3':
        $message = "<div class='alert alert-danger'>".$LANG->get('index_alert_description_wrong_password')."</div>";
        break;
      case '4':
        $message = "<div class='alert alert-warning'>".$LANG->get('index_alert_description_need_logged')."</div>";
        break;
    }
  }
}
else if (isset($_GET['logout'])) {
  $logout = $_GET['logout'];
  $time = substr($logout, 1);
  if (time() - $time < 10) {
    $message = "<div class='alert alert-success'>".$LANG->get('index_alert_description_logged_out')."</div>";
  }
}

$OBJECTS['message'] = $message;
$fw = "";
if (isset($_GET['fw'])) {
  $fw = $_GET['fw'];
}
$OBJECTS['fw'] = $fw;

echo $TEMPLATE->render($OBJECTS);




