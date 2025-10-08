<?php
require_once __DIR__ . "/vendor/autoload.php";

use Graby\Graby;

class Af_ReadabilityExtension extends Minz_Extension
{
	/** @var array<int,FreshRSS_Feed> */
	private array $feeds;
	/** @var array<int,FreshRSS_Category> */
	private array $categories;
	/** @var array<int,bool> */
	private array $configFeeds = [];
	/** @var array<int,bool> */
	private array $configCategories = [];

	public function init()
	{
		$this->registerHook('entry_before_insert', array($this, 'processArticle'));
		Minz_View::appendStyle($this->getFileUrl('style.css'));
	}

	/**
	 * @throws Minz_PermissionDeniedException
	 */
	public function processArticle(FreshRSS_Entry $article): FreshRSS_Entry
	{
		$this->loadConfigValues();
		$feedId = $article->feedId();

		$categoryId = $article->feed()?->category()?->id();

		if (!array_key_exists($feedId, $this->configFeeds)
			&& (null === $categoryId || !array_key_exists($categoryId, $this->configCategories))
		) {
			return $article;
		}

		$extractedContent = $this->extractContent($article->link());

		$contentTest = is_string($extractedContent) ? trim(strip_tags($extractedContent)) : null;

		if (!empty($contentTest)) {
			$article->_content((string)$extractedContent);
		}

		return $article;
	}

	/** @return array<int,FreshRSS_Feed> */
	public function getFeeds(): array
	{
		return $this->feeds;
	}

	/** @return array<int,FreshRSS_Category> */
	public function getCategories(): array
	{
		return $this->categories;
	}

	/**
	 * @throws Minz_PermissionDeniedException
	*/
	private function loadConfigValues(): void
	{
		if (!class_exists('FreshRSS_Context', false)) {
			echo "Failed data";
			return;
		}
		try {
			$userConf = FreshRSS_Context::userConf();
		}
		catch(\Throwable $t) {
			Minz_Log::warning('af-readability: ' . $t->getMessage());
			return;
		}

		$this->configFeeds = $this->readConfigValue($userConf, 'ext_af_readability_feeds');
		$this->configCategories = $this->readConfigValue($userConf, 'ext_af_readability_categories');
	}

	/** @return array<int,bool> */
	private function readConfigValue(FreshRSS_UserConfiguration $userConf, string $configKey): array
	{
		if('' === $configKey) {
			return [];
		}
		$value = $userConf->attributeString($configKey);
		if ($value == '') {
			return [];
		}

		$decoded = (array)json_decode($value, true);
		$result = [];
		foreach($decoded as $key => $param) {
			$result[(int)$key] = (bool) $param;
		}

		return $result;
	}

	public function getConfigFeeds(int $id): bool
	{
		return array_key_exists($id, $this->configFeeds);
	}

	public function getConfigCategories(int $id): bool
	{
		return array_key_exists($id, $this->configCategories);
	}

	/**
	 * @throws FreshRSS_Context_Exception
	 * @throws Minz_ConfigurationNamespaceException
	 * @throws Minz_PDOConnectionException
	 * @throws Minz_PermissionDeniedException
	 */
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

	/**
	 * @throws Minz_PermissionDeniedException
	 */
	private function extractContent(string $url): bool|string|null
	{
		if(empty($url)) {
			return false;
		}

        try {
            $graby = new Graby();
            $result = $graby->fetchContent($url);
        }
		catch(\Throwable $t) {
			Minz_Log::warning('af-readability: ' . $t->getMessage());
			return false;
		}

        return $result->getHtml();
	}
}
