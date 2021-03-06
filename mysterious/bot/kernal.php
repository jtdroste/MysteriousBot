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
##  [?] File name: kernal.php                         ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/23/2011                            ##
##  [*] Last edit: 6/17/2011                          ##
## ################################################## ##

namespace Mysterious\Bot;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Database\DB;
use Mysterious\Singleton;
use Mysterious\Bot\Socket;
use Mysterious\Bot\IRC\BotManager;

class Kernal extends Singleton {
	public $SOCKET_SID;
	public $HTTP_SID;
	public $init_complete = false;
	
	// Objects
	private $bot;
	private $sserver; // Socket Server
	private $xmpp;
	
	// Helpdocs
	private $_helpdocs = array();
	
	public function initialize() {
		// Are we even on the CLI?
		if ( !defined('IS_CLI') )
			throw new KernalError('Fatal error - Please run MysteriousBot from the command line!');
		
		$config = Config::get_instance();
		$logger = Logger::get_instance();
		$this->bot = BotManager::get_instance();
		$SM = Socket::get_instance();
		
		// Did they edit the "yes_i_edited_this" key?
		if ( $config->get('yes_i_edited_this') === false )
			throw new KernalError('Configuration error - Please change the key "yes_i_edited_this" to true! (Hint: Its at the bottom of the file)');
		
		// Now let's go on to setup the IRC Bot
		$setup = false;
		foreach ( $config->get('clients') AS $uuid => $settings ) {
			if ( $settings['enabled'] !== true ) continue;
			
			try {
				if ( $this->boot($uuid, $settings) )
					$setup = true;
			} catch ( KernalError $e ) {
				Logger::get_instance()->warning(__FILE__, __LINE__, 'Failed to boot bot '.$uuid.' - '.$e->getMessage());
			}
		}
		
		// Set up the XMPP Bot
		if ( $config->get('xmpp.enabled') === true ) {
			foreach ( array('domain', 'port', 'username', 'password') AS $setting ) {
				if ( $config->get('xmpp.'.$setting) === false || is_empty($config->get('xmpp.'.$setting)) ) {
					throw new KernalError('Configuration Error - XMPP requires setting '.$setting.' to be set, and not empty');
				}
			}
			
			$this->xmpp = XMPP::get_instance();
			
			try {
				$this->xmpp->setup();
				$host = $config->get('xmpp.host');
				
				$socketid = $SM->add_client($host, $config->get('xmpp.port'), false, array($this->xmpp, 'handle_read'), 'xmppbot');
				$this->xmpp->set_sid($socketid);
				
				$setup = true;
				$logger->info(__FILE__, __LINE__, 'XMPP bot booted up.');
			} catch ( XMPPError $e ) {
				Logger::get_instance()->warning(__FILE__, __LINE__, '[XMPP] '.$e->getMessage());
			}
		}
		
		// Did we spawn any clients?
		if ( $setup === false ) {
			throw new KernalError('No clients are spawned. Check the config?');
		}
		
		// Now lets start the socket server, if they enabled it
		if ( $config->get('socketserver.enabled') === true ) {
			$required_settings = array(
				'socketserver.ip', 'socketserver.port'
			);
			
			foreach ( $required_settings AS $setting ) {
				if ( $config->get($setting) === false || is_empty($config->get($setting)) ) {
					throw new KernalError('Configuration error - The setting "'.$setting.'" is not set/empty! Please double check the config file, edit fully, and read all comments and documentation!');
				}
			}
			
			SocketServer::get_instance()->setup();
			
			$ip   = $config->get('socketserver.ip');
			$port = $config->get('socketserver.port');
			$this->SOCKET_SID = $SM->add_listener($ip, $port, array(SocketServer::get_instance(), 'handle_read'), array(SocketServer::get_instance(), 'new_connection'), 'socketserver');
			$logger->info(__FILE__, __LINE__, 'SocketServer booted up. Listening for connections on '.$ip.':'.$port);
		}
		
		// Now lets start the HTTP server, if they enabled it
		if ( $config->get('httpserver.enabled') === true ) {
			$required_settings = array(
				'httpserver.ip', 'httpserver.port', 'httpserver.webroot'
			);
			
			foreach ( $required_settings AS $setting ) {
				if ( $config->get($setting) === false || is_empty($config->get($setting)) ) {
					throw new KernalError('Configuration error - The setting "'.$setting.'" is not set/empty! Please double check the config file, edit fully, and read all comments and documentation!');
				}
			}
			
			HTTPServer::get_instance()->setup();
			
			$ip   = $config->get('httpserver.ip');
			$port = $config->get('httpserver.port');
			$this->HTTP_SID = $SM->add_listener($ip, $port, array(HTTPServer::get_instance(), 'handle_read'), array(HTTPServer::get_instance(), 'new_connection'), 'httpserver', array('noexplode'=>true));
			$logger->info(__FILE__, __LINE__, 'HTTPServer booted up. Listening for connections on '.$ip.':'.$port);
		}
		
		// Start the plugin manager
		PluginManager::get_instance()->do_autoload();
		$this->init_complete = true;
		
		// Setup the DB Connection
		if ( $config->get('database.enabled') === true ) {
			require BASE_DIR.'database/db.php';
			DB::get_instance()->setup();
			
			$logger->info(__FILE__, __LINE__, 'Setup database server');
		}
		
		// Everything is ready!
		return $this;
	}
	
