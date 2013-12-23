<?php
/**
 * CakeYafuoku BrowserBehavior
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
 * @package       CakeYafuoku.Model.Behavior
 * @since         Yafuoku 0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('YahooCurl', 'CakeYafuoku.Lib');
App::uses('YahooLogin', 'CakeYafuoku.Lib');
App::uses('YahooApi', 'CakeYafuoku.Lib');

/**
 * Class BrowserBehavior
 *
 * @package       Yafuoku.Model.Behavior
 * @since         Yafuoku 0.1
 */
class BrowserBehavior extends ModelBehavior {

/**
 * Behavior settings
 *
 * @var array
 */
	public $settings = array();

/**
 * Default setting values
 *
 * @var array
 */
	protected $_defaults = array(
		'id' => null,
		'pass' => null,
		'cookieFilePath' => null,
		'userAgent' => 'Mozilla/5.0 (Windows NT 6.1; rv:5.0) Gecko/20100101 Firefox/5.02',
		'log' => false,
		'fullBaseUrl' => FULL_BASE_URL
	);

/**
 * Setup the behavior and import required classes.
 *
 * @param \Model|object $Model Model using the behavior
 * @param array $settings Settings to override for model.
 * @return void
 */
	public function setup(Model $Model, $settings = null) {
		if ($settings) {
			$this->settings[$Model->alias] = array_merge($this->_defaults, $settings);
		} else {
			$this->settings[$Model->alias] = $this->_defaults;
		}
	}

/**
 * main login method
 *
 * @param \Model|object $Model Model using the behavior
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function login(Model $Model) {
		return YahooLogin::login($this->settings[$Model->alias]);
	}

/**
 * initLoginStatus initialize login status
 *
 * @param \Model|object $Model Model using the behavior
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function initLoginStatus(Model $Model) {
		if (!YahooLogin::loginTest($this->settings[$Model->alias])) {
			if (!$this->login($Model)) {
				trigger_error('login false unknown error occurred');
				return false;
			}
		}
		return true;
	}

/**
 * itemInfoFromCurl method
 * getting item information which are "access", "watchlist", "store keyword"
 *
 * @param \Model|object $Model Model using the behavior
 * @param string $url url
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemInfoFromCurl(Model $Model, $url) {
		return YahooCurl::itemInfo($this->settings[$Model->alias], $url);
	}

/**
 * itemInfo method
 * getting item information
 *
 * @param \Model|object $Model Model using the behavior
 * @param string $url url
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemInfo(Model $Model, $url) {
		$result = YahooApi::itemInfo($this->settings[$Model->alias], $url);
		if ($result) {
			return $result;
		}
		return YahooCurl::itemInfo($this->settings[$Model->alias], $url);
	}

/**
 * itemList method
 * Yahoo api is not stable so backup with curl scraping
 *
 * @param \Model|object $Model  Model using the behavior
 * @param array         $type   list type 'closed' or 'selling'
 * @param bool          $isSold list type ture => sold
 * @param int           $offset page offset
 * @param int           $limit  page limit
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemList(Model $Model, $type, $isSold = true, $offset = 1, $limit = 1) {
		$list = array();
		$settings = $this->settings[$Model->alias];
		if (!$this->initLoginStatus($Model)) {
			return false;
		}

		$token = YahooApi::yConnect($settings);
		if (!$token) {
			return YahooCurl::itemList($settings, $type, $isSold, $offset, $limit);
		}
		$list = YahooApi::itemList($settings, $type, $token, $isSold, $offset, $limit);
		if (!$list) {
			return YahooCurl::itemList($settings, $type, $isSold, $offset, $limit);
		}
		return $list;
	}

/**
 * reExhibitAll method re-exhibit all not sold items
 *
 * @param \Model|object $Model Model using the behavior
 * @param array         $list  target item list
 *     array(
 *         array(
 *             'url' => 'target url',
 *             'Description' => 'description',
 *             see all option at self::reExhibit()
 *         )
 *     )
 * @param string        $error error
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibitAll(Model $Model, $list, &$error = '') {
		$urls = array();
		$settings = $this->settings[$Model->alias];
		if (!YahooLogin::initLoginStatus($settings)) {
			return false;
		}

		foreach ($list as $item) {
			$url = $this->reExhibit($Model, $item['url'], $item, $error);
			if ($url) {
				$urls[] = $url;
			}
		}

		return $error ? false : true;
	}

/**
 * reExhibit method
 *
 * @param \Model|object $Model   Model using the behavior
 * @param string        $url     exhibit item url
 * @param array         $options exhibit options
 * @param string        &$error  error message
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibit(Model $Model, $url, $option = array(), &$error = '') {
		$settings = $this->settings[$Model->alias];
		$url = YahooCurl::reExhibit($settings, $url, $option, $error);
		return $url ? $url : false;
	}

/**
 * cancelAuction method
 *
 * @param \Model|object $Model   Model using the behavior
 * @param string        $url     target item url
 * @param string        &$error  error message
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function cancelAuction(Model $Model, $url, &$error = '') {
		$settings = $this->settings[$Model->alias];
		if (YahooCurl::cancelAuction($settings, $url, $error)) {
			return true;
		}
		return false;
	}

}
