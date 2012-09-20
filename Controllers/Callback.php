<?php

if(!file_exists('Zend/Oauth.php')) {
	// The autoloader can skip this if TwitterCallback is called before twitter/_config is included
	require_once dirname(dirname(__FILE__)) . '/_config.php';
}

require_once 'Zend/Oauth/Consumer.php';

class TwitterCallback extends Controller {
	
	private static $consumer_secret = null;
	private static $consumer_key = null;
	
	public static function set_consumer_secret($secret) {
		self::$consumer_secret = $secret;
	}
	
	public static function set_consumer_key($key) {
		self::$consumer_key = $key;
	}
	
	public static function get_consumer_key() {
		return self::$consumer_key;
	}

	private static $create_member = true;

	private static $member_groups = array();

	public static function set_create_member($bool) {
		self::$create_member = $bool;
	}

	public static function create_member() {
		return self::$create_member;
	}

	public static function set_member_groups($group) {
		if(is_array($group)) {
			self::$member_groups = $group;
		} else {
			self::$member_groups[] = $group;
		}
	}

	public static function get_member_groups() {
		return (count(self::$member_groups) > 0) ? self::$member_groups : false;
	}

	public static $allowed_actions = array(
		'Connect',
		'Login',
		'TwitterConnect',
		'FinishTwitter',
		'RemoveTwitter',
	);
	
