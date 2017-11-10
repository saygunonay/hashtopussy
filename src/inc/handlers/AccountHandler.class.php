<?php

class AccountHandler implements Handler {
  private $user;
  
  public function __construct($userId = null) {
    global $FACTORIES, $LANG;
    
    if ($userId == null) {
      $this->user = null;
      return;
    }
    
    $this->user = $FACTORIES::getUserFactory()->get($userId);
    if ($this->user == null) {
      UI::printError("FATAL", $LANG->get('handler_message_account_user_not_found', [$userId]));
    }
  }
  
  public function handle($action) {
    /** @var Login $LOGIN */
    global $LOGIN, $OBJECTS, $LANG;
    
    switch ($action) {
      case DAccountAction::SET_EMAIL:
        $this->setEmail();
        break;
      case DAccountAction::YUBIKEY_DISABLE:
        $this->setOTP(-1);
        break;
      case DAccountAction::YUBIKEY_ENABLE:
        $this->setOTP(0);
        break;
      case DAccountAction::SET_OTP1:
        $this->setOTP(1);
        break;
      case DAccountAction::SET_OTP2:
        $this->setOTP(2);
        break;
      case DAccountAction::SET_OTP3:
        $this->setOTP(3);
        break;
      case DAccountAction::SET_OTP4:
        $this->setOTP(4);
        break;
      case DAccountAction::UPDATE_LIFETIME:
        $this->updateLifetime();
        break;
      case DAccountAction::CHANGE_PASSWORD:
        $this->changePassword();
        break;
      default:
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_invalid_action'));
        break;
    }
    
    $LOGIN->setUser($this->user);
    $OBJECTS['user'] = $this->user;
  }
  
  private function changePassword() {
    global $FACTORIES, $LANG;
    
    $oldPassword = $_POST['oldpass'];
    $newPassword = $_POST['newpass'];
    $repeatedPassword = $_POST['reppass'];
    if (!Encryption::passwordVerify($oldPassword, $this->user->getPasswordSalt(), $this->user->getPasswordHash())) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_old_password_wrong'));
      return;
    }
    else if (strlen($newPassword) < 4) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_password_short'));
      return;
    }
    else if ($newPassword != $repeatedPassword) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_password_dont_match'));
      return;
    }
    
    $newSalt = Util::randomString(20);
    $newHash = Encryption::passwordHash($newPassword, $newSalt);
    $this->user->setPasswordHash($newHash);
    $this->user->setPasswordSalt($newSalt);
    $this->user->setIsComputedPassword(0);
    $FACTORIES::getUserFactory()->update($this->user);
    Util::createLogEntry(DLogEntryIssuer::USER, $this->user->getId(), DLogEntry::INFO, "User changed password!");
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_account_password_updated'));
  }
  
  private function updateLifetime() {
    global $FACTORIES, $LANG;
    
    $lifetime = intval($_POST['lifetime']);
    if ($lifetime < 60 || $lifetime > 48 * 3600) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_lifetime_error'));
      return;
    }
    
    $this->user->setSessionLifetime($lifetime);
    $FACTORIES::getUserFactory()->update($this->user);
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_account_lifetime_updated'));
  }
  
  private function setEmail() {
    global $FACTORIES, $LANG;
    
    $email = $_POST['email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_email_invalid'));
      return;
    }
    
    $this->user->setEmail($email);
    $FACTORIES::getUserFactory()->update($this->user);
    Util::createLogEntry(DLogEntryIssuer::USER, $this->user->getId(), DLogEntry::INFO, "User changed email!");
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_account_email_updated'));
  }
  
  private function checkOTP() {
    global $FACTORIES;
    
    $isValid = false;
    
    if (strlen($this->user->getOtp1()) == 12) {
      $isValid = true;
    }
    else if (strlen($this->user->getOtp2()) == 12) {
      $isValid = true;
    }
    else if (strlen($this->user->getOtp3()) == 12) {
      $isValid = true;
    }
    else if (strlen($this->user->getOtp4()) == 12) {
      $isValid = true;
    }
    if (!$isValid) {
      $this->user->setYubikey(0);
    }
    $FACTORIES::getUserFactory()->update($this->user);
  }
  
  private function setOTP($num) {
    global $FACTORIES, $LANG;
    
    if ($_POST['action'] == DAccountAction::YUBIKEY_ENABLE) {
      $isValid = false;
      
      if (strlen($this->user->getOtp1()) == 12) {
        $isValid = true;
      }
      else if (strlen($this->user->getOtp2()) == 12) {
        $isValid = true;
      }
      else if (strlen($this->user->getOtp3()) == 12) {
        $isValid = true;
      }
      else if (strlen($this->user->getOtp4()) == 12) {
        $isValid = true;
      }
      
      if (!$isValid) {
        UI::addMessage(UI::ERROR, $LANG->get('handler_message_account_otp_configure_first'));
        return;
      }
    }
    
    switch ($num) {
      case -1:
        $this->user->setYubikey(0);
        break;
      case 0:
        $this->user->setYubikey(1);
        break;
      case 1:
        $otp = $_POST['otp1'];
        $this->user->setOtp1(substr($otp, 0, 12));
        break;
      case 2:
        $otp = $_POST['otp2'];
        $this->user->setOtp2(substr($otp, 0, 12));
        break;
      case 3:
        $otp = $_POST['otp3'];
        $this->user->setOtp3(substr($otp, 0, 12));
        break;
      case 4:
        $otp = $_POST['otp4'];
        $this->user->setOtp4(substr($otp, 0, 12));
        break;
      default:
        return;
    }
    
    $this->checkOTP();
    
    $FACTORIES::getUserFactory()->update($this->user);
    Util::createLogEntry(DLogEntryIssuer::USER, $this->user->getId(), DLogEntry::INFO, "User changed OTP!");
    UI::addMessage(UI::SUCCESS, $LANG->get('handler_message_account_otp_updated'));
  }
}