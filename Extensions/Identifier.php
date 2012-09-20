<?php

class TwitterIdentifier extends DataObjectDecorator {
	public function extraStatics() {
		return array(
			'db' => array(
				'TwitterID' => 'Varchar',
				'TwitterHandle' => 'Varchar',
			)
		);
	}
	
	public function updateMemberFormFields(FieldSet &$fields) {
		$fields->removeByName('TwitterID');
		$fields->removeByName('TwitterHandle');
		
		if(Member::CurrentMember() && Member::CurrentMember()->exists()) {
			$fields->push($f = new ReadonlyField('TwitterButton', 'Twitter'));
			$f->dontEscape = true;
		} else {
			$fields->push(new HiddenField('TwitterButton', false));
		}
	}
	
	public function getTwitterButton() {
		if($this->owner->exists()) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
			Requirements::javascript('twitter/javascript/twitter.js');
			if($this->owner->TwitterID) {
				$token = SecurityToken::inst();
				$removeURL = Controller::join_links('TwitterCallback', 'RemoveTwitter');
				$removeURL = $token->addToUrl($removeURL);
				return 'Connected to Twitter account ' . $this->owner->TwitterHandle . '. <a href="' . $removeURL . '" id="RemoveTwitterButton">Disconnect</a>';
			} else {
				return '<img src="twitter/Images/connect.png" id="ConnectTwitterButton" alt="Connect to Twitter" />';
			}
		}
	}
}
