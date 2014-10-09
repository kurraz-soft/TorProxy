<?php
require_once(dirname(__FILE__).'/../TorProxy.php');

class TorTest extends PHPUnit_Framework_TestCase
{
    public function testGetPage()
    {
        $tor = new TorProxy();
        try{
			$html = $tor->getPage('http://ya.ru');
			$this->assertNotEmpty($html);
		}catch(TorProxyException $e)
		{
			$this->fail($e->getMessage());
		}
		$tor->destroy();
    }
}
