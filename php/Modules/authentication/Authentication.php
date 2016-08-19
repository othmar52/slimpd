<?php
namespace Slimpd\Modules\authentication;

class Authentication
{
	protected $app;
	public function __construct() {
		$this->$app = \Slim\Slim::getInstance();
	}
	
	public function check($access, $cache = false, $validate_sign = false, $disable_counter = false) {
		global $cfg;
		
		 
		
		if ($cache == false && headers_sent() == false)	{
			header('Expires: Mon, 9 Oct 2000 18:00:00 GMT');
			header('Cache-Control: no-store, no-cache, must-revalidate');
		}
		
		$sid = $this->app->getCookie('netjukebox_sid');
		$authenticate = $this->app->request->params('authenticate');
		
		$result	= $this->app->db->query('
			SELECT logged_in, user_id, idle_time,
			    ip, user_agent, sign, seed, skin,
				random_blacklist, thumbnail, thumbnail_size,
				stream_id, download_id, player_id
			FROM session
			WHERE sid = BINARY "' . $this->app->db->real_escape_string($sid) . '"');
		$session		= $result->fetch_assoc();
		
		//setSkin($session['skin']);
		
		// Validate login
		if ($authenticate == 'validate') {
			$username	= $this->app->request->post('username');
			$hash1		= $this->app->request->post('hash1');
			$hash2		= $this->app->request->post('hash2');
			$sign		= $this->app->request->post('sign');
			
			if ($session['ip'] == '')
				message(__FILE__, __LINE__, 'error', '[b]Login failed[/b][br]netjukebox requires cookies to login.[br]Enable cookies in your browser and try again.[br][url=index.php][img]small_login.png[/img]login[/url]');
				
			if ($session['ip'] != $_SERVER['REMOTE_ADDR'])
				message(__FILE__, __LINE__, 'error', '[b]Login failed[/b][br]Unexpected IP address[br][url=index.php][img]small_login.png[/img]login[/url]');
					
			$query		= mysql_query('SELECT ' . (string) round(microtime(true) * 1000) . ' - pre_login_time AS login_delay FROM session WHERE ip = "' . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '" ORDER BY pre_login_time DESC LIMIT 1');
			$ip			= mysql_fetch_assoc($query);
			
			$query		= mysql_query('SELECT password, seed, version, user_id FROM user WHERE username = "' . mysql_real_escape_string($username) . '"');
			$user		= mysql_fetch_assoc($query);
			$user_id	= $user['user_id'];
				
			if (// validate password
				($user['version'] == 0 && $user['password'] == sha1($hash1) ||
				$user['version'] == 1 && $user['password'] == hmacsha1($hash1, $user['seed'])) &&
				// sha1 collision protection
				preg_match('#^[0-9a-f]{40}$#', $hash1) &&
				// new password validation as far as possible
				preg_match('#^[0-9a-f]{40}$#', $hash2) &&
				(($username == $cfg['anonymous_user'] && $hash2 == hmacsha1(hmacsha1($cfg['anonymous_user'], $session['seed']), $session['seed'])) ||
				($username != $cfg['anonymous_user'] && $hash2 != hmacsha1(hmacsha1('', $session['seed']), $session['seed']))) &&
				// brute force & hack attack protection
				$ip['login_delay'] > $cfg['login_delay'] &&
				$session['user_agent'] == substr($_SERVER['HTTP_USER_AGENT'], 0, 255) &&
				$session['sign'] == $sign) {
				
				mysql_query('UPDATE user SET
					password		= "' . mysql_real_escape_string($hash2) . '",
					seed			= "' . mysql_real_escape_string($session['seed']) . '",
					version			= 1
					WHERE username	= "' . mysql_real_escape_string($username) . '"');
				
				$sign = randomKey();
				$sid = randomKey();
				
				mysql_query('UPDATE session SET
					logged_in		= 1,
					user_id			= ' . (int) $user_id . ',
					login_time		= ' . (int) time() . ',
					idle_time		= ' . (int) time() . ',
					sid				= "' . mysql_real_escape_string($sid) . '",
					sign			= "' . mysql_real_escape_string($sign) . '",
					hit_counter		= hit_counter + ' . ($disable_counter ? 0 : 1) . ',
					visit_counter	= visit_counter + ' . (time() > $session['idle_time'] + 3600 ? 1 : 0) . '
					WHERE sid		= BINARY "' . mysql_real_escape_string(cookie('netjukebox_sid')) . '"');
				
				setcookie('netjukebox_sid', $sid, time() + 31536000, null, null, NJB_HTTPS, true);
				@ob_flush();
				flush();
			}
			else
				logoutSession();
		}
		else {
			// Validate current session
			$user_id = $session['user_id'];
			
			if ($session['logged_in'] &&
				$session['ip']			== $_SERVER['REMOTE_ADDR'] &&
				$session['user_agent']	== substr($_SERVER['HTTP_USER_AGENT'], 0, 255) &&
				$session['idle_time'] + $cfg['session_lifetime'] > time()) {
				
				mysql_query('UPDATE session SET
					idle_time		= ' . (int) time() . ',
					hit_counter		= hit_counter + ' . ($disable_counter ? 0 : 1) . ',
					visit_counter	= visit_counter + ' . (time() > $session['idle_time'] + 3600 ? 1 : 0) . '
					WHERE sid		= BINARY "' . mysql_real_escape_string($sid) . '"');
			}
			elseif ($access == 'access_always')	{
				$cfg['access_media']		= false;
				$cfg['access_popular']		= false;
				$cfg['access_favorite']		= false;
				$cfg['access_cover']		= false;
				$cfg['access_stream']		= false;
				$cfg['access_download']		= false;
				$cfg['access_playlist']		= false;
				$cfg['access_play']			= false;
				$cfg['access_add']			= false;
				$cfg['access_record']		= false;
				$cfg['access_statistics']	= false;
				$cfg['access_admin']		= false;
				return true;
			}
			else {
				$app->ll->str('böla');
				logoutSession();
			}
		}
		
		// Username & user privalages
		unset($cfg['username']);
		$query = mysql_query('SELECT
			username,
			access_media,
			access_popular,
			access_favorite,
			access_cover,
			access_stream,
			access_download,
			access_playlist,
			access_play,
			access_add,
			access_record,
			access_statistics,
			access_admin
			FROM user
			WHERE user_id = ' . (int) $user_id);
		$cfg += mysql_fetch_assoc($query);
		
		// Validate privilege
		$access_validated = false;
		if (is_array($access)) {
			foreach ($access as $value)
				if (isset($cfg[$value]) && $cfg[$value])	$access_validated = true;
		}
		elseif (isset($cfg[$access]) && $cfg[$access])		$access_validated = true;
		elseif ($access == 'access_logged_in')				$access_validated = true;
		elseif ($access == 'access_always')					$access_validated = true;
		if ($access_validated == false)
			message(__FILE__, __LINE__, 'warning', '[b]You have no privilege to access this page[/b][br][url=index.php?authenticate=logout][img]small_login.png[/img]Login as another user[/url]');
		
		// Validate signature
		if	($cfg['sign_validated'] == false &&
			($validate_sign ||
			$authenticate == 'logoutAllSessions' ||
			$authenticate == 'logoutSession')) {
			
			$cfg['sign'] = randomKey();
			mysql_query('UPDATE session
				SET	sign		= "' . mysql_real_escape_string($cfg['sign']) . '"
				WHERE sid		= BINARY "' . mysql_real_escape_string($sid) . '"');
			if ($session['sign'] == getpost('sign'))
				$cfg['sign_validated'] = true;
			else
				message(__FILE__, __LINE__, 'error', '[b]Signature expired[/b]');
		}
		else
			$cfg['sign'] = $session['sign'];
		
		// Logout
		if ($authenticate == 'logout' && $cfg['username'] != $cfg['anonymous_user']) {
			$query = mysql_query('SELECT user_id FROM session
				WHERE logged_in
				AND user_id		= ' . (int) $user_id . '
				AND idle_time	> ' . (int) (time() - $cfg['session_lifetime']) );
			
			if (mysql_affected_rows($db) > 1)	logoutMenu();
			else								logoutSession();	
		}
		elseif ($authenticate == 'logoutAllSessions' && $cfg['username'] != $cfg['anonymous_user']) {
			mysql_query('UPDATE session
				SET logged_in	= 0
				WHERE user_id	= ' . (int) $user_id);
			logoutSession();
		}
		elseif ($authenticate == 'logoutSession' || $authenticate == 'logout')
			logoutSession();
		
		$cfg['user_id']				= $user_id;
		$cfg['sid']					= $sid;
		$cfg['session_seed']		= $session['seed'];
		$cfg['random_blacklist']	= $session['random_blacklist'];
		//$cfg['thumbnail']			= $session['thumbnail'];
		$cfg['thumbnail']			= 1;
		//$cfg['thumbnail_size']		= $session['thumbnail_size'];
		$cfg['thumbnail_size']		= 100;
		$cfg['stream_id']			= (isset($cfg['encode_extension'][$session['stream_id']])) ? $session['stream_id'] : -1;
		$cfg['download_id']			= (isset($cfg['encode_extension'][$session['download_id']])) ? $session['download_id'] : -1;
		$cfg['player_id']			= $session['player_id'];
	}




}
