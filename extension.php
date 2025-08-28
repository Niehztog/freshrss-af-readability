<?php
require_once __DIR__ . "/vendor/autoload.php";

use \fivefilters\Readability\Readability;
use \fivefilters\Readability\Configuration;

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
	private bool $configLoaded = false;

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
		if ($this->configLoaded) {
			return;
		}
		if (!class_exists('FreshRSS_Context', false)) {
			Minz_Log::warning('af-readability: FreshRSS_Context class not found');
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
		$this->configLoaded = true;
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

		$ch = curl_init();
		if(false === $ch) {
			return false;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, FRESHRSS_USERAGENT);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: text/*',
			'Content-Type: text/html'
		]);
		curl_setopt($ch, CURLOPT_ACCEPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_MAXFILESIZE, 1024 * 1024 * 2);
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			return false;
		}
		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
		$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);

		if (!is_string($response)) {
			return false;
		}

		$response = $this->ensureUtf8($response, is_string($contentType) ? $contentType : '');

		try {
			$r = new Readability((new Configuration([
				'FixRelativeURLs' => true,
				'OriginalURL' => $url,
				'ExtraIgnoredElements' => ['template'],
			]))->setLogger(new LoggerBridge()));

			if ($r->parse($response)) {
				$content = $r->getContent();
				if (!is_string($content)) {
					return $content;
				}
				// Emit non-ASCII characters as HTML numeric entities so the stored
				// markup is pure ASCII. Readability returns correct UTF-8, but further
				// down the FreshRSS pipeline raw UTF-8 bytes were being re-interpreted
				// as Latin-1 and re-encoded, turning punctuation like ’ and … into
				// "â" followed by invisible control characters (issue #11). Pure-ASCII
				// entities are immune to that and decode correctly in every reader.
				return mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
			}
		}
		catch(\Throwable $t) {
			Minz_Log::warning('af-readability: ' . $t->getMessage());
			return false;
		}

		return false;
	}

	/**
	 * Normalise fetched markup to UTF-8. Readability's HTML5 parser handles UTF-8
	 * itself (with or without a meta charset) but drops bytes from legacy encodings,
	 * so we only convert when the page explicitly declares a non-UTF-8 charset.
	 * UTF-8/ASCII is passed through untouched to avoid the UTF-8 -> Latin-1 -> UTF-8
	 * double-encoding that corrupted punctuation (issue #11).
	 */
	private function ensureUtf8(string $response, string $contentType): string
	{
		$charset = null;
		if (preg_match('/charset\s*=\s*["\']?([\w\-]+)/i', $contentType, $m)) {
			$charset = strtolower($m[1]);
		}
		if ($charset === null
			&& preg_match('/<meta[^>]+charset\s*=\s*["\']?([\w\-]+)/i', $response, $m)) {
			$charset = strtolower($m[1]);
		}
		if ($charset === null
			|| in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii'], true)) {
			return $response;
		}
		if (!in_array($charset, array_map('strtolower', mb_list_encodings()), true)) {
			return $response; // unknown charset: leave to Readability rather than risk corruption
		}
		$converted = mb_convert_encoding($response, 'UTF-8', $charset);
		if (!is_string($converted) || $converted === '') {
			return $response;
		}
		// Strip the now-stale meta charset so the HTML5 parser doesn't re-interpret
		// the already-converted UTF-8 bytes with the old encoding.
		$stripped = preg_replace('/<meta[^>]*charset[^>]*>/i', '', $converted);
		return is_string($stripped) ? $stripped : $converted;
	}
}

class LoggerBridge implements \Psr\Log\LoggerInterface {

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_EMERG, null);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_ALERT, null);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_CRIT, null);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_ERR, null);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_WARNING, null);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_WARNING/*LOG_NOTICE*/, null);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_WARNING/*LOG_INFO*/, null);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, LOG_WARNING/*LOG_DEBUG*/, null);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        Minz_Log::record($message, $level, null);
    }
}