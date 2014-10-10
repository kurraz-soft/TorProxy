<?php

class TorProxy
{
    const START_PORT = 9050;
	const DELAY_START = 5;

    private $pid;
	private $port;
	
	private $cookie_file = false;
	private $cookie_jar = false;
	private $cookie_vars = false;
    private $auto_reload = false;
    private $connections_count = 0;
	
    function __construct($port = false)
    {
        $this->init($port);
    }
    
    public function setCookieFile($filepath)
    {
		$this->cookie_file = $filepath;
		return $this;
	}
	
	public function setCookieJar($filepath)
    {
		$this->cookie_jar = $filepath;
		return $this;
	}
	
	public function setCookieVar($vars)
	{
		$this->cookie_vars = implode('; ',$vars);
	}

    /**
     * Reloads tor after n connections
     * @param int $after_n_connections
     */
    public function setAutoReload($after_n_connections)
    {
        $this->auto_reload = $after_n_connections;
    }
    
    /**
     * @param string $url
     * @param array|false $post
     * @throws TorProxyException
     * @return string
     */
    public function getPage($url, $post = false)
    {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.97 Safari/537.11');
		curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:".$this->port);
		curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if($this->cookie_file)
		{
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
		}
		if($this->cookie_jar)
		{
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_jar);
		}
		if($this->cookie_vars)
		{
			curl_setopt($ch, CURLOPT_COOKIE, $this->cookie_vars);
		}
		if($post)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		$out=curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		
		if($err) throw new TorProxyException($err);

        $this->connections_count++;
        if($this->auto_reload)
        {
            if($this->connections_count % $this->auto_reload === 0)
            {
                $this->reload();
            }
        }
		
		return $out;
	}
	
	public function destroy()
	{
		exec('kill '.$this->pid);
		unlink(dirname(__FILE__).'/ports/'.$this->port);
	}

    public function init($port = false)
    {
        $ports_dir = dirname(__FILE__).'/ports';

        if(!is_dir($ports_dir)) mkdir($ports_dir);

        if(!$port)
        {
            $dir = opendir($ports_dir);
            $busyPorts = array();
            while($file = readdir($dir) !== false)
            {
                if(is_file($file))
                {
                    $busyPorts[] = (int)$file;
                }
            }
            if(!$busyPorts)
            {
                $port = self::START_PORT + 1;
            }else
            {
                sort($busyPorts);
                $startPort = self::START_PORT + 1;
                foreach($busyPorts as $p)
                {
                    if($p > $startPort)
                    {
                        $port = $startPort;
                        break;
                    }else
                    {
                        $startPort++;
                    }
                }
            }
        }
        $f = fopen($ports_dir.'/'.$port,'w');
        fprintf($f,"SocksPort %d\nSocksListenAddress 127.0.0.1\n",$port);
        fclose($f);

        $this->pid = $this->_start_process('tor -f '.$ports_dir.'/'.$port);
        $this->port = $port;
        sleep(self::DELAY_START);
    }

    public function reload()
    {
        $this->destroy();
        $this->init($this->port);
    }

    private function _start_process($command)
    {
        $command = $command.' > /dev/null 2>&1 & echo $!'; 
        exec($command ,$op); 
        $pid = (int)$op[0]; 

        if($pid!="") return $pid; 

        return false;
    }
    
    static public function getStandardCookieFilepath()
    {
		return dirname(__FILE__).'/cookie.txt';
	}
}

class TorProxyException extends Exception
{
}
