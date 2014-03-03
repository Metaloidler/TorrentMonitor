<?php
class Sys
{
	//проверяем есть ли интернет
	public static function checkInternet()
	{
		$page = file_get_contents('http://ya.ru');
		if (preg_match('/<title>Яндекс<\/title>/', $page))
			return TRUE;
		else
			return FALSE;
	}
	
	//проверяем есть ли конфигурационный файл
	public static function checkConfigExist()
	{
		$dir = dirname(__FILE__);
		$dir = str_replace('class', '', $dir);
		if (file_exists($dir.'/config.php'))
			return TRUE;
		else
			return FALSE;
	}

	//проверяем правильно ли заполнен конфигурационный файл
	public static function checkConfig()
	{
		$dir = dirname(__FILE__).'/../';
		include_once $dir.'config.php';
		
		$confArray = Config::$confArray;
		foreach ($confArray as $key => $val)
		{
			if (empty($val))
				return FALSE;
		}
		return TRUE;
	}

	//проверяем установлено ли расширение CURL
	public static function checkCurl()
	{
		if (in_array('curl', get_loaded_extensions()))
			return TRUE;
		else
			return FALSE;
	}

	//проверяем есть ли на конце пути / или \
	public static function checkPath($path)
	{
		$path=trim($path);
		$dirseppath=substr($path, -1);
		
		if ($dirseppath == '/' or $dirseppath == '\\' ){
			return $path;
		}else{
			return $path.DIRECTORY_SEPARATOR;
		}
	}
	
	//проверка на возхможность записи в директорию
	public static function checkWriteToPath($path)
	{
		return is_writable($path);
	}

	//версия системы
	public static function version()
	{
		return '0.9.4';
	}

	//проверка обновлений системы
	public static function checkUpdate()
	{
	    $opts = stream_context_create(array(
    		'http' => array(
    			'timeout' => 1
    			)
    		));

        $xmlstr = @file_get_contents('http://korphome.ru/torrent_monitor/version.xml', false, $opts);
        $xml = @simplexml_load_string($xmlstr);
	    
		if (false !== $xml)
		{
			if (Sys::version() < $xml->current_version)
				return TRUE;
			else
				return FALSE;
		}
	}
	
	//обёртка для CURL, для более удобного использования
	public static function getUrlContent($param = null)
    {
    	if (is_array($param))
    	{
    		$ch = curl_init();
    		if ($param['type'] == 'POST')
    			curl_setopt($ch, CURLOPT_POST, 1);

    		if ($param['type'] == 'GET')
    			curl_setopt($ch, CURLOPT_HTTPGET, 1);

    		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:16.0) Gecko/20100101 Firefox/16.0');

    		if (isset($param['header']))
    			curl_setopt($ch, CURLOPT_HEADER, 1);

   			curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    		if (isset($param['returntransfer']))
    			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    		curl_setopt($ch, CURLOPT_URL, $param['url']);

    		if (isset($param['postfields']))
    			curl_setopt($ch, CURLOPT_POSTFIELDS, $param['postfields']);

    		if (isset($param['cookie']))
    			curl_setopt($ch, CURLOPT_COOKIE, $param['cookie']);

    		if (isset($param['sendHeader']))
    		{
    			foreach ($param['sendHeader'] as $k => $v)
    			{
    				$header[] = $k.': '.$v."\r\n";
    			}
    			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    		}		

    		if (isset($param['referer']))
    			curl_setopt($ch, CURLOPT_REFERER, $param['referer']);
    			
            $settingProxy = Database::getSetting('proxy');
            if ($settingProxy)
            {
                $settingProxyAddress = Database::getSetting('proxyAddress');
                curl_setopt($ch, CURLOPT_PROXY, $settingProxyAddress); 
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5); 
            }

    		$result = curl_exec($ch);
    		curl_close($ch);

    		if (isset($param['convert']))
    			$result = iconv($param['convert'][0], $param['convert'][1], $result);

    		return $result;
    	}
    }
    
    //Проверяем доступность трекера
    public static function checkavAilability($tracker)
    {
		$page = Sys::getUrlContent(
            array(
                'type'           => 'GET',
                'header'         => 1,
                'returntransfer' => 1,
                'url'            => $tracker,
            )
		);
		
		if (preg_match('/HTTP\/1\.1 200 OK/', $page))
			return true;
		else
			return false;
    }
	
	//Получаем заголовок страницы
	public static function getHeader($url)
	{
		$Purl = parse_url($url);
		$tracker = $Purl['host'];
		$tracker = preg_replace('/www\./', '', $tracker);
	
		$forumPage = Sys::getUrlContent(
            array(
                'type'           => 'GET',
                'returntransfer' => 1,
                'url'            => $url,
            )
		);

		if ($tracker != 'rutor.org' && $tracker != 'casstudio.tv')
			$forumPage = iconv('windows-1251', 'utf-8//IGNORE', $forumPage);

		if ($tracker == 'tr.anidub.com')
			$tracker = 'anidub.com';

		preg_match('/<title>(.*)<\/title>/is', $forumPage, $array);
		if ( ! empty($array[1]))
		{
			$name = $array[1];
			if ($tracker == 'anidub.com')
				$name = substr($name, 15, -50);
			if ($tracker == 'casstudio.tv')
				$name = substr($name, 48);
			if ($tracker == 'kinozal.tv')
				$name = substr($name, 0, -22);
			if ($tracker == 'nnm-club.me')
				$name = substr($name, 0, -20);
			if ($tracker == 'rutracker.org')
				$name = substr($name, 0, -34);
			if ($tracker == 'rutor.org')
				$name = substr($name, 13);
		}
		else
			$name = 'Неизвестный';
		return $name;
	}
	
	//сохраняем torrent файл
	public static function saveTorrent($tracker, $name, $torrent, $id, $hash)
	{
    	$file = '['.$tracker.']_'.$name.'.torrent';
        $path = Database::getSetting('path').$file;
        file_put_contents($path, $torrent);

        $useTorrent = Database::getSetting('useTorrent');
        if ($useTorrent)
        {
            $torrentClient = Database::getSetting('
