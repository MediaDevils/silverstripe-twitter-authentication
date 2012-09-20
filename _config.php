<?php

if(!file_exists('Zend/Oauth.php')) {
	set_include_path(get_include_path() . PATH_SEPARATOR . (dirname(__FILE__)) . '/thirdparty');
}

define('TWITTER_PATH', dirname(__FILE__));

Object::add_extension('Member', 'TwitterIdentifier');

Authenticator::register_authenticator('TwitterAuthenticator');
