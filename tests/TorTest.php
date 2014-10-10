<?php
require_once(dirname(__FILE__).'/../TorProxy.php');

class TorTest extends PHPUnit_Framework_TestCase
{
    public function testGetPage()
    {
        try{
			$tor = new TorProxy();
			$html = $tor->getPage('http://ya.ru');
			$this->assertNotEmpty($html);
		}catch(TorProxyException $e)
		{
			$this->fail($e->getMessage());
		}
		$tor->destroy();
    }

    public function testGetBusyPorts()
    {
        $tor = new TorProxy();
        $this->assertNotEmpty($tor->getBusyPorts());
        $tor->destroy();
    }
    
    public function testReload()
    {
		try{
			$tor = new TorProxy();
			$html = $tor->getPage('http://ya.ru');
			$this->assertNotEmpty($html);
			$tor->reload();
			try{
				$html = $tor->getPage('http://ya.ru');
				$this->assertNotEmpty($html);
			}catch(TorProxyException $e)
			{
				$this->fail($e->getMessage());
			}
		}catch(TorProxyException $e)
		{
			$this->fail($e->getMessage());
		}
		$tor->destroy();
	}
}
