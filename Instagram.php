<?php

namespace evsyutkin\instagram;

use Yii;
use InstagramScraper\Instagram as InstagramScraper;

class Instagram extends \yii\base\Widget
{
	// Instagram login
	public $login;

	// Get pictures from WORLDWIDE by hashtags.
	// Separate hashtags by comma. For example: girl, man
	// Use this options only if you want show pictures of other users.
	// Profile avatar and statistic will be hidden.
	public $hashTag = '';

	// Random order of pictures [ true / false ]
	public $imgRandom = true;

	// How many pictures widget will get from Instagram
	public $limit = 30;

	public $useCache = true;

	// Cache expiration time
	public $cacheExpiration = 3600;

	// Widget language
	public $language;

	//Widget width in pixels
	public $width = 260;

	//Count of images at one row
	public $inline = 4;

	//Count of images will be showed
	public $count = 12;

	public $toolbar = true;

	public $imgWidth = 0;

	// Images size  [ small (320x320) / large (640x640) / fullsize ]
	public $preview = 'small';

	private $scraper;

	public function init()
	{
		parent::init();
		$this->registerTranslations();
		$this->scraper = new InstagramScraper();
	}

	public function registerTranslations()
	{
		$i18n = Yii::$app->i18n;
		$i18n->translations['evsyutkin/instagram/*'] = [
			'class' => 'yii\i18n\PhpMessageSource',
			'sourceLanguage' => 'en',
			'basePath' => '@evsyutkin/instagram/messages',
			'fileMap' => [
				'evsyutkin/instagram/messages' => 'widget.php',
			],
		];
	}

	public function run()
	{
		$account = null;
		$images = array();
		$this->width -= 2;

		if ($this->width > 0) {
			$this->imgWidth = round(($this->width - (17 + (9 * $this->inline))) / $this->inline);
		}

		try {
			$account = $this->findAccount($this->login);

			if ($account->isPrivate) {
				throw new \Exception('Requested profile is private');
			}

			// by hash tag
			if (!empty($this->hashTag)) {
				$this->toolbar = false;
				$medias = $this->findMediaByHashTag($this->hashTag, $this->limit);
				$imgEmpty = 'photos by tag <b>#{hashTag}</b> not found';
			}
			// by profile
			else {
				$medias = $this->findMediaByProfile($this->login, $this->limit);
				$imgEmpty = 'user doesn\'t have any photos yet';
			}

			if(!empty($medias)) {
				foreach ($medias as $key => $item) {
					$images[$key]['id'] 			= $item->id;
					$images[$key]['shortcode'] 		= $item->shortcode;
					$images[$key]['created'] 		= $item->createdTime;
					$images[$key]['text'] 			= $item->caption;
					$images[$key]['link'] 			= $item->link;
					$images[$key]['fullsize'] 		= $item->imageHighResolutionUrl;
					$images[$key]['large'] 			= $item->imageStandardResolutionUrl;
					$images[$key]['small'] 			= $item->imageLowResolutionUrl;
					$images[$key]['likesCount'] 	= $item->likesCount;
					$images[$key]['commentsCount'] 	= $item->commentsCount;
					if(!empty($this->hashTag)) {
						$images[$key]['authorId'] = $item->ownerId;
					}
					else {
						$images[$key]['authorId'] = $account->id;
					}
				}
			}

		} catch (\Exception $e) {
			die($e->getMessage());
		}

		if($this->imgRandom === true) {
			shuffle($images);
		}

		return $this->render('instagram', [
				'account' => $account,
				'images' => $images,
				'width' => $this->width,
				'imgWidth' => $this->imgWidth,
				'inline' => $this->inline,
				'toolbar' => $this->toolbar,
				'count' => $this->count,
				'preview' => $this->preview,
				'title' => Instagram::t('messages', 'We\'re on Instagram', array(), $this->language),
				'buttonFollow' => Instagram::t('messages', 'View', array(), $this->language),
				'statPosts' => Instagram::t('messages', 'posts', array(), $this->language),
				'statFollowers' => Instagram::t('messages', 'followers', array(), $this->language),
				'statFollowing' => Instagram::t('messages', 'following', array(), $this->language),
				'imgEmpty' => Instagram::t('messages', $imgEmpty, ['hashTag' => $this->hashTag], $this->language),
			]
		);
	}

	public function findAccount($login)
	{
		$profile = null;
		$cacheKey = static::className() . '_profile_' . $login;

		if ($this->useCache) {
			$profile = Yii::$app->cache->get($cacheKey);
		}

		if (!$profile) {
			$profile = $this->scraper->getAccount($login);

			if ($this->useCache) {
				Yii::$app->cache->set($cacheKey, $profile, $this->cacheExpiration);
			}
		}

		return $profile;
	}

	public function findMediaByHashTag($hashTag, $count)
	{
		$mediaArray = array();
		$cacheKey = static::className() . '_medias_by_hashtag_' . $hashTag . '_count_' . $count;

		if ($this->useCache) {
			$medias = Yii::$app->cache->get($cacheKey);
		}

		if (!$medias) {
			$tags = explode(',', $hashTag);
			if (!empty($tags)) {
				foreach ($tags as $key => $item) {
					$item = strtolower(trim($item));
					if (!empty($item)) {
						$mediaArray[] = $this->scraper->getMediasByTag($item, $count);
					}
				}
			}

			$medias = new \ArrayObject();
			if (!empty($mediaArray)) {
				foreach ($mediaArray as $key => $item) {
					$medias = (object)array_merge((array)$medias, (array)$item);
				}
			}

			if ($this->useCache) {
				Yii::$app->cache->set($cacheKey, $medias, $this->cacheExpiration);
			}
		}


		return $medias;
	}

	public function findMediaByProfile($login, $count)
	{
		$medias = null;
		$cacheKey = static::className() . '_medias_by_profile_' . $login . '_count_' . $count;

		if ($this->useCache) {
			$medias = Yii::$app->cache->get($cacheKey);
		}

		if (!$medias) {
			$medias = $this->scraper->getMedias($login, $count);

			if ($this->useCache) {
				Yii::$app->cache->set($cacheKey, $medias, $this->cacheExpiration);
			}
		}

		return $medias;
	}

	public static function t($category, $message, $params = [], $language = null)
	{
		return Yii::t('evsyutkin/instagram/' . $category, $message, $params, $language);
	}
}