	public function boot($uuid, $settings) {
		$config = Config::get_instance();
		$logger = Logger::get_instance();
		
		// Get the required settings for each type, and verify the type.
		switch ( strtolower($settings['type']) ) {
			case 'client':
				$required_settings = array(
					'server', 'port', 'nick', 'ident', 'name'
				);
				$one_client = false;
				$func = 'create_client';
			break;
			
			case 'server':
				$required_settings = array(
					'server', 'port', 'linkpass', 'linkname', 'linkdesc'
				);
				$one_client = true;
				$func = 'create_server';
			break;
			
			default:
				throw new KernalError('Configuration error - Unknown type for client id '.$uuid.' (clients.'.$uuid.'.type)');
			break;
		}
		
		foreach ( $required_settings AS $setting ) {
			if ( $config->get('clients.'.$uuid.'.'.$setting) === false || is_empty($config->get('clients.'.$uuid.'.'.$setting)) ) {
				throw new KernalError('Configuration error - '.$settings['type'].' requires setting '.$setting.' to be set/not empty. (clients.'.$uuid.'.'.$setting.')');
			}
		}
		
		if ( $one_client === true ) {
			if ( !isset($settings['clients']) || count($settings['clients']) < 1 ) {
				throw new KernalError('Configuration error - '.$settings['type'].' requires atleast one client set in the sub-block of itself.');
			}
		}
		
		// We have SSL?
		if ( !isset($settings['ssl']) )
			$settings['ssl'] = false;
		
		// Create it!
		$this->bot->$func($uuid, $settings);
		
		// Now lets get the socket running.
		$socketid = Socket::get_instance()->add_client($settings['server'], $settings['port'], $settings['ssl'], array($this->bot, 'handle_read'), $uuid);
		$this->bot->set_sid($uuid, $socketid);
		
		$logger->info(__FILE__, __LINE__, 'Bot '.$uuid.' booted up');
		
		// Update XMPP Bot.
		if ( $config->get('xmpp.enabled', false) !== false ) {
			$xmppstream = XMPP\Stream::get_instance();
			
			if ( $xmppstream->status == XMPP\Stream::S_LISTENING )
				$xmppstream->update_presence('status', sprintf('Bot Status: Online | Connected Bots: %s', count(BotManager::get_instance()->_bots)));
		}
		
		// Autoload.
		if ( $this->init_complete === true && $config->get('clients.'.$uuid.'.plugins', false) !== false ) {
			foreach ( $config->get('clients.'.$uuid.'.plugins') AS $plugin )
				PluginManager::get_instance()->load_plugin($plugin, $uuid);
		}
		
		return true;
	}
	
	public function shutdown($uuid) {
		if ( $this->bot->get_bot($uuid) === false ) {
			throw new KernalError('Unknown Bot UUID!');
			return false;
		}
		
		Socket::get_instance()->close($this->bot->bot2sid($uuid));
		$this->bot->destroy_bot($uuid);
		Logger::get_instance()->info(__FILE__, __LINE__, 'Bot '.$uuid.' shutdown');
		
		// Update XMPP Bot.
		if ( Config::get_instance()->get('xmpp.enabled', false) !== false ) {
			$xmppstream = XMPP\Stream::get_instance();
			
			if ( $xmppstream->status == XMPP\Stream::S_LISTENING )
				$xmppstream->update_presence('status', sprintf('Bot Status: Online | Connected Bots: %s', count(BotManager::get_instance()->_bots)));
		}
		
		return true;
	}
	
	public function loop() {
		$SM = Socket::get_instance();
		$config = Config::get_instance();
		$host = $config->get('connection.server');
		$port = $config->get('connection.port');
		$ssl  = $config->get('connection.ssl');
		
		$SM->loop();
		
		return $this;
	}
	
	public function loop_finish() {
		Logger::get_instance()->fatal(__FILE__, __LINE__, 'Loop has finished! :(');
		
		return $this;
	}
	
	public function stop_loop() {
		Socket::get_instance()->stop_loop();
	}
	
	public function lines_sent() {
		return Socket::get_instance()->lines_sent;
	}
	
	public function lines_read() {
		return Socket::get_instance()->lines_read;
	}
	
	public function write($sid, $payload) {
		if ( empty($sid) )     return;
		if ( empty($payload) ) return;
		Socket::get_instance()->write($sid, $payload);
	}
	
	public function add_help($command, $doc) {
		$this->_helpdocs[$command] = $doc;
	}
	
	public function get_help($short=false, $maxlen=40) {
		if ( $short === false ) {
			return $this->_helpdocs;
		} else {
			$rtn = array();
			
			foreach ( $this->_helpdocs AS $k => $v )
				$rtn[$k] = substr($v, 0, $maxlen);
			
			return $rtn;
		}
	}
}

class KernalError extends \Exception { }

/*
 * empty() only works on vars, grr..
 * What a stupid hack, but its needed
 */
function is_empty($val) {
	return empty($val);
}
