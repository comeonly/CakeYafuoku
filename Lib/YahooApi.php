<?php
/**
 * Yafuoku YahooApi class.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Yafuoku.Lib
 * @since         Yafuoku 0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 **/

App::uses('CurlUtility', 'Yafuoku.Lib');

/**
 * Class YahooCurl
 *
 * @package       Yafuoku.Lib
 * @since         Yafuoku 0.1
 */
class YahooApi extends CurlUtility {

/**
 * appid
 *
 * @var string
 */
	protected static $_appid = '<your_app_id';

/**
 * yConnect method set oauth token
 *
 * @param array $settings curl and yahoo settings
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function yConnect($settings) {
		self::initialize($settings);
		$fullBaseUrl = $settings['fullBaseUrl'];
		$url = $fullBaseUrl . Router::url(
			array('controller' => 'auth', 'action' => 'yahoojp')
		);
		$body = self::getBody($url);
		$queryPath = htmlqp($body);
		$opauth = $queryPath->find("input[name='opauth']")->attr('value');
		$authResponse = unserialize(base64_decode($opauth));

		if ($authResponse && array_key_exists('auth', $authResponse)) {
			$token = $authResponse['auth']['credentials']['token'];
			self::finalize($settings);
			return $token;
		}
		self::finalize($settings);
		return false;
	}

/**
 * itemList method
 *
 * @param array  $settings curl and yahoo settings
 * @param array  $type   list type 'closed' or 'selling'
 * @param string $token    oauth token
 * @param bool   $isSold   list type ture => sold
 * @param int    $offset   page offset
 * @param int    $limit    page limit
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemList($settings, $type, $token, $isSold, $offset, $limit) {
		$list = array();
		$listType = $isSold ? 'sold' : 'not_sold';
		self::initialize($settings);
		curl_setopt($this->curlHandler, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $token . "\n"
		));
		for ($i = $offset; $i < $limit + 1; $i++) {
			if ($type === 'closed') {
				$url = 'https://auctions.yahooapis.jp/AuctionWebService/V2/myCloseList?';
				$query = array(
					'output' => 'php',
					'start' => $i,
					'list' => $listType
				);
			} else {
				$url = 'https://auctions.yahooapis.jp/AuctionWebService/V2/mySellingList?';
				$query = array(
					'output' => 'php',
					'start' => $i,
				);
			}

			$httpQuery = http_build_query($query);
			$result = unserialize(self::getBody($url . $httpQuery));
			if (array_key_exists('ResultSet', $result)) {
				$list[] = $result;
			} else {
				break;
			}
		}
		self::finalize($settings);
		return $list;
	}

/**
 * itemInfo method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemInfo($settings, $url) {
		$info = array();
		self::initialize($settings);
		if (preg_match('/auction\/([a-z][0-9]+)/', $url, $match)) {
			$auctionId = $match[1];
		} else {
			return false;
		}

		$requestUrl = 'http://auctions.yahooapis.jp/AuctionWebService/V2/auctionItem?';
		$query = array(
			'appid' => self::$_appid,
			'output' => 'php',
			'auctionID' => $auctionId
		);

		$httpQuery = http_build_query($query);
		$info = unserialize(self::getBody($requestUrl . $httpQuery));

		self::finalize($settings);
		return $info;
	}

}
