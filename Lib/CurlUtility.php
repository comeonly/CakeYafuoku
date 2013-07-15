<?php
/**
 * Yafuoku CurlUtility class.
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

/**
 * Class CurlUtility
 *
 * @package       Yafuoku.Lib
 * @since         Yafuoku 0.1
 */
class CurlUtility {

/**
 * curl handler
 *
 * @var stream
 */
	public $curlHandler = null;

/**
 * log file pointer
 *
 * @var stream
 */
	public $filePointer = null;

/**
 * query path encode option
 *
 * @var array
 */
	protected static $_qpOption = array(
		'euc-jp' => array(
			'convert_from_encoding' => 'euc-jp',
			'convert_to_encoding' => 'euc-jp',
		),
		'utf-8' => array(
			'convert_from_encoding' => 'UTF-8',
			'convert_to_encoding' => 'UTF-8',
		)
	);

/**
 * initialize method
 *
 * @param array $settings curl settings
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function initialize($settings) {
		$this->curlHandler = curl_init();
		if ($settings['log']) {
			$this->filePointer = fopen(TMP . 'logs/curl.log', 'a');
			curl_setopt($this->curlHandler, CURLOPT_VERBOSE, true);
			curl_setopt($this->curlHandler, CURLOPT_STDERR, $this->filePointer);
		}
		if (!$settings['cookieFilePath']) {
			$settings['cookieFilePath'] = TMP . 'cookies/' . $settings['id'] . '.cookie';
		}
		curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curlHandler, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($this->curlHandler, CURLOPT_AUTOREFERER, 1);
		if ($settings['userAgent']) {
			curl_setopt($this->curlHandler, CURLOPT_USERAGENT, $settings['userAgent']);
		}
		if ($settings['cookieFilePath']) {
			if (!file_exists(TMP . 'cookies')) {
				mkdir(TMP . 'cookies');
			}
			curl_setopt($this->curlHandler, CURLOPT_COOKIEFILE, $settings['cookieFilePath']);
			curl_setopt($this->curlHandler, CURLOPT_COOKIEJAR, $settings['cookieFilePath']);
		}
	}

/**
 * finalize method
 *
 * @param array $settings curl settings
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function finalize($settings) {
		if ($settings['log']) {
			fclose($this->filePointer);
		}
		curl_close($this->curlHandler);
	}

/**
 * get body by curl
 *
 * @param {string} $url     target url
 * @param {string} $referer header referer
 * @return string
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function getBody($url, $referer = null) {
		curl_setopt($this->curlHandler, CURLOPT_URL, $url);
		if ($referer) {
			curl_setopt($this->curlHandler, CURLOPT_REFERER, $referer);
		}
		return curl_exec($this->curlHandler);
	}

/**
 * setPostFields method
 *
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function setPostFields($postFields) {
		$postFields = http_build_query($postFields);
		curl_setopt($this->curlHandler, CURLOPT_POST, true);
		curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, $postFields);
	}

/**
 * formValues method
 *
 * @param object $queryPath queryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function formValues($queryPath) {
		$params = array();
		$params = array_merge($params, self::getInputParams($queryPath));
		$params = array_merge($params, self::getTextareaParams($queryPath));
		$params = array_merge($params, self::getSelectParams($queryPath));
		return $params;
	}

/**
 * getInputParams method
 *
 * @param object $queryPath queryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function getInputParams($queryPath) {
		$params = array();
		$inputs = $queryPath->find('input');
		foreach ($inputs->get() as $input) {
			$type = $input->getAttribute('type');
			switch ($type) {
				case 'hidden':
				case 'text':
					$params[$input->getAttribute('name')]
						= $input->getAttribute('value');
				case 'radio':
				case 'checkbox':
					if ($input->getAttribute('selected')
						|| $input->getAttribute('checked')
					) {
						$params[$input->getAttribute('name')]
							= $input->getAttribute('value');
					}
					break;
				default:
					break;
			}
		}
		return $params;
	}

/**
 * getTextareaParams method
 *
 * @param object $queryPath queryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function getTextareaParams($queryPath) {
		$params = array();
		$textareas = $queryPath->find('textarea');
		foreach ($textareas as $textarea) {
			$params[$textarea->attr('name')] = $textarea->innerHtml();
		}
		return $params;
	}

/**
 * getSelectParams method
 *
 * @param object $queryPath queryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function getSelectParams($queryPath) {
		$params = array();
		$selects = $queryPath->find('select');
		foreach ($selects as $select) {
			$optionTags = $select->find('option');
			foreach ($optionTags->get() as $optionTag) {
				if ($optionTag->getAttribute('selected')) {
					$params[$select->attr('name')]
						= $optionTag->getAttribute('value');
				}
			}
		}
		return $params;
	}

}
