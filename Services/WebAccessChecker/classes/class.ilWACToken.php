<?php
require_once('class.ilWACSignedPath.php');

/**
 * Class ilWACToken
 *
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class ilWACToken {

	const TYPE_FILE = ilWACSignedPath::TYPE_FILE;
	const TYPE_FOLDER = ilWACSignedPath::TYPE_FOLDER;
	/**
	 * @var string
	 */
	protected static $SALT = '';
	/**
	 * @var string
	 */
	protected $session_id = '';
	/**
	 * @var int
	 */
	protected $timestamp = 0;
	/**
	 * @var int
	 */
	protected $type = self::TYPE_FILE;
	/**
	 * @var string
	 */
	protected $ip = '';
	/**
	 * @var string
	 */
	protected $token = '';
	/**
	 * @var string
	 */
	protected $path = '';
	/**
	 * @var string
	 */
	protected $id = '';


	/**
	 * @param $path
	 */
	protected function __construct($path) {
		$this->initSalt();
		$parts = parse_url($path);
		$this->setPath($parts['path']);
		$this->setSessionId(session_id() ? session_id() : '-');
		$this->setIp($_SERVER['REMOTE_ADDR']);
		$this->setTimestamp(time());
		$this->generateToken();
		$this->setId(md5($this->getPath()));
	}


	protected function generateToken() {
		if (! self::getSALT()) {
			$this->initSalt();
		}
		$token = implode('-', array( $this->getSessionId(), $this->getIp() ));
		$token = $token * self::getSALT();
		$token = sha1($token);
		$this->setToken($token);
	}


	protected function initSalt() {
		$salt = NULL;
		@include_once('./data/wacsalt.php');
		self::setSALT($salt ? $salt : '-');
		if (self::getSALT()) {
			return true;
		}
		$this->generateSaltFile();
	}


	public function generateSaltFile() {
		if (is_file('./data/wacsalt.php')) {
			unlink('./data/wacsalt.php');
		}
		$template = file_get_contents('./Services/WebAccessChecker/wacsalt.php.template');
		$template = str_replace('INSERT_SALT', md5(time() * rand(1000, 9999)), $template);
		if (is_writable('./data/')) {
			file_put_contents('./data/wacsalt.php', $template);
		} else {
			throw new ilWACException(ilWACException::DATA_DIR_NON_WRITEABLE);
		}
	}


	/**
	 * @param $path
	 *
	 * @return ilWACToken
	 */
	public static function getInstance($path) {
		return new self($path);
	}


	/**
	 * @return string
	 */
	public function getSessionId() {
		return $this->session_id;
	}


	/**
	 * @param string $session_id
	 */
	public function setSessionId($session_id) {
		$this->session_id = $session_id;
	}


	/**
	 * @return int
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}


	/**
	 * @param int $timestamp
	 */
	public function setTimestamp($timestamp) {
		$this->timestamp = $timestamp;
	}


	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}


	/**
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}


	/**
	 * @return string
	 */
	public function getIp() {
		return $this->ip;
	}


	/**
	 * @param string $ip
	 */
	public function setIp($ip) {
		$this->ip = $ip;
	}


	/**
	 * @return string
	 */
	public function getToken() {
		return $this->token;
	}


	/**
	 * @param string $token
	 */
	public function setToken($token) {
		$this->token = $token;
	}


	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}


	/**
	 * @param string $path
	 */
	public function setPath($path) {
		$this->path = $path;
	}


	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}


	/**
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}


	/**
	 * @return string
	 */
	public static function getSALT() {
		return self::$SALT;
	}


	/**
	 * @param string $SALT
	 */
	public static function setSALT($SALT) {
		self::$SALT = $SALT;
	}
}

?>
