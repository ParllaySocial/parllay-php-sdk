<?php
if(!function_exists('curl_init')) {
	throw new Exception('Parllay needs the CURL PHP extension.');
}

if(!function_exists('json_decode')) {
	throw new Exception('Parllay needs the JSON PHP extension.');
}

/**
 * Thrown when an API call returns an exception.
 * 
 * @author Steve Gao <gaofu@parllay.com>
 *
 */
class ParllayApiException extends Exception
{
	protected $result;
	
	public function __construct($result) {
		$this->result = $result;
		
		$code = isset($result['error']['code']) ? $result['error']['code'] : 0;
		
		if(isset($result['error']) && is_array($result['error'])) {
			$msg = $result['error']['message'];
		} else {
			$msg = 'Unknown Error. Check getResult()';
		}
		
		parent::__construct($msg, $code);
	}
	
	public function getResult() {
		return $this->result;
	}
	
	public function getType() {
		if(is_array($this->result['error'])) {
			if(isset($this->result['error']['type'])) {
				return $this->result['error']['type'];
			}
		}
		
		return 'Exception';
	}
	
	public function __toString() {
		$str = $this->getType() . ': ';
		if($this->code != 0) {
			$str .= $this->code . ': ';
		}
		
		return $str . $this->message;
	}
}

class Parllay {
	/**
	 * Version
	 */
	const VERSION = '1.0';
	
	/**
	 * Default options for curl
	 */
	public static $CURL_OPTS = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_USERAGENT      => 'parllay-php-sdk-1.0',
		CURLOPT_HTTPHEADER     => array()
	);
	
	/**
	 * Maps aliases to Parllay domains
	 */
	public static $API_DOMAIN_MAP = array(
		'api' => 'https://api.parllay.com/1.0/',
		'api_ppe' => 'https://api-ppe.parllay.com/1.0/'
	);
	
	public static $HTTP_METHODS = array(
		'GET',
		'POST',
		'HEAD'
	);
	
	/**
	 * The Parllay Application ID
	 * @var string
	 */
	protected $appId;
	
	/**
	 * The Application API Secret
	 * @var string
	 */
	protected $appSecret;
	
	protected $inSandBox = false;
	
	public function __construct($config) {
		
		if(!session_id()) {
			session_start();
		}
		
		$this->setAppId($config["appId"]);
		$this->setAppSecret($config["secret"]);
	}
	
	public function setAppId($appId) {
		$this->appId = $appId;
	}
	
	public function getAppId() {
		return $this->appId;
	}
	
	public function setAppSecret($secret) {
		$this->appSecret = $secret;
	}
	
	public function getAppSecret() {
		return $this->appSecret;
	}
	
	public function setSandbox($isSandBox) {
		$this->inSandBox = $isSandBox;
	}
	
	public function useSandbox() {
		return $this->inSandBox;
	}
	
	public function api(/* polymorphic */) {
		$args = func_get_args();
		if(gettype($args[0]) !== 'string') {
			throw new ParllayApiException(array(
				"error" => array(
					"message" => "Unavailable call for Parllay PHP SDK, Use Path for the first parameter",
					"type" => "ParllayApiException",
					"code" => 101
				)
			));
		} else {
			return call_user_func_array(array($this, '_rest'), $args);
		}
	}
	
	protected function throwAPIException($result) {
		$e = new ParllayApiException($result);
		throw $e;
	}
	
	protected function _rest($path, $method = 'GET', $params = array()) {
		if(is_array($method) && empty($params)) {
			$params = $method;
			$method = 'GET';
		}
		
		if(is_string($method)) {
			$method = strtoupper($method);
			if(!in_array($method, self::$HTTP_METHODS)) {
				$method = 'GET';
			}
			
			if(!is_array($params)) {
				throw new ParllayApiException(array(
					"error" => array(
						"type" => "ParllayApiException",
						"code" => 102,
						"message" => "Unavailable parameters"
					)
				));
			}
		}
		
		foreach($params as $key=>$value) {
			if(!is_string($value)) {
				$params[$key] = json_encode($value);
			}
		}
		$url = $this->getUrl($path, $method, $params);
		$result = json_decode($this->makeRequest(
				$url, 
				$method, 
				$params
		), true);

		if(is_array($result) && isset($result["error"])) {
			$this->throwAPIException($result);
		}
		
		return $result;
	}
	
	protected function getUrl($path, $method, $params = array()) {
		$url = null;
		if($this->useSandbox()) {
			$url = self::$API_DOMAIN_MAP['api_ppe'];
		} else {
			$url = self::$API_DOMAIN_MAP['api'];
		}
		
		if($path) {
			if($path[0] === '/') {
				$path = substr($path, 1);
			}
			$url .= $path;
		}
		
		if($method === 'GET' && $params) {
			$url .= '?' . http_build_query($params, null, '&');
		}
		
		return $url;
	}
	
	protected function makeRequest($url, $method, $params) {
		$ch = curl_init();
		
		$opts = self::$CURL_OPTS;
		array_push($opts[CURLOPT_HTTPHEADER], 'X-Parllay-App-Id: ' . $this->getAppId());
		array_push($opts[CURLOPT_HTTPHEADER], 'X-Parllay-App-Secret: ' . $this->getAppSecret());

		if($method === 'POST') {
			$opts[CURLOPT_POST] = true;
			$opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
		}
		$opts[CURLOPT_URL] = $url;
		
		curl_setopt_array($ch, $opts);
		
		$result = curl_exec($ch);
		
		if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
	     
	      curl_setopt($ch, CURLOPT_CAINFO,
	                  dirname(__FILE__) . '/parllay_ca_chain.crt');
	      $result = curl_exec($ch);
	    }
		
		if($result === false) {
			$e = new ParllayApiException(array(
				"error" => array(
					"message" => curl_error($ch),
					"type" => 'CurlException',
					'code' => curl_errno($ch)
				)
			));
			curl_close($ch);
			throw $e;
		}
		curl_close($ch);
		return $result;
	}
}