<?php
/**
 * Yafuoku YahooCurl class.
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

App::uses('YahooLogin', 'CakeYafuoku.Lib');
App::uses('YahooReExhibit', 'CakeYafuoku.Lib');
App::uses('CurlUtility', 'CakeYafuoku.Lib');

/**
 * Class YahooCurl
 *
 * @package       CakeYafuoku.Lib
 * @since         Yafuoku 0.1
 */
class YahooCurl extends CurlUtility {

/**
 * itemInfo method
 * getting item information which are "access", "watchlist", "store keyword"
 *
 * @param array  $settings curl and yahoo settings
 * @param string $url url
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemInfo($settings, $url) {
		self::initialize($settings);
		$body = self::getBody($url);
		$queryPath = htmlqp($body, null, self::$_qpOption['utf-8']);
		$sellInfo = $queryPath->find('#modSellInfoB table');
		$firstTableTd = $sellInfo->eq(0)->find('td');
		$secondTableTd = $sellInfo->eq(1)->find('td');
		$price = $queryPath->find("p[property='auction:Price']")->innerHtml();
		$price = str_replace(array(' ', '円', ','), '', $price);
		$itemInfo['access'] = str_replace('： ', '', $firstTableTd->eq(0)->innerHtml());
		$itemInfo['watch'] = str_replace('： ', '', $firstTableTd->eq(1)->innerHtml());
		$itemInfo['store_keyword'] = $secondTableTd->eq(0)->innerHtml();
		$itemInfo['price'] = $price;
		self::finalize($settings);
		return $itemInfo;
	}

/**
 * itemList method
 *
 * @param array $settings curl and yahoo settings
 * @param bool $isSold list type ture => sold
 * @param int  $offset page offset
 * @param int  $limit  page limit
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemList($settings, $type, $isSold, $offset, $limit) {
		self::initialize($settings);
		$list = array();
		for ($i = $offset; $i < $limit + 1; $i++) {
			$result = self::itemsFromListBody($type, $i, $isSold);
			if ($result) {
				$list[] = $result;
			} else {
				break;
			}
		}
		self::finalize($settings);
		return $list;
	}

/**
 * itemsFromListBody method
 *
 * @param int  $page    list page number
 * @param bool $isSold  list type true hasWinner=0
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function itemsFromListBody($type, $page, $isSold) {
		$url = 'https://order.auctions.yahoo.co.jp/jp/show/mystatus?';
		if ($type === 'closed') {
			$query = array(
				'select' => 'closed',
				'page' => $page,
				'hasWinner' => $isSold ? '1' : '0'
			);
		} else {
			$query = array(
				'select' => 'selling',
				'page' => $page,
			);
		}
		$httpQuery = http_build_query($query);

		$body = self::getBody($url . $httpQuery);

		if ($type === 'closed') {
			$queryPath = htmlqp(
				$body,
				"form[name='itemList'] table",
				self::$_qpOption['euc-jp']
			);
			if ($isSold) {
				return self::soldList($queryPath);
			} else {
				return self::notSoldList($queryPath);
			}
		} else {
			$queryPath = htmlqp(
				$body,
				"#acWrContents table table table table",
				self::$_qpOption['euc-jp']
			);
			return self::sellingList($queryPath);
		}
	}

/**
 * soldList method
 *
 * @param object $queryPath QueryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function soldList($queryPath) {
		$list = array();
		$queryPath = $queryPath->find('tr');
		$count = $queryPath->size() - 2;
		for ($i = 1; $i < $count; $i++) {
			$item = $queryPath->eq($i)->find('td');
			$price = $item->eq(3)->find('b')->innerHtml();
			$endTime = str_replace(
				array('月', '日', '時', '分'),
				array('-', '', ':', ''),
				$item->eq(4)->innerHtml()
			);
			$year = date('Y');
			if (date('m-d') === '1-1' && date('m', strtotime($endTime)) > 1) {
				$endTime = ($year - 1) . '-' . $endTime . ':00';
			} else {
				$endTime = $year . '-' . $endTime . ':00';
			}
			$list[] = array(
				'auctionId' => $item->eq(1)->innerHtml(),
				'title' => $item->eq(2)->find('a')->innerHtml(),
				'price' => str_replace(array('円', ' ', ','), '', $price),
				'endTime' => $endTime
			);
		}
		return $list;
	}

/**
 * notSoldList method
 *
 * @param object $queryPath QueryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function notSoldList($queryPath) {
		$queryPath = $queryPath->remove('table')->find('tr');
		if ($queryPath->size() < 50) {
			$count = $queryPath->size() / 3 + 1;
		} else {
			$count = 50 + 1;
		}
		for ($i = 1; $i < $count; $i++) {
			$item = $queryPath->eq($i)->find('td');
			$price = $item->eq(3)->find('b')->innerHtml();
			$endTime = str_replace(
				array('月', '日', '時', '分'),
				array('-', '', ':', ''),
				$item->eq(4)->innerHtml()
			);
			$year = date('Y');
			if (date('m-d') === '1-1' && date('m', strtotime($endTime)) > 1) {
				$endTime = ($year - 1) . '-' . $endTime . ':00';
			} else {
				$endTime = $year . '-' . $endTime . ':00';
			}
			$list[] = array(
				'auctionId' => $item->eq(1)->innerHtml(),
				'title' => $item->eq(2)->find('a')->innerHtml(),
				'price' => str_replace(array('円', ' ', ','), '', $price),
				'endTime' => $endTime,
				'reExhibitUrl' => $item->eq(7)->find('a')->attr('href'),
			);
		}
		return $list;
	}

/**
 * sellingList method
 *
 * @param object $queryPath QueryPath object
 * @return array
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function sellingList($queryPath) {
		$items = $queryPath->find('tr');
		$count = $items->size() - 6;
		for ($i = 4; $i < $count; $i++) {
			$item = $items->eq($i)->find('td');
			$list[] = array(
				'auctionId' => $item->eq(0)->innerHtml(),
				'title' => $item->eq(1)->find('a')->innerHtml(),
			);
		}
		return $list;
	}

/**
 * reExhibit method
 *
 * @param array  $settings curl and yahoo settings
 * @param string $url      exhibit item url
 * @param array  $options  array(
 *     'Title' => 'タイトル',
 *     'StartPrice' => '開始値',
 *     'BidOrBuyPrice' => '即決価格',
 *     'Quantity' => '数量',
 *     'city' => '発送元市区町村',
 *    'loc_cd' => array(
 *        '' =>'都道府県を選択',
 *         (int) 1 =>'北海道',
 *         (int) 2 =>'青森県',
 *         (int) 3 =>'岩手県',
 *         (int) 4 =>'宮城県',
 *         (int) 5 =>'秋田県',
 *         (int) 6 =>'山形県',
 *         (int) 7 =>'福島県',
 *         (int) 8 =>'茨城県',
 *         (int) 9 =>'栃木県',
 *         (int) 10 =>'群馬県',
 *         (int) 11 =>'埼玉県',
 *         (int) 12 =>'千葉県',
 *         (int) 13 =>'東京都',
 *         (int) 14 =>'神奈川県',
 *         (int) 15 =>'山梨県',
 *         (int) 16 =>'長野県',
 *         (int) 17 =>'新潟県',
 *         (int) 18 =>'富山県',
 *         (int) 19 =>'石川県',
 *         (int) 20 =>'福井県',
 *         (int) 21 =>'岐阜県',
 *         (int) 22 =>'静岡県',
 *         (int) 23 =>'愛知県',
 *         (int) 24 =>'三重県',
 *         (int) 25 =>'滋賀県',
 *         (int) 26 =>'京都府',
 *         (int) 27 =>'大阪府',
 *         (int) 28 =>'兵庫県',
 *         (int) 29 =>'奈良県',
 *         (int) 30 =>'和歌山県',
 *         (int) 31 =>'鳥取県',
 *         (int) 32 =>'島根県',
 *         (int) 33 =>'岡山県',
 *         (int) 34 =>'広島県',
 *         (int) 35 =>'山口県',
 *         (int) 36 =>'徳島県',
 *         (int) 37 =>'香川県',
 *         (int) 38 =>'愛媛県',
 *         (int) 39 =>'高知県',
 *         (int) 40 =>'福岡県',
 *         (int) 41 =>'佐賀県',
 *         (int) 42 =>'長崎県',
 *         (int) 43 =>'熊本県',
 *         (int) 44 =>'大分県',
 *         (int) 45 =>'宮崎県',
 *         (int) 46 =>'鹿児島県',
 *         (int) 47 =>'沖縄県',
 *         (int) 48 =>'海外'
 *     ),
 *     'istatus_comment' => '商品状態備考',
 *     'retpolicy_comment' => '返品備考',
 *     'image_comment1' => '画像1備考',
 *     'image_comment2' => '画像2備考',
 *     'image_comment3' => '画像3備考',
 *     'ReservePrice' => '最低落札価格(有料オプション)',
 *     'featuredAmount' => '注目のオークション(有料オプション)',
 *     'shipname1' => '送料表リンクタイトル1',
 *     'shipname2' => '送料表リンクタイトル2',
 *     'shipname3' => '送料表リンクタイトル3',
 *     'shipname4' => '送料表リンクタイトル4',
 *     'shipname5' => '送料表リンクタイトル5',
 *     'shipname6' => '送料表リンクタイトル6',
 *     'shipname7' => '送料表リンクタイトル7',
 *     'shipname8' => '送料表リンクタイトル8',
 *     'shipname9' => '送料表リンクタイトル9',
 *     'shipname10' => '送料表リンクタイトル10',
 *     'aID' => 'オークションID',
 *     'category' => 'オークションカテゴリ',
 *     'Duration' => '期間',
 *     'shipratelink1' => '送料表リンク1',
 *     'shipratelink2' => '送料表リンク2',
 *     'shipratelink3' => '送料表リンク3',
 *     'shipratelink4' => '送料表リンク4',
 *     'shipratelink5' => '送料表リンク5',
 *     'shipratelink6' => '送料表リンク6',
 *     'shipratelink7' => '送料表リンク7',
 *     'shipratelink8' => '送料表リンク8',
 *     'shipratelink9' => '送料表リンク9',
 *     'shipratelink10' => '送料表リンク10'
 *     'Description' => '商品説明文',
 *     'Description_plain_work' => '',
 *     'Description_plain' => ''
 *     // radio
 *     'salesmode' => array(     // 販売形式
 *         (int) 0 => 'auction', // オークション
 *         (int) 1 => 'buynow',  // 定価
 *         (int) 2 => 'offer'    // 定価値下げ交渉あり
 *     ),
 *     'shipping' => array(      // 送料負担
 *         (int) 0 => 'buyer',   // 落札者
 *         (int) 1 => 'seller'   // 出品者
 *     ),
 *     'shiptime' => array(      // 代金支払い
 *         (int) 0 => 'payment', // 発送前
 *         (int) 1 => 'close'    // 発送後
 *     ),
 *     'istatus' => array(       // 商品の状態
 *         (int) 0 => 'used',    // 中古
 *         (int) 1 => 'new',     // 新品
 *         (int) 2 => 'other'    // その他
 *     ),
 *     'retpolicy' => array(     // 返品
 *         (int) 0 => '0',       // 返品不可
 *         (int) 1 => '1'        // 返品可
 *     ),
 *     'charityOption' => array( // チャリティー
 *         (int) 0 => '',        // 参加しない
 *         (int) 1 => '10',      // 10％参加
 *         (int) 2 => '100'      // 全額参加
 *     )
 *     // checkbox
 *     'hasBidLimitQuantity' => array(
 *         (int) 0 => '1'        // 数量制限あり
 *     ),
 *     'BidLimitQuantity' => array(
 *         (int) 1 => '1 ',
 *         (int) 2 => '2 ',
 *         (int) 3 => '3 ',
 *         (int) 4 => '4 ',
 *         (int) 5 => '5 '
 *     ),
 *     'ypmOKChecked' => array(
 *         (int) 0 => 'yes'      // かんたん決済
 *     ),
 *     'abkn1Checked' => array(
 *         (int) 0 => 'yes'      // 銀行決済
 *     ),
 *     'aspj13' => array(
 *         (int) 0 => 'yes'      // ローン決済
 *     ),
 *     'apm1Checked' => array(
 *         (int) 0 => 'yes'      // その他決済(直接引取り)
 *     ),
 *     'minBidRating' => array(
 *         (int) 0 => '0'        // 入札制限
 *     ),
 *     'AutoExtension' => array(
 *         (int) 0 => 'yes'      // 自動延長
 *     ),
 *     'CloseEarly' => array(
 *         (int) 0 => 'yes'      // 早期終了
 *     ),
 *     'boldSelected' => array(
 *         (int) 0 => 'yes'      // 太字テキスト
 *     ),
 *     'highlightlistingSelected' => array(
 *         (int) 0 => 'yes'      // 背景色
 *     ),
 *     'showCase1' => array(
 *         (int) 0 => '1'        // 100円ショーケース
 *     ),
 *     'showCase3' => array(
 *         (int) 0 => '9'        // 夏レジャーショーケース
 *     ),
 *     'showCase4' => array(
 *         (int) 0 => '8'        // ボーナスショーケース
 *     ),
 *     'wrappingSelected' => array(
 *         (int) 0 => 'yes'      // 贈答品アイコン
 *     ),
 *     'affiliate' => array(
 *         (int) 0 => '1'        // アフェリエイト
 *     ),
 *     'affiliateRate' => array(
 *         '' => '',
 *         (int) 1 => '1 ％',
 *         (int) 2 => '2 ％',
 *         (int) 3 => '3 ％',
 *         (int) 4 => '4 ％',
 *         (int) 5 => '5 ％',
 *         (int) 6 => '6 ％',
 *         (int) 7 => '7 ％',
 *         (int) 8 => '8 ％',
 *         (int) 9 => '9 ％',
 *         (int) 10 => '10 ％',
 *         (int) 11 => '11 ％',
 *         (int) 12 => '12 ％',
 *         (int) 13 => '13 ％',
 *         (int) 14 => '14 ％',
 *         (int) 15 => '15 ％',
 *         (int) 16 => '16 ％',
 *         (int) 17 => '17 ％',
 *         (int) 18 => '18 ％',
 *         (int) 19 => '19 ％',
 *         (int) 20 => '20 ％',
 *         (int) 21 => '21 ％',
 *         (int) 22 => '22 ％',
 *         (int) 23 => '23 ％',
 *         (int) 24 => '24 ％',
 *         (int) 25 => '25 ％',
 *         (int) 26 => '26 ％',
 *         (int) 27 => '27 ％',
 *         (int) 28 => '28 ％',
 *         (int) 29 => '29 ％',
 *         (int) 30 => '30 ％',
 *         (int) 31 => '31 ％',
 *         (int) 32 => '32 ％',
 *         (int) 33 => '33 ％',
 *         (int) 34 => '34 ％',
 *         (int) 35 => '35 ％',
 *         (int) 36 => '36 ％',
 *         (int) 37 => '37 ％',
 *         (int) 38 => '38 ％',
 *         (int) 39 => '39 ％',
 *         (int) 40 => '40 ％',
 *         (int) 41 => '41 ％',
 *         (int) 42 => '42 ％',
 *         (int) 43 => '43 ％',
 *         (int) 44 => '44 ％',
 *         (int) 45 => '45 ％',
 *         (int) 46 => '46 ％',
 *         (int) 47 => '47 ％',
 *         (int) 48 => '48 ％',
 *         (int) 49 => '49 ％',
 *         (int) 50 => '50 ％',
 *         (int) 51 => '51 ％',
 *         (int) 52 => '52 ％',
 *         (int) 53 => '53 ％',
 *         (int) 54 => '54 ％',
 *         (int) 55 => '55 ％',
 *         (int) 56 => '56 ％',
 *         (int) 57 => '57 ％',
 *         (int) 58 => '58 ％',
 *         (int) 59 => '59 ％',
 *         (int) 60 => '60 ％',
 *         (int) 61 => '61 ％',
 *         (int) 62 => '62 ％',
 *         (int) 63 => '63 ％',
 *         (int) 64 => '64 ％',
 *         (int) 65 => '65 ％',
 *         (int) 66 => '66 ％',
 *         (int) 67 => '67 ％',
 *         (int) 68 => '68 ％',
 *         (int) 69 => '69 ％',
 *         (int) 70 => '70 ％',
 *         (int) 71 => '71 ％',
 *         (int) 72 => '72 ％',
 *         (int) 73 => '73 ％',
 *         (int) 74 => '74 ％',
 *         (int) 75 => '75 ％',
 *         (int) 76 => '76 ％',
 *         (int) 77 => '77 ％',
 *         (int) 78 => '78 ％',
 *         (int) 79 => '79 ％',
 *         (int) 80 => '80 ％',
 *         (int) 81 => '81 ％',
 *         (int) 82 => '82 ％',
 *         (int) 83 => '83 ％',
 *         (int) 84 => '84 ％',
 *         (int) 85 => '85 ％',
 *         (int) 86 => '86 ％',
 *         (int) 87 => '87 ％',
 *         (int) 88 => '88 ％',
 *         (int) 89 => '89 ％',
 *         (int) 90 => '90 ％',
 *         (int) 91 => '91 ％',
 *         (int) 92 => '92 ％',
 *         (int) 93 => '93 ％',
 *         (int) 94 => '94 ％',
 *         (int) 95 => '95 ％',
 *         (int) 96 => '96 ％',
 *         (int) 97 => '97 ％',
 *         (int) 98 => '98 ％',
 *         (int) 99 => '99 ％'
 *     ),
 *     'starclub' => array(
 *         (int) 0 => '1'        // スタークラブ限定セール
 *     ),
 *     'ypkOK' => array(
 *         (int) 0 => '1'        // はこBOON
 *     ),
 *     'intlOK' => array(
 *         (int) 0 => '1'        // 海外発送
 *     )
 *     'numResubmit' => array(   // 自動再出品
 *         (int) 0 => '0 回',
 *         (int) 1 => '1 回',
 *         (int) 2 => '2 回',
 *         (int) 3 => '3 回'
 *     ),
 *     'giftSelected' => array(  // 目立ちアイコン
 *         '' => '--選択-- ',
 *         (int) 2 => '美品 ',
 *         (int) 3 => '非売品 ',
 *         (int) 4 => '限定品 ',
 *         (int) 5 => '保証書付 ',
 *         (int) 6 => '全巻セット ',
 *         (int) 7 => '正規店購入 ',
 *         (int) 8 => '産地直送
 *  '
 *     ),
 *     'point' => array(         // Yahoo!ポイント
 *         (int) 0 => '--選択--',
 *         (int) 1 => '1 ％',
 *         (int) 2 => '2 ％',
 *         (int) 3 => '3 ％',
 *         (int) 4 => '4 ％',
 *         (int) 5 => '5 ％',
 *         (int) 6 => '6 ％',
 *         (int) 7 => '7 ％',
 *         (int) 8 => '8 ％',
 *         (int) 9 => '9 ％',
 *         (int) 10 => '10 ％',
 *         (int) 11 => '11 ％',
 *         (int) 12 => '12 ％',
 *         (int) 13 => '13 ％',
 *         (int) 14 => '14 ％',
 *         (int) 15 => '15 ％',
 *         (int) 16 => '16 ％',
 *         (int) 17 => '17 ％',
 *         (int) 18 => '18 ％',
 *         (int) 19 => '19 ％',
 *         (int) 20 => '20 ％',
 *         (int) 21 => '21 ％',
 *         (int) 22 => '22 ％',
 *         (int) 23 => '23 ％',
 *         (int) 24 => '24 ％',
 *         (int) 25 => '25 ％',
 *         (int) 26 => '26 ％',
 *         (int) 27 => '27 ％',
 *         (int) 28 => '28 ％',
 *         (int) 29 => '29 ％',
 *         (int) 30 => '30 ％',
 *         (int) 31 => '31 ％',
 *         (int) 32 => '32 ％',
 *         (int) 33 => '33 ％',
 *         (int) 34 => '34 ％',
 *         (int) 35 => '35 ％',
 *         (int) 36 => '36 ％',
 *         (int) 37 => '37 ％',
 *         (int) 38 => '38 ％',
 *         (int) 39 => '39 ％',
 *         (int) 40 => '40 ％',
 *         (int) 41 => '41 ％',
 *         (int) 42 => '42 ％',
 *         (int) 43 => '43 ％',
 *         (int) 44 => '44 ％',
 *         (int) 45 => '45 ％',
 *         (int) 46 => '46 ％',
 *         (int) 47 => '47 ％',
 *         (int) 48 => '48 ％',
 *         (int) 49 => '49 ％',
 *         (int) 50 => '50 ％'
 *     ),
 *     'itemsize' => array(
 *         '' => '--選択--',
 *         (int) 10 => '170cm〜'
 *     ),
 *     'itemweight' => array(
 *         '' => '--選択--',
 *         (int) 8 => '30kg〜'
 *     )
 *     'oldAID' => '',
 *     'mode' => '',
 *     'denomination' => '',
 *     'cc' => '',
 *     'md5' => '',
 *     'loginChk' => '',
 *     '.crumb' => '',
 *     'tos' => '',
 *     'type' => '',
 *     'thumbnailwidth' => '',
 *     'thumbnailid' => '',
 *     'ImageServer' => '',
 *     'Image1' => '',
 *     'Image2' => '',
 *     'Image3' => '',
 *     'ImageViewServer1' => '',
 *     'ImageViewServer2' => '',
 *     'ImageViewServer3' => '',
 *     'ImageWidth1' => '',
 *     'ImageWidth2' => '',
 *     'ImageWidth3' => '',
 *     'ImageHeight1' => '',
 *     'ImageHeight2' => '',
 *     'ImageHeight3' => '',
 *     'Image1Uploaded' => '',
 *     'Image2Uploaded' => '',
 *     'Image3Uploaded' => '',
 *     'auction_server' => '',
 *     'uploadserver' => '',
 *     'ypoint' => '',
 *     'ImageFullPath1' => '',
 *     'ImageFullPath2' => '',
 *     'ImageFullPath3' => '',
 *     'submit_description' => '',
 *     'UEDescription' => '',
 *     'Description_rte' => '',
 *     'Description_rte_work' => '',
 *     'Offer' => '',
 *     'submitUnixtime' => '',
 *     'tmpClosingDate' => '',
 *     'tmpClosingTime' => '',
 *     'doStorePayment' => '',
 *     'ypmOK' => '',
 *     'aspj1' => '',
 *     'abkn1' => '',
 *     'apm1' => '',
 *     'sri' => '',
 *     'shipfee1' => '',
 *     'shipfee2' => '',
 *     'shipfee3' => '',
 *     'shipfee4' => '',
 *     'shipfee5' => '',
 *     'shipfee6' => '',
 *     'shipfee7' => '',
 *     'shipfee8' => '',
 *     'shipfee9' => '',
 *     'shipfee10' => '',
 * )
 * @param string &$error   error message
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function reExhibit($settings, $url, $option = array(), &$error = '') {
		self::initialize($settings);

		$url = YahooReExhibit::reExhibitUrl($url, $error);
		if (!$url) {
			YahooReExhibit::finalize($settings);
			return false;
		}

		$url = YahooReExhibit::reExhibitEdit($url, $option, $error);
		if (!$url) {
			YahooReExhibit::finalize($settings);
			return false;
		}

		$url = YahooReExhibit::reExhibitPreview($url, $error);
		if (!$url) {
			YahooReExhibit::finalize($settings);
			return false;
		}

		$url = YahooReExhibit::reExhibitSubmit($url, $error);
		if (!$url) {
			YahooReExhibit::finalize($settings);
			return false;
		} else {
			YahooReExhibit::finalize($settings);
			return $url;
		}

		self::finalize($settings);
		return false;
	}

/**
 * cancelAuction method
 *
 * @param array  $settings curl and yahoo settings
 * @param string $url      target item url
 * @param string $error    error
 * @return void
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function cancelAuction($settings, $url, &$error = '') {
		self::initialize($settings);
		$params = array();

		if (!self::isNotSelling($url)) {
			$error .= '終了済み';
			return false;
		}

		$body = self::getBody($url);
		$queryPath = htmlqp($body, '.pts02 li a', self::$_qpOption['utf-8']);
		$url = $queryPath->eq(3)->attr('href');

		$body = self::getBody($url);
		$queryPath = htmlqp($body, 'form', self::$_qpOption['euc-jp']);
		$action = $queryPath->attr('action');
		$inputs = $queryPath->find('input');
		foreach ($inputs as $input) {
			$params[$input->attr('name')]
				= mb_convert_encoding($input->attr('value'), 'EUC-JP', 'UTF-8');
		}

		CurlUtility::setPostFields($params);

		$result = self::isNotSelling($action);
		self::finalize($settings);
		return $result;
	}

/**
 * isNotSelling method
 *
 * @param string $url     target item url
 * @return bool
 * @author Atunori Kamori <atunori.kamori@gmail.com>
 */
	public function isNotSelling($url) {
		$body = self::getBody($url);
		return htmlqp($body, '#modCloseBtn', self::$_qpOption['utf-8']);
	}

}