	public function FinishTwitter($request) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		if($this->CurrentMember()->TwitterID) {
			return '<script type="text/javascript">//<![CDATA[
			opener.TwitterResponse(' . \Convert::raw2json(array(
				'handle' => $this->CurrentMember()->TwitterHandle,
				'removeLink' => $token->addToUrl($this->Link('RemoveTwitter')),
			)) . ');
			window.close();
			//]]></script>';
		} else {
			return '<script type="text/javascript">window.close();</script>';
		}
	}
	
	public function TwitterConnect() {
		return $this->connectUser($this->Link('FinishTwitter'));
	}
	
	public function RemoveTwitter($request) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		$m = $this->CurrentMember();
		$m->TwitterID = $m->TwitterHandle = null;
		$m->write();
	}
	
	public function __construct() {
		if(self::$consumer_secret == null || self::$consumer_key == null) {
			user_error('Cannot instigate a TwitterCallback object without a consumer secret and key', E_USER_ERROR);
		}
		parent::__construct();
	}
	
	public function connectUser($returnTo = '') {
		$token = SecurityToken::inst();
		if($returnTo) {
			$returnTo = $token->addToUrl($returnTo); 
			$returnTo = urlencode($returnTo);
		}
		$callback = $this->AbsoluteLink('Connect?ret=' . $returnTo);
		$callback = $token->addToUrl($callback);
		$config = array(
			'callbackUrl' => $callback,
			'consumerKey' => self::$consumer_key,
			'consumerSecret' => self::$consumer_secret,
			'siteUrl' => 'https://api.twitter.com/oauth',
			'authorizeUrl' => 'https://api.twitter.com/oauth/authorize'
		);
		$consumer = new Zend_Oauth_Consumer($config);
		$token = $consumer->getRequestToken();
		Session::set('Twitter.Request.Token', serialize($token));
		$url = $consumer->getRedirectUrl(array(
			'force_login' => 'true'
		));
		return self::curr()->redirect($url);
	}
	
	public function loginUser() {
		$token = SecurityToken::inst();
		$callback = $this->AbsoluteLink('Login');
		$callback = $token->addToUrl($callback);
		$config = array(
			'callbackUrl' => $callback,
			'consumerKey' => self::$consumer_key,
			'consumerSecret' => self::$consumer_secret,
			'siteUrl' => 'https://api.twitter.com/oauth',
			'authorizeUrl' => 'https://api.twitter.com/oauth/authorize'
		);
		$consumer = new Zend_Oauth_Consumer($config);
		$token = $consumer->getRequestToken();
		Session::set('Twitter.Request.Token', serialize($token));
		$url = $consumer->getRedirectUrl();
		return self::curr()->redirect($url);
	}
	
	public function index() {
		$this->httpError(403);
	}
	
	public function Login(SS_HTTPRequest $req) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);
		if($req->getVar('denied')) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', 'Login cancelled.');
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		$config = array(
			'consumerKey' => self::$consumer_key,
			'consumerSecret' => self::$consumer_secret,
			'siteUrl' => 'https://api.twitter.com/oauth',
			'authorizeUrl' => 'https://api.twitter.com/oauth/authorize'
		);
		$consumer = new Zend_Oauth_Consumer($config);
		$token = Session::get('Twitter.Request.Token');
		if(is_string($token)) {
			$token = unserialize($token);
		}
		try{
			$access = $consumer->getAccessToken($req->getVars(), $token);
			$client = $access->getHttpClient($config);
			$client->setUri('https://api.twitter.com/1/account/verify_credentials.json');
			$client->setMethod(Zend_Http_Client::GET);
			$client->setParameterGet('skip_status', 't');
			$response = $client->request();
			
			$data = $response->getBody();
			$data = json_decode($data);
			$id = $data->id;
		} catch(Exception $e) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', $e->getMessage());
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		if(!is_numeric($id)) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', 'Invalid user id received from Twitter.');
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		$u = DataObject::get_one('Member', '"TwitterID" = \'' . Convert::raw2sql($id) . '\'');
		if(!$u || !$u->exists()) {
			if(self::create_member()) {
				/** create new user **/
				$u = new Member();
				$u->FirstName = $data->screen_name;
				$u->TwitterID = $id;
				$u->write();
				if($groups = self::get_member_groups()) {
					foreach($groups as $group) {
						$u->addToGroupByCode($group);
					}
				}
			} else {
				Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', 'No one found for Twitter account ' . $data->screen_name . '.');
				Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
				return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
			}
		}
		if($u->TwitterHandle != $data->screen_name) {
			$u->TwitterHandle = $data->screen_name;
			$u->write();
		}
		$u->login(Session::get('SessionForms.TwitterLoginForm.Remember'));
		Session::clear('SessionForms.TwitterLoginForm.Remember');
		$backURL = Session::get('BackURL');
		Session::clear('BackURL');
		return $this->redirect($backURL);
	}
	
	public function Connect(SS_HTTPRequest $req) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);
		if($req->getVars() && !$req->getVar('denied') && Session::get('Twitter.Request.Token')) {
			$config = array(
				'consumerKey' => self::$consumer_key,
				'consumerSecret' => self::$consumer_secret,
				'siteUrl' => 'https://api.twitter.com/oauth',
				'authorizeUrl' => 'https://api.twitter.com/oauth/authorize'
			);
			$consumer = new Zend_Oauth_Consumer($config);
			$token = Session::get('Twitter.Request.Token');
			if(is_string($token)) {
				$token = unserialize($token);
			}
			try {
				$access = $consumer->getAccessToken($req->getVars(), $token);
				$client = $access->getHttpClient($config);
				$client->setUri('https://api.twitter.com/1/account/verify_credentials.json');
				$client->setMethod(Zend_Http_Client::GET);
				$client->setParameterGet('skip_status', 't');
				$response = $client->request();
				
				$data = $response->getBody();
				$data = json_decode($data);
				if($m = $this->CurrentMember()) {
					$m->TwitterID = $data->id;
					$m->TwitterHandle = $data->screen_name;
					$m->write();
				}
			} catch(Exception $e) {
				$this->httpError(500, $e->getMessage());
			}
		}
		Session::clear('Twitter.Request.Token');
		$ret = $req->getVar('ret');
		if($ret) {
			return $this->redirect($ret);
		} else {
			return $this->redirect(Director::baseURL());
		}
	}
	
	public function AbsoluteLink($action = null) {
		return Director::absoluteURL($this->Link($action));
	}
	
	public function Link($action = null) {
		return self::join_links('TwitterCallback', $action);
	}
}
