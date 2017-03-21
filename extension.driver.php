<?php

	class Extension_cachelite extends Extension
	{
		protected $_cacheLite = null;
		protected $_lifetime = null;
		protected $_url = null;
		protected $_get = null;
		private $_sections = array();
		private $_entries = array();
		private $_pagedata = array();
		const CACHE_GROUP = 'cachelite';

		public function __construct()
		{
			require_once('lib/class.cachelite.php');
			$this->_lifetime = $this->getLifetime();
			$this->_cacheLite = new Cache_Lite(array(
				'cacheDir' => CACHE . '/',
				'lifeTime' => $this->_lifetime
			));
			$this->updateFromGetValues();
		}

		/*-------------------------------------------------------------------------
			Extension
		-------------------------------------------------------------------------*/

		public function uninstall()
		{
			// Remove preferences
			Symphony::Configuration()->remove('cachelite');
			Symphony::Configuration()->write();

			// Remove file
			if (@file_exists(MANIFEST . '/cachelite-excluded-pages')) {
				@unlink(MANIFEST . '/cachelite-excluded-pages');
			}

			// Remove extension table
			return $this->dropPageTable();
		}

		public function install()
		{
			// Create extension table
			$this->createPageTable();

			if (!@file_exists(MANIFEST . '/cachelite-excluded-pages')) {
				@touch(MANIFEST . '/cachelite-excluded-pages');
			}

			// Base configuration
			Symphony::Configuration()->set('lifetime', '86400', 'cachelite');
			Symphony::Configuration()->set('show-comments', 'no', 'cachelite');
			Symphony::Configuration()->set('backend-delegates', 'no', 'cachelite');

			return Symphony::Configuration()->write();
		}

		public function update($previousVersion = false)
		{
			if (version_compare($previousVersion, '2.0.0', '<')) {
				$this->dropPageTable();
				$this->createPageTable();
			}
			return true;
		}

		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPageResolved',
					'callback'	=> 'interceptPage'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPreGenerate',
					'callback'	=> 'parsePageData'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPostGenerate',
					'callback'	=> 'writePageCache'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/success/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				),
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'entryCreate'
				),
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPreEdit',
					'callback'	=> 'entryEdit'
				),
				array(
					'page'		=> '/publish/',
					'delegate'	=> 'EntryPreDelete',
					'callback'	=> 'entryDelete'
				),
				array(
					'page' => '/blueprints/events/new/',
					'delegate' => 'AppendEventFilter',
					'callback' => 'addFilterToEventEditor'
				),
				array(
					'page' => '/blueprints/events/edit/',
					'delegate' => 'AppendEventFilter',
					'callback' => 'addFilterToEventEditor'
				),
				array(
					'page' => '/blueprints/events/new/',
					'delegate' => 'AppendEventFilterDocumentation',
					'callback' => 'addFilterDocumentationToEvent'
				),
				array(
					'page' => '/blueprints/events/edit/',
					'delegate' => 'AppendEventFilterDocumentation',
					'callback' => 'addFilterDocumentationToEvent'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'processEventData'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'processPostSaveData'
				),
			);
		}

		public function extensionCacheBypass()
		{
			$cachebypass = false;
			/**
			 * Allows extensions to make this request
			 * bypass the cache.
			 *
			 * @delegate CacheliteBypass
			 * @since 2.0.0
			 * @param string $context
			 *  '/frontend/'
			 * @param bool $bypass
			 *  A flag to tell if the user is logged in and cache must be disabled
			 */
			Symphony::ExtensionManager()->notifyMembers('CacheliteBypass', '/frontend/', array(
				'bypass' => &$cachebypass,
			));
			return $cachebypass;
		}

		/*-------------------------------------------------------------------------
			Preferences
		-------------------------------------------------------------------------*/

		public function appendPreferences($context)
		{
			// Add new fieldset
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'CacheLite'));

			// Add Site Reference field
			$label = Widget::Label(__('Cache Period'));
			$label->appendChild(Widget::Input('settings[cachelite][lifetime]', General::Sanitize($this->getLifetime())));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Length of cache period in seconds.'), array('class' => 'help')));

			$label = Widget::Label(__('Excluded URLs'));
			$label->appendChild(Widget::Textarea('cachelite[excluded-pages]', 10, 50, $this->getExcludedPages()));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Add a line for each URL you want to be excluded from the cache. Add a <code>*</code> to the end of the URL for wildcard matches.'), array('class' => 'help')));

			$label = Widget::Label();
			$label->setAttribute('for', 'cachelite-show-comments');
			$hidden = Widget::Input('settings[cachelite][show-comments]', 'no', 'hidden');
			$input = Widget::Input('settings[cachelite][show-comments]', 'yes', 'checkbox');
			$input->setAttribute('id', 'cachelite-show-comments');
			if (Symphony::Configuration()->get('show-comments', 'cachelite') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Show comments in page source?', array($hidden->generate() . $input->generate())));
			$group->appendChild($label);

			$label = Widget::Label();
			$label->setAttribute('for', 'cachelite-backend-delegates');
			$hidden = Widget::Input('settings[cachelite][backend-delegates]', 'no', 'hidden');
			$input = Widget::Input('settings[cachelite][backend-delegates]', 'yes', 'checkbox');
			$input->setAttribute('id', 'cachelite-backend-delegates');
			if (Symphony::Configuration()->get('backend-delegates', 'cachelite') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue( __('%s Expire cache when entries are created/updated through the backend?', array($hidden->generate() . $input->generate())));
			$group->appendChild($label);
			$context['wrapper']->appendChild($group);
		}

		public function savePreferences($context)
		{
			$this->saveExcludedPages(stripslashes($_POST['cachelite']['excluded-pages']));
		}

		/*-------------------------------------------------------------------------
			Events
		-------------------------------------------------------------------------*/

		public function addFilterToEventEditor($context)
		{
			// adds filters to Filters select box on Event editor page
			$context['options'][] = array('cachelite-entry', @in_array('cachelite-entry', $context['selected']) , 'CacheLite: ' . __('Expire cache for pages showing this entry'));
			$context['options'][] = array('cachelite-section', @in_array('cachelite-section', $context['selected']) , 'CacheLite: ' . __('Expire cache for pages showing content from this section'));
			$context['options'][] = array('cachelite-url', @in_array('cachelite-url', $context['selected']) , 'CacheLite: ' . __('Expire cache for the passed URL'));
		}

		public function processEventData($context)
		{
			// flush the cache based on entry IDs
			if (in_array('cachelite-entry', $context['event']->eParamFILTERS) && isset($_POST['cachelite']['flush-entry'])) {
				if (is_array($_POST['id'])) {
					foreach($_POST['id'] as $id) {
						$this->clearPagesByReference($id, 'entry');
					}
				} elseif (isset($_POST['id'])) {
					$this->clearPagesByReference($_POST['id'], 'entry');
				}
			}

			// flush cache based on the Section ID of the section this Event accesses
			if (in_array('cachelite-section', $context['event']->eParamFILTERS) && isset($_POST['cachelite']['flush-section'])) {
				$this->clearPagesByReference($context['event']->getSource(), 'section');
			}
		}

		public function processPostSaveData($context)
		{
			// flush the cache based on explicit value
			if (in_array('cachelite-url', $context['event']->eParamFILTERS)) {
				$flush = (empty($_POST['cachelite']['flush-url']))
					? $this->_url
					: $this->computeHash(General::sanitize($_POST['cachelite']['flush-url']));
				$this->_cacheLite->remove($flush, self::CACHE_GROUP, true);
			}
		}

		public function addFilterDocumentationToEvent($context)
		{
			if (in_array('cachelite-entry', $context['selected']) || in_array('cachelite-section', $context['selected'])) $context['documentation'][] = new XMLElement('h3', __('CacheLite: Expiring the cache'));
			if (in_array('cachelite-entry', $context['selected']))
			{
				$context['documentation'][] = new XMLElement('h4', __('Expire cache for pages showing this entry'));
				$context['documentation'][] = new XMLElement('p', __('When editing existing entries (one or many, supports the <em>Allow Multiple</em> option) any pages showing this entry will be flushed. Add the following in your form to trigger this filter:'));
				$code = '<input type="hidden" name="cachelite[flush-entry]" value="yes"/>';
				$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			}
			if (in_array('cachelite-section', $context['selected']))
			{
				$context['documentation'][] = new XMLElement('h4', __('Expire cache for pages showing content from this section'));
				$context['documentation'][] = new XMLElement('p', __('This will flush the cache of pages using any entries from this event&#8217;s section. Since you may want to only run it when creating new entries, this will only run if you pass a specific field in your HTML:'));
				$code = '<input type="hidden" name="cachelite[flush-section]" value="yes"/>';
				$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			}
			if (in_array('cachelite-url', $context['selected']))
			{
				$context['documentation'][] = new XMLElement('h4', __('Expire cache for the passed URL'));
				$context['documentation'][] = new XMLElement('p', __('This will expire the cache of the URL at the value you pass it. For example'));
				$code = '<input type="hidden" name="cachelite[flush-url]" value="/article/123/"/>';
				$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
				$context['documentation'][] = new XMLElement('p', __('Will flush the cache for <code>http://domain.tld/article/123/</code>. If no value is passed it will flush the cache of the current page (i.e., the value of <code>action=""</code> in you form):'));
				$code = '<input type="hidden" name="cachelite[flush-url]"/>';
				$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			}
			return;
		}

		/*-------------------------------------------------------------------------
			Caching
		-------------------------------------------------------------------------*/

		protected function computeEtag()
		{
			$lastModified = $this->_cacheLite->lastModified();
			return md5($lastModified . $this->_url);
		}

		public function writeCacheHeaders($cacheHit = 'MISS')
		{
			// Kill session
			@session_unset();
			@session_destroy();
			header_remove('Set-Cookie');
			// Cache headers
			$lastModified = $this->_cacheLite->lastModified();
			$etag = $this->computeEtag();
			header("Cache-Control: public, max-age=" . $this->_lifetime . ", must-revalidate");
			header("Expires: " . gmdate("D, d M Y H:i:s", $lastModified + $this->_lifetime) . " GMT");
			header("Last-Modified: " . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
			header("ETag: \"$etag\"");
			header("Access-Control-Allow-Origin: " . URL);
			header("X-Frame-Options: SAMEORIGIN");
			header("X-Cache-Status: $cacheHit");
			header_remove('Pragma');
			// Add custom content type
			if (!isset($this->_pagedata['type']) || !is_array($this->_pagedata['type']) || empty($this->_pagedata['type'])) {
				header('Content-Type: text/html; charset=utf-8');
			} else if (@in_array('XML', $this->_pagedata['type']) || @in_array('xml', $this->_pagedata['type'])) {
				header('Content-Type: text/xml; charset=utf-8');
			} else {
				foreach($this->_pagedata['type'] as $type) {
					$content_type = Symphony::Configuration()->get(strtolower($type), 'content-type-mappings');

					if (!is_null($content_type)){
						header("Content-Type: $content_type;");
					}

					if ($type{0} == '.') {
						$FileName = $this->_pagedata['handle'];
						header("Content-Disposition: attachment; filename={$FileName}{$type}");
					}
				}
			}
		}

		public function interceptPage($context)
		{
			$this->_pagedata = $context['page_data'];
			if ($this->inExcludedPages() || !$this->isGetRequest() || $this->isErrorTemplate()) {
				return;
			}

			$logged_in = Symphony::isLoggedIn();
			if ($logged_in && array_key_exists('flush', $this->_get) && $this->_get['flush'] == 'site') {
				unset($this->_get['flush']);
				$this->_cacheLite->clean(self::CACHE_GROUP);
				$this->updateFromGetValues();
			} else if ($logged_in && array_key_exists('flush', $this->_get)) {
				unset($this->_get['flush']);
				$this->updateFromGetValues();
				$this->_cacheLite->remove($this->_url, self::CACHE_GROUP, true);
			} else if (!$logged_in && !$this->extensionCacheBypass()) {
				$this->updateFromGetValues();
				$output = $this->_cacheLite->get($this->_url, self::CACHE_GROUP);

				// no cache entry found
				if (!$output) {
					return;
				}

				// Try to return 304
				if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
					$modified = $this->_cacheLite->lastModified();
					$modified_gmt = gmdate('r', $modified);
					if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $modified_gmt ||
						str_replace('"', NULL, stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == $this->computeEtag()){
						Page::renderStatusCode(Page::HTTP_NOT_MODIFIED);
						exit();
					}
				}

				// Add comment
				if ($this->getCommentPref() === 'yes') {
					$output .= "<!-- Cache hit: ". $this->_cacheLite->_fileName ." -->";
				}

				// Write headers
				$this->writeCacheHeaders('HIT');

				// Send response
				print $output;
				exit();
			}
		}

		public function writePageCache(&$output)
		{
			if ($this->inExcludedPages() || !$this->isGetRequest() || $this->isErrorTemplate()) {
				return;
			}

			$logged_in = Symphony::isLoggedIn();
			if (!$logged_in && !$this->extensionCacheBypass()) {
				$render = $output['output'];

				// rebuild entry/section reference list for this page
				$this->deletePageReferences($this->_url);
				$this->savePageReferences($this->_url, $this->_sections, $this->_entries);

				// Actually write the cache
				$this->_cacheLite->save($render, $this->_url, self::CACHE_GROUP);

				// Add comment
				if ($this->getCommentPref() === 'yes') {
					$render .= "<!-- Cache miss: ". $this->_cacheLite->_fileName ." -->";
				}

				// Write headers
				$this->writeCacheHeaders();

				// Send response
				echo $render;
				exit();
			}
		}

		// Parse any Event or Section elements from the page XML
		public function parsePageData($context)
		{
			if ($this->inExcludedPages() || !$this->isGetRequest() || $this->isErrorTemplate()) {
				return;
			}
			$logged_in = Symphony::isLoggedIn();
			if ($logged_in || $this->extensionCacheBypass()) {
				return;
			}
			try {
				$xml = @DomDocument::loadXML($context['xml']->generate());
				if (!$xml) {
					return;
				}
				$xpath = new DOMXPath($xml);

				$sections_xpath = $xpath->query('//section[@id]');
				$sections = array();
				foreach($sections_xpath as $section) {
					$sections[] = $section->getAttribute('id');
				}

				$entries_xpath = $xpath->query('//entry[@id] | //item[@id]');
				$entries = array();
				foreach($entries_xpath as $entry) {
					$entries[] = $entry->getAttribute('id');
				}

				$this->_sections = array_unique($sections);
				$this->_entries = array_unique($entries);
			} catch (Exception $ex) {
				Symphony::Log()->pushExceptionToLog($ex);
			}
		}

		public function entryCreate($context)
		{
			if (Symphony::Configuration()->get('backend-delegates', 'cachelite') == 'no') return;
			// flush by Section ID
			if (isset($context['section'])) {
				$this->clearPagesByReference($context['section']->get('id'), 'section');
			}
		}

		public function entryEdit($context)
		{
			if (Symphony::Configuration()->get('backend-delegates', 'cachelite') == 'no') return;
			// flush by Entry ID
			if (isset($context['entry'])) {
				$this->clearPagesByReference($context['entry']->get('id'), 'entry');
			}
		}

		public function entryDelete($context)
		{
			if (Symphony::Configuration()->get('backend-delegates', 'cachelite') == 'no') return;
			// flush by Entry ID
			$this->clearPagesByReference($context['entry_id'], 'entry');
		}

		public function clearPagesByReference($id, $type)
		{
			// get a list of pages matching this entry/section ID
			$pages = $this->getPagesByContent($id, $type);
			// flush the cache for each
			foreach($pages as $page) {
				$url = $page['page'];
				$this->_cacheLite->remove($url, self::CACHE_GROUP, true);
				$this->deletePageReferences($url);
			}
		}

		/*-------------------------------------------------------------------------
			Helpers
		-------------------------------------------------------------------------*/

		private function getLifetime()
		{
			$default_lifetime = 86400;
			$val = General::intval(Symphony::Configuration()->get('lifetime', 'cachelite'));
			return $val > -1 ? $val : $default_lifetime;
		}

		private function getCommentPref()
		{
			return Symphony::Configuration()->get('show-comments', 'cachelite');
		}

		private function getExcludedPages()
		{
			return @file_get_contents(MANIFEST . '/cachelite-excluded-pages');
		}

		private function saveExcludedPages($string)
		{
			return @file_put_contents(MANIFEST . '/cachelite-excluded-pages', $string);
		}

		private function inExcludedPages()
		{
			$segments = explode('/', $this->_get['symphony-page']);
			$domain = explode('/', DOMAIN);
			foreach($segments as $key => $segment) {
				if (in_array($segment, $domain) || empty($segment)) {
					unset($segments[$key]);
				}
			}
			$path = "/" . implode("/", $segments);

			$rules = file(MANIFEST . '/cachelite-excluded-pages', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$rules = array_filter(array_map('trim', $rules));
			if (count($rules) > 0) {
				foreach($rules as $r) {
					// Make sure we're matching `url/blah` not `/url/blah
					$r = "/" . trim($r, "/"); 
					//wildcard
					if ($r == '*') {
						return true;
					}
					// wildcard after
					else if (substr($r, -1) == '*' && strncasecmp($path, $r, strlen($r) - 2) == 0) {
						return true;
					}
					// wildcard before
					else if (substr($r, -1) == '*' && strpos($r, $path) !== false) {
						return true;
					}
					// wildcard before and after
					else if (substr($r, -1) == '*' && substr($r, 0) == '*' && strncasecmp($path, $r, strlen($r) - 2) == 0) {
						return true;
					}
					// perfect match
					else if (strcasecmp($r, $path) == 0) {
						return true;
					}
				}
			}
			return false;
		}


		/*-------------------------------------------------------------------------
			Database Helpers
		-------------------------------------------------------------------------*/

		private function getPagesByContent($id, $type)
		{
			try {
				$col = $type == 'entry' ? 'entry_id' : 'section_id';
				$id = General::intval($id);
				return Symphony::Database()->fetch(
					"SELECT DISTINCT `page` FROM `tbl_cachelite_references`
						WHERE `$col` = $id "
				);
			} catch (Exception $ex) {
				Symphony::Log()->pushExceptionToLog($ex);
			}
			return array();
		}

		private function deletePageReferences($url)
		{
			try {
				$url = MySQL::cleanValue($url);
				return Symphony::Database()->query(
					"DELETE FROM `tbl_cachelite_references` WHERE `page` = '$url'"
				);
			} catch (Exception $ex) {
				Symphony::Log()->pushExceptionToLog($ex);
			}
			return false;
		}

		protected function saveReferences($reference, $url, $ids)
		{
			try {
				$now = DateTimeObj::get('Y-m-d H:i:s');
				$values = array();
				foreach ($ids as $id) {
					$id = General::intval($id);
					$values[] = "('$url', $id, '$now')";
				}
				$values = implode(',', $values);
				return Symphony::Database()->query(
					"INSERT INTO `tbl_cachelite_references` (`page`, `$reference`, `timestamp`)
						VALUES $values
						ON DUPLICATE KEY UPDATE `timestamp` = '$now'"
				);
			} catch (Exception $ex) {
				Symphony::Log()->pushExceptionToLog($ex);
			}
			return false;
		}

		protected function savePageReferences($url, array $sections, array $entries)
		{
			$url = MySQL::cleanValue($url);
			// Create sections rows
			$sections = $this->saveReferences('section_id', $url, $sections);
			// Create entries rows
			$entries = $this->saveReferences('entry_id', $url, $entries);
			return $sections && $entries;
		}

		protected function createPageTable()
		{
			// Create extension table
			return Symphony::Database()->query("
				CREATE TABLE `tbl_cachelite_references` (
				  `page` char(128) NOT NULL,
				  `section_id` int(11) NOT NULL default 0,
				  `entry_id` int(11) NOT NULL default 0,
				  `timestamp` datetime NOT NULL /*!50600 default CURRENT_TIMESTAMP */,
				  PRIMARY KEY (`page`, `section_id`, `entry_id`),
				  KEY `page` (`page`),
				  KEY `section_page` (`page`, `section_id`),
				  KEY `entry_page` (`page`, `entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
			");
		}

		protected function dropPageTable()
		{
			return Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_cachelite_references`");
		}

		/*-------------------------------------------------------------------------
			Utilities
		-------------------------------------------------------------------------*/

		private function computeHash($url)
		{
			return hash('sha512', (__SECURE__ ? 'https:' : '').serialize($url));
		}

		private function updateFromGetValues()
		{
			// Cache sorted $_GET;
			$this->_get = array_merge(array(), $_GET);
			ksort($this->_get);
			// hash it to make sure it wont overflow
			$this->_url = $this->computeHash($this->_get);
		}

		private function isGetRequest()
		{
			return $_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'HEAD';
		}

		private function isErrorTemplate()
		{
			$types = $this->_pagedata['type'];
			if (empty($types) || !is_array($types)) {
				return $this->_errorTemplate;
			}
			// Check for custom http status
			$this->_errorTemplate = @in_array('404', $types) || @in_array('403', $types);
			return $this->_errorTemplate;
		}
	}
