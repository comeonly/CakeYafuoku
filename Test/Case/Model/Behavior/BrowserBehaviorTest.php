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
 * YafuokuTestCase
 *
 * @package YafuokuBrowser
 * @subpackage YafuokuBrowser.tests.cases.behaviors
 */
class YafuokuTest extends CakeTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array();

/**
 * testAccount
 *
 * @var array
 */
	public $testAccount = array(
		'id' => '<your_account>',
		'pass' => '<your_pass>'
	);

/**
 * testSellingItem
 *
 * @var array
 */
	public $testSellingItem = array(
		'url' => 'http://page17.auctions.yahoo.co.jp/jp/auction/v0000000000',
		'access' => 0,
		'watch' => 0,
		'store_keyword' => 'foobar',
	);

/**
 * testFullBaseUrl
 *
 * @var string
 */
	public $testFullBaseUrl = 'http://localhost/yafuoku';

/**
 * testReExhibitItem
 *
 * @var array
 */
	public $testReExhibitItem = array(
		'url' => 'http://page19.auctions.yahoo.co.jp/jp/auction/x111111111',
		'StartPrice' => '100000',
		'BidOrBuyPrice' => ''
	);

/**
 * testSoldList
 *
 * @var array
 */
	public $testSoldList = array(array(
		'ResultSet' => array(
			'totalResultsAvailable' => 777,
			'Result' => array(
				array(
					'AuctionID' => 't999999999',
				)
			)
		)
	));

/**
 * testNotSoldList
 *
 * @var array
 */
	public $testNotSoldList = array(array(
		'ResultSet' => array(
			'totalResultsAvailable' => 999,
			'Result' => array(
				array(
					'AuctionID' => 'x999999999',
				)
		)
	)
	));

/**
 * testSellingList
 *
 * @var array
 */
	public $testSellingList = array(array(
		'ResultSet' => array(
			'totalResultsAvailable' => 777,
			'Result' => array(
				array(
					'AuctionID' => 'p888888888',
				)
		)
	)
	));

/**
 * setUp
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->Article = ClassRegistry::init('Article');
		$this->Article->Behaviors->attach('Yafuoku.Browser');
		$this->Article->Behaviors->load('Browser', $this->testAccount);
	}

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Article);
	}

/**
 * test login method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testLogin() {
		$dir = TMP . 'cookies';
		if (!file_exists($dir)) {
			return;
		}
		$dhandle = opendir($dir);
		if ($dhandle) {
			while (false !== ($fname = readdir($dhandle))) {
				if (is_dir( "{$dir}/{$fname}" )) {
					if (($fname != '.') && ($fname != '..')) {
						$this->rmdir_all("$dir/$fname");
					}
				} else {
					unlink("{$dir}/{$fname}");
				}
			}
			closedir($dhandle);
		}
		rmdir($dir);
		$this->assertTrue($this->Article->login());

		$this->Article->Behaviors->load('Browser', array(
			'id' => null,
			'pass' => null
		));
		$this->assertFalse($this->Article->login());
	}

/**
 * test itemInfoFromCurl method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testItemInfoFromCurl() {
		$itemInfo = $this->Article->itemInfoFromCurl($this->testSellingItem['url']);
		// $this->assertEquals($this->testSellingItem['access'], $itemInfo['access']);
		$this->assertEquals($this->testSellingItem['watch'], $itemInfo['watch']);
		$this->assertEquals($this->testSellingItem['store_keyword'], $itemInfo['store_keyword']);
	}

/**
 * test itemList method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testItemList() {
		// use api closed list
		$this->Article->Behaviors->load('Browser', array(
			'fullBaseUrl' => $this->testFullBaseUrl,
		));
		$list = $this->Article->itemList('closed', true);
		$this->assertEquals(
			$this->testSoldList[0]['ResultSet']['totalResultsAvailable'],
			$list[0]['ResultSet']['totalResultsAvailable']
		);
		$this->assertTrue(array_key_exists(
			'AuctionID',
			$list[0]['ResultSet']['Result'][0]
		));
		$list = $this->Article->itemList('closed', false);
		$this->assertEquals(
			$this->testNotSoldList[0]['ResultSet']['totalResultsAvailable'],
			$list[0]['ResultSet']['totalResultsAvailable']
		);
		$this->assertTrue(array_key_exists(
			'AuctionID',
			$list[0]['ResultSet']['Result'][0]
		));

		// use api selling list
		$list = $this->Article->itemList('selling');
		$this->assertEquals(
			$this->testSellingList[0]['ResultSet']['totalResultsAvailable'],
			$list[0]['ResultSet']['totalResultsAvailable']
		);
		$this->assertTrue(array_key_exists(
			'AuctionID',
			$list[0]['ResultSet']['Result'][0]
		));

		// use curl scraping
		$this->Article->Behaviors->load('Browser', array(
			'fullBaseUrl' => ''
		));
		$list = $this->Article->itemList('closed', true);
		$this->assertEquals(
			$this->testSoldList[0]['ResultSet']['Result'][0]['AuctionID'],
			$list[0][0]['auctionId']
		);
		$list = $this->Article->itemList('closed', false);
		$this->assertEquals(
			$this->testNotSoldList[0]['ResultSet']['Result'][0]['AuctionID'],
			$list[0][0]['auctionId']
		);

		$list = $this->Article->itemList('selling');
		$this->assertEquals(
			$this->testSellingList[0]['ResultSet']['Result'][0]['AuctionID'],
			$list[0][0]['auctionId']
		);
	}

/**
 * testReExhibit method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function testReExhibit() {
		$result = $this->Article->reExhibit($this->testReExhibitItem['url']);
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
		$result = $this->Article->cancelAuction($this->testReExhibitItem['url']);
		$this->assertTrue($result);
	}

}
