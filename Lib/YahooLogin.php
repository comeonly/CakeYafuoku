<?php
/**
 * Yafuoku YahooLogin class.
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
 * @package       CakeYafuoku.Lib
 * @since         Yafuoku 0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 **/

App::uses('CurlUtility', 'CakeYafuoku.Lib');

/**
 * Class YahooLogin
 *
 * @package       CakeYafuoku.Lib
 * @since         Yafuoku 0.1
 */
class YahooLogin extends CurlUtility {

/**
 * get post params from login HTML body
 *
 * @param {string} $yahooId  yahoo id
 * @param {string} $pass     yahoo id password
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function loginSetPostParams($yahooId, $pass) {
		$body = self::getBody('https://login.yahoo.co.jp/config/login?');
		$queryPath = htmlqp($body, null, self::$_qpOption['euc-jp']);
		$postFields['login'] = $yahooId;
		$postFields['passwd'] = $pass;

		// get <input> params
		$inputs = $queryPath->find('input');
		foreach ($inputs->get() as $input) {
			$name = $input->getAttribute('name');
			if ($name === '.albatross' || $name === '.nojs' || $name === 'login' || $name === 'passwd' || empty($name)) {
				continue;
			}
			$value = $input->getAttribute('value');
			$postFields[$name] = $value;
		}

		// get ".albatross" value in javascript
		$scripts = $queryPath->find('script');
		foreach ($scripts as $script) {
			$isMatch = preg_match(
				'/document\.getElementsByName\("\.albatross"\)\[0\]\.value = "(.+)";/',
				$script->innerHtml(),
				$match
			);
			if ($isMatch) {
				$postFields['.albatross'] = $match[1];
			}
		}

		self::setPostFields($postFields);
	}

/**
 * main login method
 *
 * @param array $settings curl and yahoo settings
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function login($settings) {
		self::initialize($settings);

		// get first cookie
		self::getBody('http://auctions.yahoo.co.jp/');

		self::loginSetPostParams($settings['id'], $settings['pass']);

		// login needs more than 3sec sleep
		sleep(3);

		// submit login
		self::getBody(
			'https://login.yahoo.co.jp/config/login',
			'https://login.yahoo.co.jp/config/login'
		);

		self::finalize($settings);
		return self::loginTest($settings);
	}

/**
 * set login post params from login view
 *
 * @param \Model|object $Model Model using the behavior
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function loginTest($settings) {
		self::initialize($settings);
		$body = self::getBody('http://auctions.yahoo.co.jp/');
		$queryPath = htmlqp($body);
		$account = $queryPath->find('.yjmthloginarea strong')->text();
		self::finalize($settings);
		return $account === $settings['id'];
	}

}
