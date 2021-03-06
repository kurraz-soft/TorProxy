<?php

class TorProxy
{
    const START_PORT = 9050;
	const DELAY_START = 5;

    public $pid;
	public $port;
	
	private $cookie_file = false;
	private $cookie_jar = false;
	private $cookie_vars = false;
    private $auto_reload = false;
    private $connections_count = 0;
    private $delay_get_page = 0;
	
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
        return $this;
	}

    /**
     * @param int $seconds
     * @return $this
     */
    public function setDelayGetPage($seconds)
    {
        $this->delay_get_page = $seconds;
        return $this;
    }

    /**
     * Reloads tor after n connections
     * @param int $after_n_connections
     * @return TorProxy
     */
    public function setAutoReload($after_n_connections)
    {
        $this->auto_reload = $after_n_connections;
        return $this;
    }
    
    /**
     * @param string $url
     * @param array|null $post
     * @throws TorProxyException
     * @return string
     */
    public function getPage($url, $post = null)
    {
        if($this->delay_get_page)
        {
            sleep($this->delay_get_page);
        }
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

    /**
     * @param $url
     * @param array|null $post
     * @return string
     */
    public function getPageSafe($url,$post = null)
    {
        try{
            return $this->getPage($url,$post);
        }catch (TorProxyException $e)
        {
            $this->reload();
            return $this->getPageSafe($url,$post);
        }
    }
	
	public function destroy()
	{
		unlink($this->getPortsDir().'/'.$this->port);
		$dir = $this->getPortsDir().'/data'.$this->port;
		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it,
					 RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				@unlink($file->getRealPath());
			}
		}
		rmdir($dir);
		
		exec('kill '.$this->pid);
	}

    public function init($port = false)
    {
        $ports_dir = $this->getPortsDir();

        if(!is_dir($ports_dir)) mkdir($ports_dir);

        if(!$port)
        {
            $busyPorts = $this->getBusyPorts();
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
                if(!$port) $port = $startPort;
            }
        }
        $f = fopen($ports_dir.'/'.$port,'w');
        fprintf($f,"SocksPort %d\nSocksListenAddress 127.0.0.1\nDataDirectory ".$ports_dir."/data%d\n",$port,$port);
        fclose($f);
        if(!file_exists($ports_dir.'/data'.$port))
        {
			mkdir($ports_dir.'/data'.$port);
		}

        $this->pid = $this->_start_process('tor -f '.$ports_dir.'/'.$port);
        if(!$this->pid) throw new TorProxyException("can't create process");
        $this->port = $port;
        sleep(self::DELAY_START);
    }

    public function getPortsDir()
    {
        return dirname(__FILE__).'/ports';
    }

    public function getBusyPorts()
    {
        $dir = opendir($this->getPortsDir());
        $busyPorts = array();
        while(($file = readdir($dir)) !== false)
        {
            if(is_file($this->getPortsDir().'/'.$file))
            {
                $busyPorts[] = (int)$file;
            }
        }
        closedir($dir);
        return $busyPorts;
    }

    public function reload()
    {
        $this->destroy();
        $this->init($this->port);
        return $this;
    }

    private function _start_process($command)
    {
        $command = $command.' > /dev/null 2>&1 & echo $!'; 
        exec($command, $op); 
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
