<?php
/*
 *  IpInfoDbComponent
 *  
 *  A CakePHP component for doing IP to Location lookups using the free
 *  IP Info DB service (http://ipinfodb.com). Use this code freely, but
 *  please let me know if you find any bugs or can offer any improvements.
 *  I haven't done much testing and quickly wrote this up so use at your
 *  own risk! 
 * 
 */

class IpInfoDbComponent extends Object
{	
	public $components = array("Cookie");
	private $service = 'http://api.ipinfodb.com/v3/%s/?key=%s&ip=%s&format=json';
	private $localCache = array();
	private $controller;
	private $settings = array(
		"apiKey" => false,				// get your apiKey here http://www.ipinfodb.com/register.php
		"cache" => true,				// true if result should be stored in a cookie
		"cacheKey" => "IpLookup",		// key for Cookie 
		"defaultIP" => false			// Defaults to REMOTE_ADDR, otherwise provide a default
	);
	
	public function initialize(&$controller, $settings=array())
	{
		$this->controller = $controller;
		$this->settings = array_merge($this->settings, $settings);
		if (empty($this->settings["defaultIP"]))
			$this->settings["defaultIP"] = $_SERVER['REMOTE_ADDR'];
	}
	
	// API to data provided by IPInfoDB
	public function ip() 				{return $this->get("ipAddress");}
	public function city($ip=false) 	{return $this->get("cityName", $ip);}
	public function region($ip=false) 	{return $this->get("regionName", $ip);}
	public function country($ip=false)	{return $this->get("countryName", $ip);}
	public function zip($ip=false) 		{return strtoupper($this->get("zipCode", $ip));}	// Canadian postals use uppercase letters
	public function lat($ip=false) 		{return $this->get("latitude", $ip);}
	public function lon($ip=false)		{return $this->get("longitude", $ip);}
	public function timezone($ip=false)	{return $this->get("timeZone", $ip);}

	protected function get($key, $ip=false)
	{
		$result = $this->fetchResult($ip);
		return $result[$key] == "-" ? "" : ucwords(strtolower($result[$key]));
	}

	protected function fetchResult($ip=false)
	{
		if ($ip === false)
			$ip = $this->settings["defaultIP"];
		if (!array_key_exists($ip, $this->localCache))
			$this->localCache[$ip] = $this->settings["cache"] ? $this->fetchFromCache($ip) : $this->fetchFromSource($ip);
		return $this->localCache[$ip];
	}
	
	protected function fetchFromCache($ip)
	{
		$cacheKey = $this->settings["cacheKey"];
		$remoteCache = $this->Cookie->read($cacheKey);
		
		if (!$remoteCache)
			$remoteCache = array();
		else
			$remoteCache = unserialize(base64_decode($remoteCache));
			
		if (!is_array($remoteCache))
			$remoteCache = array();
			
		if (!array_key_exists($ip, $remoteCache))
		{		
			$remoteCache[$ip] = $this->fetchFromSource($ip);
			
			// Don't rewrite Cookie every time - let it expire to revalidate the lookup
			$this->Cookie->write($cacheKey, base64_encode(serialize($remoteCache)), false, "+1 week");
		}
		return $remoteCache[$ip];
	}
	
	protected function fetchFromSource($ip)
	{
		return json_decode(file_get_contents(sprintf($this->service, "ip-city", $this->settings["apiKey"], $ip)), true);
	}
}

?>