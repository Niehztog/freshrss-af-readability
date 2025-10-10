<?php
require_once __DIR__ . "/vendor/autoload.php";

use Graby\Content;
use Graby\Graby;
use \Masterminds\HTML5;

class Af_ReadabilityExtension extends Minz_Extension
{
	/**
	 * Elements that must never survive into stored article content.
	 *
	 * Readability returns the article subtree as-is, and any document-level element that the
	 * scraped page placed inside the body is kept. Because entry_before_insert replaces the
	 * content after FreshRSS has already sanitized the feed, these tags reach the reading view
	 * verbatim. A single <base href> is enough to re-root every relative URL in the FreshRSS UI.
	 *
	 * Embeds (iframe/object/embed) are deliberately absent: Readability already removes them
	 * except for whitelisted video providers, which users expect to keep.
	 */
	private const DISALLOWED_ELEMENTS = [
		// Document-level: hijack the host page's URL resolution, redirect it, restyle it or
		// overwrite its title.
		'base', 'meta', 'link', 'title', 'style',
		// Active content. Readability strips these already; repeated here so the guarantee does
		// not depend on its heuristics.
		'script', 'noscript', 'applet',
		// Framesets replace the document entirely.
		'frame', 'frameset',
		// Inert holders whose contents are never article text.
		'template', 'basefont',
	];

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

		if ($extractedContent instanceof Content) {
			$article->_content($extractedContent->getHtml());
            $image = $extractedContent->getImage();

            $enclosures = $article->attributeArray('enclosures');
            if(empty($enclosures) && !empty($image)) {
                $ext = strtolower(pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION));
                if(isset(\FreshRSS_extension_Controller::MIME_TYPES[$ext])) {
                    $article->_attribute('enclosures', [
                        [
                            'url' => $extractedContent->getImage(),
                            'medium' => 'image',
                            'type' => \FreshRSS_extension_Controller::MIME_TYPES[$ext],
                        ]
                    ]);
                }
            }
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
	private function extractContent(string $url): bool|Content
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

        return $result;
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

	/**
	 * Removes document-level and active elements from extracted content.
	 *
	 * Operates on the parsed DOM rather than the serialized string: the HTML5 serializer does
	 * not escape '>' inside attribute values, so a regex that chops at the first '>' can turn an
	 * inert attribute value such as <base x="><meta http-equiv=refresh ...">  into a live
	 * element. Parsing keeps attribute values as attribute values.
	 *
	 * FreshRSS sanitizes its own scraped content with FreshRSS_SimplePieCustom::sanitizeHTML(),
	 * which was rejected here because SimplePie's default strip_htmltags list also removes
	 * iframe/object/embed and would drop the video embeds Readability deliberately keeps.
	 *
	 * @return false|string false when the content cannot be sanitized, so that the caller keeps
	 *                      the original feed content instead of storing unsanitized markup.
	 */
	private function sanitizeContent(string $html): bool|string
	{
		if ('' === trim($html)) {
			return false;
		}

		try {
			$document = new DOMDocument('1.0', 'UTF-8');
			$html5 = new HTML5(['disable_html_ns' => true, 'target_document' => $document]);
			$fragment = $html5->loadHTMLFragment($html);
			if (!$fragment instanceof DOMDocumentFragment) {
				return false;
			}

			// A DOMDocument holds a single element child, so the fragment is parked in a
			// throwaway wrapper. Only the wrapper's children are serialized back.
			$wrapper = $document->createElement('div');
			$document->appendChild($wrapper);
			$wrapper->appendChild($fragment);

			$xpath = new DOMXPath($document);

			$selector = implode(' or ', array_map(
				static fn(string $tag): string => 'self::' . $tag,
				self::DISALLOWED_ELEMENTS
			));
			$nodes = $xpath->query('.//*[' . $selector . ']', $wrapper);
			if (false !== $nodes) {
				// Snapshot first: the node list is live and shrinks as nodes are removed.
				foreach (iterator_to_array($nodes) as $node) {
					$node->parentNode?->removeChild($node);
				}
			}

			// Inline event handlers would execute in the reading view.
			$attributes = $xpath->query('.//@*[starts-with(translate(name(), "ON", "on"), "on")]', $wrapper);
			if (false !== $attributes) {
				foreach (iterator_to_array($attributes) as $attribute) {
					if ($attribute instanceof DOMAttr) {
						$attribute->ownerElement?->removeAttributeNode($attribute);
					}
				}
			}

			$sanitized = '';
			foreach ($wrapper->childNodes as $child) {
				$sanitized .= $html5->saveHTML($child);
			}
		}
		catch(\Throwable $t) {
			Minz_Log::warning('af-readability: unable to sanitize extracted content: ' . $t->getMessage());
			return false;
		}

		return '' === trim($sanitized) ? false : $sanitized;
	}
}
