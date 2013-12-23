<?php
/**
 * Yafuoku YahooReExhibit class.
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

App::uses('CurlUtility', 'CakeYafuoku.Lib');

/**
 * Class YahooReExhibit
 *
 * @package       Yafuoku.Lib
 * @since         Yafuoku 0.1
 */
class YahooReExhibit extends CurlUtility {

/**
 * reExhibitUrl method get first url for re-exhibit
 *
 * @param string $url   item url <出品ページ>
 * @param string $error error
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibitUrl($url, &$error = '') {
		$body = self::getBody($url);
		$queryPath = htmlqp($body, null, self::$_qpOption['utf-8']);
		if ($queryPath->find('#modAlertBox a')->innerHtml() !== '再出品') {
			$error .= '既に出品済みです';
			return false;
		}
		return $queryPath->find('#modAlertBox a')->attr('href');
	}

/**
 * reExhibitEdit method edit exhibit page view
 *
 * @param string $url    item url <編集ページ>
 * @param array  $option option settings
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibitEdit($url, $option = array()) {
		$params = array();

		// parse HTML body
		$body = self::getBody($url);

		$queryPath = htmlqp($body, null, self::$_qpOption['euc-jp'])
			->find("form[name='auction']");

		// get form default values
		$params = self::formValues($queryPath);

		// get action url
		$url = $queryPath->attr('action');

		// array replace options
		foreach ($params as $name => $value) {
			if (array_key_exists($name, $option)) {
				if (is_array($option[$name])) {
					if ($name === 'Description') {
						$params[$name] = self::initExhibitDescription(
							$params[$name],
							$option[$name]
						);
					}
					continue;
				}
				$params[$name] = $option[$name];
			}
		}
		$params['Description'] = self::initExhibitDescription($params['Description']);

		// Maybe: need this coz this runinng as javascript on original
		$params['Description_plain'] = str_replace(
			array('%', '"', "'", '&'),
			array('%25', '%22', '%27', '%26'),
			$params['Description']
		);

		foreach ($params as $name => $value) {
			$params[$name] = mb_convert_encoding($value, 'EUC-JP', 'UTF-8');
		}

		self::setPostFields($params);

		return $url;
	}

/**
 * initExhibitDescription method
 *
 * @param string $description exhibit description
 * @param array  $option      exhibit description option
 *     array(
 *         'pattern' => 'preg pattern',
 *         'replacement' => 'string replacement'
 *     )
 * @return string
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function initExhibitDescription($description, $option = array()) {
		$description = str_replace(
			array("&#10;", "&#13;", "\n", '<br/>'),
			array('', '', '', '<br>'),
			$description
		);

		if ($option) {
			$description = preg_replace(
				$option['pattern'],
				$option['replacement'],
				$description
			);
		}

		return $description;
	}

/**
 * reExhibitPreview method get confirmation view
 *
 * @param string $url   item url <確認ページ>
 * @param string $error error
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibitPreview($url, &$error = '') {
		$body = self::getBody($url);

		$queryPath = htmlqp($body, null, self::$_qpOption['euc-jp']);

		// check error
		$errorBox = $queryPath->find('.decErrorBox');
		foreach ($errorBox as $value) {
			$error .= $value->text() . "\n";
		}
		if ($error) {
			return false;
		}

		$params = array();
		$inputs = $queryPath->find("form[name='auction']")->find("input[type='hidden']");
		foreach ($inputs as $input) {
			$params[$input->attr('name')] = mb_convert_encoding($input->attr('value'), 'EUC-JP', 'UTF-8');
		}

		CurlUtility::setPostFields($params);

		// get action url
		$buttons = $queryPath->find("input[type='button']");
		foreach ($buttons as $button) {
			$isMatch = preg_match(
				"/Ya.submit\(document.auction, '(.+)'\);disabledSubmit/",
				$button->attr('onclick'),
				$match
			);
			if ($isMatch) {
				$url = $match[1];
				break;
			}
		}
		return $url;
	}

/**
 * reExhibitSubmit method re-exhibit
 *
 * @param string $url   item url <出品完了ページ>
 * @param string $error error
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibitSubmit($url, &$error = '') {
		$body = self::getBody($url);
		$queryPath = htmlqp($body, null, self::$_qpOption['euc-jp']);

		$url = $queryPath->find('#modInfoTxt a')->eq(0)->attr('href');
		if ($url) {
			return $url;
		}

		$error .= $queryPath->find('#modAlertBox strong')->text() . "\n";
		if ($error) {
			return false;
		}
	}

}
