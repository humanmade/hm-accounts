<?php

/**
 * Use cookies instead of sessions.
 */
class Cookie_Facebook extends Facebook
{

  public function __construct($config) {
	BaseFacebook::__construct($config); // skip over session_start, etc.
  }

  /**
   * This implementation uses cookies to maintain
   * a store for authorization codes, user ids, CSRF states, and
   * access tokens.
   */
  protected function setPersistentData($key, $value) {
	if (!in_array($key, self::$kSupportedKeys)) {
	  self::errorLog('Unsupported key passed to setPersistentData.');
	  return;
	}

	$session_var_name = $this->constructSessionVariableName($key);
	setcookie($session_var_name, $value, time() + 3600, '/', '', true);
  }

  protected function getPersistentData($key, $default = false) {
	if (!in_array($key, self::$kSupportedKeys)) {
	  self::errorLog('Unsupported key passed to getPersistentData.');
	  return $default;
	}

	$session_var_name = $this->constructSessionVariableName($key);
	return isset($_COOKIE[$session_var_name]) ?
	  $_COOKIE[$session_var_name] : $default;
  }

  protected function clearPersistentData($key) {
	if (!in_array($key, self::$kSupportedKeys)) {
	  self::errorLog('Unsupported key passed to clearPersistentData.');
	  return;
	}

	$session_var_name = $this->constructSessionVariableName($key);
	if (isset($_COOKIE[$session_var_name])) {
	  unset($_COOKIE[$session_var_name]);
	}
  }

}
