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
##  [?] File name: exampleplugin.php                  ##
##                                                    ##
##  [*] Author: debug <jtdroste@gmail.com>            ##
##  [*] Created: 5/26/2011                            ##
##  [*] Last edit: 6/1/2011                           ##
## ################################################## ##

namespace Plugins;
defined('Y_SO_MYSTERIOUS') or die('External script access is forbidden.');

use Mysterious\Bot\Kernal;
use Mysterious\Bot\Message;
use Mysterious\Bot\Plugin;
use Mysterious\Bot\Timer;

class ExamplePlugin extends Plugin {
	
	public function __initialize() {
		// Privmsg, all..
		$this->register_event('irc.privmsg', '!example', 'cmd_example');
		//$this->register_event('irc.privmsg', '/^!example/', 'cmd_example');
		
		// Privmsg, but as a PM
		$this->register_event('irc.privmsg.private', '!hello', 'cmd_hello');
		
		// Privmsg, but in channel
		$this->register_event('irc.privmsg.channel', '!test', 'cmd_test');
		
		// Catch all
		$this->register_event('irc.privmsg', 'catchall');
		
		// Kill the bot off
		$this->register_event('irc.privmsg', '!die', 'cmd_die');
		
		// Get lines sent
		$this->register_event('irc.privmsg', '!timertest', 'cmd_timertest');
		
		// Get lines sent
		$this->register_event('irc.privmsg', '!stats', 'cmd_stats');
		
		$this->register_help('!example', 'The help info for !example');
	}
	
	public function cmd_example() {
		$this->privmsg(Message::channel(), 'Hello!');
	}
	
	public function cmd_hello() {
		$this->privmsg(Message::channel(), 'Test from a PM!!');
	}
	
	public function cmd_test() {
		$this->privmsg(Message::channel(), 'Test from the channel!');
	}
	
	public function cmd_die() {
		$this->privmsg(Message::channel(), 'Shutting down in 5 seconds! BEEP BOOP BOO');
		Timer::register(5, function() { \Mysterious\Bot\Kernal::get_instance()->stop_loop(); }, array(), true);
	}
	
	public function cmd_stats() {
		$this->privmsg(Message::channel(), '[STATS] This bot has globally sent '.Kernal::get_instance()->lines_sent().' lines of data');
		$this->privmsg(Message::channel(), '[STATS] This bot has globally read '.Kernal::get_instance()->lines_read().' lines of data');
	}
	
	public function cmd_timertest() {
		$this->register_timer(10, 'cmd_timertest_finish', array(Message::channel()), true);
		$this->privmsg(Message::channel(), 'Registered new timer to tik in 10 seconds!');
	}
	
	public function cmd_timertest_finish($channel) {
		$this->privmsg($channel, 'Timer done!');
	}
	
	public function catchall() {
		if ( Message::nick() != $this->config('nick', null) && rand(1,10) == rand(1,10) )
			$this->privmsg(Message::channel(), strrev(Message::message()));
	}
}
