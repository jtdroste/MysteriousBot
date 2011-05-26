<?php
## ################################################## ##
##                   MysteriousBot                    ##
## -------------------------------------------------- ##
##  [*] Package: MysteriousBot                        ##
##                                                    ##
##  [!] License: $LICENSE--------$                    ##
##  [!] Registered to: $DOMAIN----------------------$ ##
##  [!] Expires: $EXPIRES-$                           ##
##                                                    ##
##  [?] File name: botmanager.php                     ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/25/2011                            ##
##  [*] Last edit: 5/25/2011                          ##
## ################################################## ##

namespace Mysterious\Bot\IRC;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Singleton;

class BotManager extends Singleton {
	private $_bots = array();
	private $_sid2bot = array();
	private $_bot2sid = array();
	
	public function create_client($uuid, $settings) {
		if ( isset($this->_bots[$uuid]) ) throw new BotManagerError('Bot UUID '.$uuid.' is already set! Maybe it is not so unique?');
		
		$this->_bots[$uuid] = new Client($settings);
	}
	
	public function create_server($uuid, $settings) {
		if ( isset($this->_bots[$uuid]) ) throw new BotManagerError('Bot UUID '.$uuid.' is already set! Maybe it is not so unique?');
		
		throw new BotManagerError(__METHOD__ .' is not yet supported.');
	}
	
	public function set_sid($uuid, $sid) {
		if ( !isset($this->_bots[$uuid]) ) return false;
		
		$this->_sid2bot[$sid]  = $uuid;
		$this->_bot2sid[$uuid] = $sid;
		
		call_user_func(array($this->get_bot($this->_sid2bot[$sid]), 'set_sid'), $sid);
	}
	
	public function handle_read($sid, $raw) {
		if ( !isset($this->_sid2bot[$sid]) ) throw new BotManagerError('The SID provided is currently not tracked by the BotManager');
		
		// Lets parse the message
		$data = array(
			'raw' => $raw,
			'rawparts' => explode(' ', $raw),
			'socketid' => $sid
		);
		
		try {
			$parsed = Parser::new_instance($raw);
		} catch ( IRCParserException $e ) {
			Logger::get_instance()->warning(__FILE__, __LINE__, 'The IRC Parser threw an error! '.$e->getMessage());
			return;
		}
		$data = array_merge($data, $parsed);
		
		// Pass it to the bot. :)
		call_user_func(array($this->get_bot($this->_sid2bot[$sid]), 'on_raw'), $data);
	}
	
	public function get_bot($uuid) {
		return isset($this->_bots[$uuid]) ? $this->_bots[$uuid] : null;
	}
	
	public function destroy_bot($uuid) {
		unset($this->_bots[$uuid]);
		return true;
	}
}

class BotManagerError extends \Exception { };
