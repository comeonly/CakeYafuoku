<?php
/**
 * Copyright 2009 - 2013, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2009 - 2013, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * TheVoid class
 *
 * @package       Cake.Test.Case.Model
 */
class VoidYafuokuModel extends CakeTestModel {

/**
 * name property
 *
 * @var string 'TheVoid'
 */
	public $name = 'VoidYafuokuModel';

/**
 * useTable property
 *
 * @var bool false
 */
	public $useTable = false;

/**
 * actsAs property
 *
 * @var array
 */
	public $actsAs = array('CakeYafuoku.Browser');
}

/**
 * YafuokuTestCase
 *
 * @package YafuokuBrowser
 * @subpackage YafuokuBrowser.tests.cases.behaviors
 */
class YafuokuTest extends CakeTestCase {

/**
 * setUp
 *
 * @return void
 */
	public function setUp() {
		$this->cookieFilePath =
		$this->testCase = array(
			'sellingUrl' => 'http://page19.auctions.yahoo.co.jp/jp/auction/x299208220',
			'watch' => 14,
			'store_keyword' => '#1003ⅶ∈∇リクライニング北欧モダン',
			'exhibitUrl' => 'http://page22.auctions.yahoo.co.jp/jp/auction/l205446575',
			'StartPrice' => '28000'
		);

		$this->Model = new VoidYafuokuModel();
		$this->Model->Behaviors->load('CakeYafuoku.Browser', array(
			'id' => 'izuya_market',
			'pass' => 'aaa888',
			'cookieFilePath' => TMP . 'testCakeYafuoku' . DS . 'izuya_market.cookie',
			'log' => true,
			'fullBaseUrl' => 'http://localhost/noah'
			// 'id' => '<your_account>',
			// 'pass' => '<your_pass>'
			// 'cookieFilePath' => null,
			// 'userAgent' => 'Mozilla/5.0 (Windows NT 6.1; rv:5.0) Gecko/20100101 Firefox/5.02',
			// 'log' => true,
			// 'fullBaseUrl' => FULL_BASE_URL
		));
	}

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Model);
	}

/**
 * test login method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testLogin() {
		$dir = dirname($this->cookieFilePath);
		if (file_exists($dir)) {
			$dhandle = opendir($dir);
			if ($dhandle) {
				while (false !== ($fname = readdir($dhandle))) {
					if (is_dir($dir . DS . $fname)) {
						if (($fname != '.') && ($fname != '..')) {
							$this->rmdir_all($dir . DS . $fname);
						}
					} else {
						unlink($dir . DS . $fname);
					}
				}
				closedir($dhandle);
			}
			rmdir($dir);
		}
		$this->assertTrue($this->Model->login());

		$this->Model->Behaviors->load('Browser', array(
			'id' => null,
			'pass' => null
		));
		$this->assertFalse($this->Model->login());
	}

/**
 * test itemInfoFromCurl method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testItemInfoFromCurl() {
		$itemInfo = $this->Model->itemInfoFromCurl($this->testCase['sellingUrl']);
		$this->assertEquals($this->testCase['watch'], $itemInfo['watch']);
		$this->assertEquals($this->testCase['store_keyword'], $itemInfo['store_keyword']);
	}

/**
 * test itemList method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testItemList() {
		// use api closed list
		$list = $this->Model->itemList('closed', true);

		$result = array_key_exists('ResultSet', $list[0]);
		$this->assertTrue($result);

		$totalResults = $list[0]['ResultSet']['totalResultsAvailable'];
		if ($totalResults > 0) {
			$item = $list[0]['ResultSet']['Result'][0];

			$result = array_key_exists('AuctionID', $item);
			$this->assertTrue($result);
		}

		$list = $this->Model->itemList('closed', false);

		$result = array_key_exists('ResultSet', $list[0]);
		$this->assertTrue($result);

		$totalResults = $list[0]['ResultSet']['totalResultsAvailable'];
		if ($totalResults > 0) {
			$item = $list[0]['ResultSet']['Result'][0];

			$result = array_key_exists('AuctionID', $item);
			$this->assertTrue($result);
		}

		// use api selling list
		$list = $this->Model->itemList('selling');

		$result = array_key_exists('ResultSet', $list[0]);
		$this->assertTrue($result);

		$totalResults = $list[0]['ResultSet']['totalResultsAvailable'];
		if ($totalResults > 0) {
			$item = $list[0]['ResultSet']['Result'][0];

			$result = array_key_exists('AuctionID', $item);
			$this->assertTrue($result);
		}

		// use curl scraping
		$this->Model->Behaviors->load('Browser', array(
			'fullBaseUrl' => ''
		));
		$list = $this->Model->itemList('closed', true);
		if (count($list) > 0) {
			$result = array_key_exists('auctionId', $list[0][0]);
			$this->assertTrue($result);
		}

		$list = $this->Model->itemList('closed', false);
		if (count($list) > 0) {
			$result = array_key_exists('auctionId', $list[0][0]);
			$this->assertTrue($result);
		}

		$list = $this->Model->itemList('selling');
		if (count($list) > 0) {
			$result = array_key_exists('auctionId', $list[0][0]);
			$this->assertTrue($result);
		}
	}

/**
 * testReExhibit method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testReExhibit() {
		$url = $this->testCase['exhibitUrl'];
		$option['StartPrice'] = $this->testCase['StartPrice'];
		$result = $this->Model->reExhibit($url, $option);
		$result = $result ? true : false;
		$this->assertTrue($result);
	}

/**
 * testCancelAuction method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testCancelAuction() {
		$result = $this->Model->cancelAuction($this->testCase['exhibitUrl']);
		$this->assertTrue($result);
	}

}
