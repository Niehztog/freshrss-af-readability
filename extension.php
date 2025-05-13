<?php
require_once __DIR__ . "/vendor/autoload.php";

use \fivefilters\Readability\Readability;
use \fivefilters\Readability\Configuration;

class Af_ReadabilityExtension extends Minz_Extension {

	private array $feeds;
	private array $categories;
	private array $configFeeds = [];
	private array $configCategories = [];

	public function init() {

		$this->registerHook('entry_before_insert', array($this, 'processArticle'));
		Minz_View::appendStyle($this->getFileUrl('style.css'));
	}

	public function processArticle($article) {

		$this->loadConfigValues();
		if (empty($article->toArray()['id_feed'])){
			$feedId = $article->feed(false);
		} else {
			$feedId = $article->toArray()['id_feed'];
		}

		$categoryId = $article->feed()->category()->id();

		if (!array_key_exists($feedId, $this->configFeeds) && !array_key_exists($categoryId, $this->configCategories) ) {
			return $article;
		}

		$extractedContent = $this->extractContent($article->link());

		$contentTest = trim(strip_tags($extractedContent));

		if (!empty($contentTest)) {
			$article->_content($extractedContent);
		}

		return $article;
	}

	public function getFeeds() {
		return $this->feeds;
	}

	public function getCategories() {
		return $this->categories;
	}

	public function loadConfigValues() {
		if (!class_exists('FreshRSS_Context', false)) {
			echo "Failed data";
			return;
		}
		try {
			$userConf = FreshRSS_Context::userConf();
		}
		catch(\Throwable $t) {
			echo "Failed data";
			return;
		}

		if ($userConf->attributeString('ext_af_readability_feeds') != '') {
			$this->configFeeds = (array)json_decode($userConf->attributeString('ext_af_readability_feeds'), true);
		} else {
			$this->configFeeds = [];
		}
		if ($userConf->attributeString('ext_af_readability_categories') != '') {
			$this->configCategories = (array)json_decode($userConf->attributeString('ext_af_readability_categories'), true);
		} else {
			$this->configCategories = [];
		}
	}

	public function getConfigFeeds($id) {
		return array_key_exists($id, $this->configFeeds);
	}

	public function getConfigCategories($id) {
		return array_key_exists($id, $this->configCategories);
	}

	public function handleConfigureAction()
	{
		$feedDAO = FreshRSS_Factory::createFeedDao();
		$catDAO = FreshRSS_Factory::createCategoryDao();
		$this->feeds = $feedDAO->listFeeds();
		$this->categories = $catDAO->listCategories(true,false);

		if (Minz_Request::isPost()) {
			$configFeeds = [];
			foreach ($this->feeds as $f) {
				if (Minz_Request::paramBoolean("feed_".$f->id())){
					$configFeeds[$f->id()] = true;
				}
			}

			$configCategories = [];
			foreach ($this->categories as $c) {
				if (Minz_Request::paramBoolean("cat_".$c->id())){
					$configCategories[$c->id()] = true;
				}
			}

			FreshRSS_Context::userConf()->_attribute('ext_af_readability_feeds', (string)json_encode($configFeeds));
			FreshRSS_Context::userConf()->_attribute('ext_af_readability_categories', (string)json_encode($configCategories));

			FreshRSS_Context::userConf()->save();
		}

		$this->loadConfigValues();
	}

	public function extractContent(string $url): bool|string|null {
		$ch = curl_init();
		if(false === $ch) {
			return false;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: text/*',
			'Content-Type: text/html'
		]);
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			return false;
		}
		$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
		if (!empty($redirectUrl)) {
			$url = $redirectUrl;
		}
		curl_close($ch);

		if ($response && mb_strlen($response) < 1024 * 500) {
			$document = new DOMDocument("1.0", "UTF-8");

			libxml_use_internal_errors(true);
			if (!$document->loadHTML('<?xml encoding="UTF-8">' . $response)) {
				libxml_clear_errors();
				return false;
			}
			libxml_clear_errors();

			if (strtolower($document->encoding) != 'utf-8') {
				$response = preg_replace("/<meta.*?charset.*?\/?>/i", "", $response);
				if (empty($document->encoding)) {
					$response = mb_convert_encoding($response, 'utf-8');
				} else {
					$response = mb_convert_encoding($response, 'utf-8', $document->encoding);
				}
			}

			try {
				$r = new Readability(new Configuration([
					'FixRelativeURLs'      => true,
					'OriginalURL'          => $url,
					'ExtraIgnoredElements' => ['template'],
				]));

				if ($r->parse($response)) {
					return $r->getContent();
				}

			} catch (Exception $e) {
				return false;
			}
		}

		return false;
	}
}
