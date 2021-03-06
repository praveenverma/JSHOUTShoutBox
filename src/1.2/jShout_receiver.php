<?php

require ("jShout_config.php");

class jShout implements cfg {
	
	function __construct($cookie, $POST_DATA = null) {
		$this->data = $POST_DATA;
		$this->_start_time = microtime ( true );
		$this->cookie = $cookie;
		$this->link = $this->openDB ();
		$pf = time ();
		setcookie ( "jShout_flood_protect", $pf );
		$this->flood = $_COOKIE ['jShout_flood_protect'];
		if (is_null ( $POST_DATA )) {
			echo "<!-- Refresh {$this->_start_time} || Flood Protect: {$pf} -->";
		} else {
			$this->process_shout ( $POST_DATA, false );
		}
	
	}
	
	function v3_list_msg_manual() {
		$data = $this->data;
		if ($this->get_auth ( $this->cookie )) {
			$result = mysql_query ( cfg::v3_msg_query );
			while ( $row = mysql_fetch_array ( $result ) ) {
				$data [] = array (
					0 => $row [0], 1 => $row [1], 2 => $row [2], 3 => $row [3] 
				);
			}
			if (! is_array ( $data )) {
				$this->sys_msg ( "Error getting data array." );
			} else {
				foreach ( $data as $key => $value ) {
					if ($value [3] == "j-Shout") {
						print ("<!-- System Message Skipped -->") ;
					} else {
						print ("
							<div id='shout_msg_container'>
							<div id='shout_avatar_container'></div>
							<div id='shout_stamp_container'><em>{$value[2]}</em></div>
							<div id='shout_user_container'><em>{$value[3]}</em></div>
							<div id='shout_message'>
							<div id='shout_message_text'>{$value[1]}</div>
							</div></div>
							") ;
					}
				}
			
			}
		} else {
			$this->sys_msg ( "You are not logged in." );
		}
	}
	
	function sys_msg($msg) {
		print ("<B>j-Shout</b>: &nbsp;&nbsp;" . $msg) ;
		$sql = "INSERT INTO jshout VALUES (NULL, '{$msg}', CURRENT_TIMESTAMP, 'j-Shout', '0')";
		mysql_query ( $sql ) or die ( mysql_error () );
	
	}
	
	function openDB() {
		if (mysql_connect ( cfg::db_host, cfg::db_user, cfg::db_pass )) {
			mysql_select_db ( cfg::db_name );
			return true;
		} else {
			return false;
		}
		
		return null;
	}
	
	function process_shout($POST_DATA, $action = false) {
		if (cfg::enabled) {
			if (is_array ( $POST_DATA ) && in_array ( "v_01", $POST_DATA )) {
				if (! empty ( $POST_DATA ['jshout'] ) && $POST_DATA ['jshout'] == "v_01") {
					if ($this->link) {
						if (@$POST_DATA ['req'] == "add_shout") {
							$shout = strip_tags ( @$POST_DATA ['sb'] );
							$clean = mysql_real_escape_string ( $shout );
							if (cfg::allow_switches) {
								
								$action = $this->process_switch ( $clean );
								
								if ($this->inStr ( "/pvt", $clean )) {
									$this->send_private ( $clean );
									$action = false;
									$clean = '';
								}
								
								if ($this->inStr ( "/ping", $clean )) {
									$this->ping ( $clean );
									$action = false;
								}
								
								if ($this->inStr ( "/nslook", $clean )) {
									$this->nslookup ( $this->params ( $clean, 1 ) );
									$action = false;
								}
								
								if ($this->inStr ( "/sha1", $clean )) {
									sha1 ( $this->params ( $clean, 1 ) );
									$action = false;
								}
								
								if ($this->inStr ( "/register", $clean )) {
									$this->register ( $clean );
									$action = false;
								}
								
								if ($this->inStr ( "/login", $clean )) {
									$this->login ( $clean );
									$action = false;
								}
								
								if ($this->inStr ( "http://", $clean ) || $this->inStr ( "https://", $clean )) {
									$this->link ( $clean );
									$action = false;
								}
								
								if ($action) {
									$this->add_shout ( $clean );
								}
							
							} else {
								$this->sys_msg ( "Command line switches disabled by admin" );
							}
						
						}
					
					} else {
						print ("failed to connect") ;
						die ();
					}
				}
			} else {
				print_r ( $POST_DATA );
			}
		} else {
			$this->sys_msg ( "*** SYSTEM OFFLINE ***" );
		}
	}
	
	function m8() {
		$result = mysql_query ( "SELECT * FROM jshout " );
		while ( $row = mysql_fetch_array ( $result ) ) {
			$tip [] = $row [3];
		}
		
		$tip_num = rand ( 0, count ( $tip ) + 1 );
		$clean = $tip [$tip_num];
		
		if ($clean == '') {
			$tip_num = rand ( 0, count ( $tip ) + 1 );
			$clean = $tip [$tip_num];
		}
		return $clean . "({$tip_num})";
	}
	
	function inStr($needle, $haystack) {
		$needlechars = strlen ( $needle );
		$i = 0;
		for($i = 0; $i < strlen ( $haystack ); $i ++) {
			if (substr ( $haystack, $i, $needlechars ) == $needle) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	function nslookup($ip) {
		exec ( 'nslookup ' . $ip, $op );
		if (substr ( php_uname (), 0, 7 ) == "Windows") {
			return substr ( $op [3], 6 );
		} else {
			if (strpos ( $op [4], 'name = ' ) > 0)
				return substr ( $op [4], strpos ( $op [4], 'name =' ) + 7, - 1 );
			else
				return substr ( $op [4], strpos ( $op [4], 'Name:' ) + 6 );
		}
	}
	
	function process_switch($clean) {
		switch ($clean) {
			case "/date" :
				$this->add_shout ( date ( 'l jS \of F Y h:i:s A' ) );
				return false;
			case "/vpc" :
				$this->add_shout ( " =(*,_,*)=" );
				return false;
			case "/m8" :
				$this->add_shout ( $this->m8 () );
				return false;
			case "/logout" :
				mysql_query ( "UPDATE jshout_user SET auth='000' WHERE user='{$this->cookie}'" );
				setcookie ( "jShout", '' );
				setcookie ( "jShout_auth", '' );
				$this->sys_msg ( "Logged out, see ya later {$this->cookie}" );
				return false;
			case "/clear" :
				$clean = $this->clear ( $clean );
				return false;
			case "/troll" :
				$this->add_shout ( "really?" );
				return false;
			default :
				return true;
		
		}
	}
	
	function ping($clean) {
		if (cfg::allow_ping) {
			$addr = explode ( " ", $clean );
			$cnt = count ( $addr );
			$addr [1] = mysql_real_escape_string ( $addr [1] );
			if ($addr [0] != '/ping') {
			
			} else {
				$exec = exec ( "ping -n 1 " . $addr [1], $ret );
				$clean = '';
				$x = 0;
				foreach ( $ret as $response ) {
					if ($x == 0) {
						$x ++;
						continue;
					}
					if ($x == 2) {
						$clean .= $response . "<br>";
					}
					$x ++;
				}
				if ($clean == '') {
					$clean = $this->err ( "Invalid host address, try again." );
				}
			}
		
		} else {
			$this->add_shout ( $this->err ( "Ping is disabled by admin" ) );
			return null;
		}
		$this->sys_msg ( $clean );
		return null;
	}
	function params($clean, $int) {
		$out = explode ( " ", $clean );
		return $out [$int];
	}
	
	function send_private($clean) {
		if ($this->get_auth ( $this->cookie )) {
			$strInput = explode ( " ", $clean );
			if ($strInput [0] != "/pvt") {
				$this->add_shout ( $clean );
				exit ();
			} else {
				$cnt = count ( $strInput );
				$strInput [1] = mysql_real_escape_string ( $strInput [1] );
				$strInput [2] = mysql_real_escape_string ( $strInput [2] );
				$strInput [3] = mysql_real_escape_string ( $strInput [3] );
				if ($cnt >= 3) {
					$sql = mysql_query ( "SELECT user FROM jshout_user WHERE user='{$strInput[1]}'" );
					$dbu = mysql_fetch_row ( $sql );
					if ($dbu [0] == $strInput [1]) {
						$clean = "Message sent to: {$strInput[1]}";
						mysql_query ( "INSERT INTO jshout_private VALUES (NULL, '{$strInput[2]} {$strInput[3]} {$strInput[4]} {$strInput[5]} {$strInput[6]}', '{$this->cookie}', '{$strInput[1]}', CURRENT_TIMESTAMP)" );
					} else {
						$clean = $this->err ( "User not found <B>{$strInput[1]}</B>, message not sent. Try checking case." );
					}
				} else {
					$clean = $this->err ( "Missing Message Params." );
				}
			}
			$this->sys_msg ( $clean );
			return null;
		} else {
			$this->sys_msg ( "You must be logged in to send private messages" );
			return null;
		}
	
	}
	
	function register($clean) {
		$strInput = explode ( " ", $clean );
		if ($strInput [0] != "/register") {
			$clean = $clean;
		} else {
			$cnt = count ( $strInput );
			$strInput [1] = mysql_real_escape_string ( $strInput [1] );
			$strInput [2] = mysql_real_escape_string ( $strInput [2] );
			if ($cnt >= 3) {
				$sql = mysql_query ( "SELECT user FROM jshout_user WHERE user='{$strInput[1]}'" );
				$dbu = mysql_fetch_row ( $sql );
				if ($dbu [0] == $strInput [1]) {
					$clean = $this->err ( "Registration failed, user already in use." );
				} else {
					$clean = "<font color=\"#060\">User Registered ({$strInput[1]})</font>";
					setcookie ( "jShout_id", sha1 ( $strInput [2] ) );
					$pass = sha1 ( $strInput [2] );
					setcookie ( "jShout_user", $strInput [1] );
					mysql_query ( "INSERT INTO jshout_user VALUES (NULL, '{$strInput[1]}', '{$pass}', '0')" );
				}
			} else {
				$clean = $this->err ( "Missing Registration Params." );
			}
		}
		$this->sys_msg ( $clean );
		return null;
	}
	
	function err($msg) {
		$font_color = cfg::err_color;
		$out = "<font color=\"{$font_color}\">{$msg}</font>";
		return $out;
	}
	
	function login($clean) {
		$user = explode ( " ", $clean );
		if (count ( $user ) >= 3) {
			$user [0] = "Login";
			$user [1] = mysql_real_escape_string ( $user [1] );
			$user [2] = mysql_real_escape_string ( $user [2] );
			$pass = sha1 ( $user [2] );
			
			$sql = mysql_query ( "SELECT id,user,pass FROM jshout_user WHERE user='{$user[1]}' AND pass='{$pass}'" );
			$udata = mysql_fetch_row ( $sql );
			
			if ($udata [0] == '') {
				$clean = $this->err ( "Login failed, Invalid Credentials" );
			} elseif ($udata [1] == $user [1] && $udata [2] == $pass) {
				$clean = "<font color=\"#060\">Login succeeded, welcome {$user[1]}</font>";
				session_start ();
				$id = session_id ();
				mysql_query ( "UPDATE jshout_user SET auth='{$id}' WHERE id='{$udata[0]}'" );
				setcookie ( "jShout_auth", session_id () );
				setcookie ( "jShout", $user [1] );
				sleep ( 1 );
				$uname = $user [1];
			} else {
				$clean = $this->err ( "Login failed, Invalid Credentials" );
			}
		
		} else {
			$clean = $this->err ( "Login failed, Missing Params" );
		}
		$this->sys_msg ( $clean );
		return null;
	}
	
	function tcate($str, $chars, $to_space, $replacement = "...") {
		if ($chars > strlen ( $str )) return $str;
		
		$str = substr ( $str, 0, $chars );
		
		$space_pos = strrpos ( $str, " " );
		if ($to_space && $space_pos >= 0) {
			$str = substr ( $str, 0, strrpos ( $str, " " ) );
		}
		
		return ($str . $replacement);
	}
	
	function link($clean) {
		$url = strip_tags ( $clean );
		$file = @file ( $url );
		$file = @implode ( "", $file );
		$title = preg_match ( "/<title>(.+)<\/title>/i", $file, $r );
		$r [1] = mysql_real_escape_string ( $r [1] );
		$r [1] = $this->tcate ( $r [1], 70, true, "..." );
		$clean = mysql_real_escape_string ( "<a href='{$url}' title='{$url}' target='_new'>{$r[1]}</a>" );
		if ($r [1] == '') {
			$clean = mysql_real_escape_string ( "<a href='{$url}' title='{$url}' target='_new'>{$url}</a>" );
		}
		$this->sys_msg ( $clean );
		return null;
	}
	
	function reg_msg($msg) {
		$color = cfg::reg_color;
		$shout = "<font color=\"{$color}\">" . $msg . "</font>";
		return $shout;
	}
	function guest_msg($msg) {
		$color = cfg::guest_color;
		$shout = "<font color=\"{$color}\">" . $msg . "</font>";
		return $shout;
	}
	
	function get_auth($user) {
		if (cfg::must_auth) {
			if ($_COOKIE ['jShout_auth'] == '') {
				setcookie ( "jShout", '' );
			} else {
				$sql = "SELECT auth FROM jshout_user WHERE user='{$user}')";
				$res = mysql_query ( $sql );
				$a = @mysql_fetch_row ( $res );
				if ($a [0] != session_id ()) {
					return false;
				} else {
					return true;
				}
			}
		} else {
			return true;
		}
	
	}
	function add_shout($clean, $uname = 'Guest') {
		if ($this->cookie == '') {
			$uname = "Guest";
			$shout = $this->guest_msg ( $clean );
		} else {
			$uname = $this->cookie;
			$shout = $this->reg_msg ( $clean );
		}
		if ($this->flood > $_COOKIE ['jShout_last_shout'] + cfg::flood_timeout) {
			if ($this->get_auth ( $uname )) {
				$pf = time ();
				setcookie ( "jShout_last_shout", $pf );
				$sql = "INSERT INTO jshout VALUES (NULL, '{$shout}', CURRENT_TIMESTAMP, '{$uname}', '')";
				mysql_query ( $sql ) or die ( mysql_error () );
				if (mysql_affected_rows () > 0) {
					print ("<B>{$uname}</b>: &nbsp;&nbsp;" . $shout) ;
				} else {
					print ('failed to add shout') ;
					die ();
				}
			} else {
				$this->sys_msg ( "Please [/login] or [/register]." );
			}
		} else {
			$this->sys_msg ( $this->err ( "Flood Protectionaaaaaaa!" ) );
		}
	
	}
	
	function clear($clean) {
		if (cfg::allow_clear) {
			if ($this->cookie == '') {
				$clean = "You are not logged in";
			} else {
				mysql_query ( "TRUNCATE jshout" );
				$clean = ("Chat cleared on " . date ( 'l jS \of F Y h:i:s A' ));
			}
			$this->add_shout ( $clean );
			return null;
		} else {
			$this->sys_msg ( $this->err ( "Clearing is disabled by admin" ) );
		}
	}
	
	function __destruct() {
		$this->_stop_time = microtime ( true );
		$diff = round ( ($this->_stop_time - $this->_start_time) * 1000, 3 );
		$this->exTime = $diff;
	}
}

?>