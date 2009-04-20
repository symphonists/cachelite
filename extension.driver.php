<?php

	require_once('lib/class.cachelite.php');
	require_once(CORE . '/class.frontend.php');
	
	Class extension_cachelite extends Extension
	{	
				
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		public function about()
		{
			return array('name' => 'CacheLite',
						 'version' => '0.1.2',
						 'release-date' => '2009-04-20',
						 'author' => array('name' => 'Max Wheeler',
											 'website' => 'http://makenosound.com/',
											 'email' => 'max@makenosound.com'),
 						 'description' => 'Allows for simple frontend caching using the CacheLite library.'
				 		);
		}		
		
		public function uninstall()
		{
			# Remove preferences
			$this->_Parent->Configuration->remove('cachelite');
			$this->_Parent->saveConfig();
			
			# Remove file
			if(file_exists(MANIFEST . '/cachelite-excluded-pages')) unlink(MANIFEST . '/cachelite-excluded-pages');
		}
		
		public function install()
		{
			return true;
		}

		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPreGenerate',
					'callback'	=> 'intercept_page'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPostGenerate',
					'callback'	=> 'write_page_cache'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'append_preferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'save_preferences'
				),
			);
		}

		/*-------------------------------------------------------------------------
			Preferences
		-------------------------------------------------------------------------*/

		public function append_preferences($context)
		{	
			# Add new fieldset
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'CacheLite'));

			# Add Site Reference field
			$label = Widget::Label('Cache Period');
			$label->appendChild(Widget::Input('settings[cachelite][lifetime]', General::Sanitize($this->_get_lifetime())));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Length of cache period in seconds.', array('class' => 'help')));
			
			$label = Widget::Label('Excluded URLs');
			$label->appendChild(Widget::Textarea('cachelite[excluded-pages]', 10, 50, $this->_get_excluded_pages()));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Add a line for each URL you want to be excluded from the cache. Add a <code>*</code> to the end of the URL for wildcard matches.', array('class' => 'help')));
			
			$label = Widget::Label();
			$input = Widget::Input('settings[cachelite][show-comments]', 'yes', 'checkbox');
			if($this->_Parent->Configuration->get('show-comments', 'cachelite') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Show comments in page source?');
			$group->appendChild($label);
			$context['wrapper']->appendChild($group);
		}
		
		public function save_preferences($context)
		{
			if(!isset($context['settings']['cachelite']['show-comments'])){
				$context['settings']['cachelite']['show-comments'] = 'no';
			}
			$this->_save_excluded_pages(stripslashes($_POST['cachelite']['excluded-pages']));
		}

		/*-------------------------------------------------------------------------
			Caching
		-------------------------------------------------------------------------*/
		
		public function intercept_page(&$page)
		{
			if($this->_in_excluded_pages()) return;
			$frontend = Frontend::instance();
			$logged_in = $frontend->isLoggedIn();
			
			$headers = $page['page']->_headers;
						
			$url = getCurrentPage();
			$options = array(
					'cacheDir' => CACHE . "/",
					'lifeTime' => $this->_get_lifetime()
			);
			$cl = new Cache_Lite($options);
			
			if ($page['page']->_param['url-flush'] == 'site')
			{
				$cl->clean();
			}
			else if (array_key_exists('url-flush', $page['page']->_param))
			{
				$cl->remove($url);
			}
			else if ( ! $logged_in && $output = $cl->get($url))
			{
				# Ensure the original headers are served out
				foreach ($headers as $header) {
					header($header);
				}
				print $output;
				if ($this->_get_comment_pref() == 'yes') echo "<!-- Cache served: ". $cl->_fileName	." -->";
				exit();
			}
		}
		
		public function write_page_cache(&$output)
		{				
			if($this->_in_excluded_pages()) return;
			$frontend = Frontend::instance();
			$logged_in = $frontend->isLoggedIn();
			if ( ! $logged_in)
			{
				$render = $output['output'];
				$url = getCurrentPage();
				$options = array(
						'cacheDir' => CACHE . "/",
						'lifeTime' => $this->_get_lifetime()
				);
				$cl = new Cache_Lite($options);
				if ( ! $cl->get($url)) {
					$cl->save($render);
				}
				header(sprintf("Content-Length: %d", strlen($render)));
				print $render;
				if ($this->_get_comment_pref() == 'yes') echo "<!-- Cache generated: ". $cl->_fileName	." -->";
				exit();
			}
		}
		
		/*-------------------------------------------------------------------------
			Helpers
		-------------------------------------------------------------------------*/

		private function _get_lifetime()
		{
			$default_lifetime = 86400;
			$val = $this->_Parent->Configuration->get('lifetime', 'cachelite');
			return (isset($val)) ? $val : $default_lifetime;
		}
		
		private function _get_comment_pref()
		{
			return $this->_Parent->Configuration->get('show-comments', 'cachelite');
		}
		private function _get_excluded_pages()
		{
			return @file_get_contents(MANIFEST . '/cachelite-excluded-pages');
		}
		
		private function _save_excluded_pages($string){
			return @file_put_contents(MANIFEST . '/cachelite-excluded-pages', $string);
		}
		
		private function _in_excluded_pages()
		{
			$segments = explode("/",$_SERVER['REQUEST_URI']);
			foreach($segments as $key => $segment)
			{
				if($segment == DOMAIN OR empty($segment)) unset($segments[$key]);
			}
			$path = "/" . implode("/", $segments);
			
			$rules = file(MANIFEST . '/cachelite-excluded-pages', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$ignored = FALSE;
			$rules = array_map('trim', $rules);
			if(count($rules) > 0)
			{
				foreach($rules as $r)
				{
					$r = str_replace('http://', NULL, $r);
					$r = str_replace(DOMAIN . '/', NULL, $r);
					if (substr($r, -1) != '/') $r = "/" . $r; # Make sure we're matching `/url/blah` not `url/blah
					if($r == '*')
					{
						$ignored = TRUE;
						break;
					}
					elseif(substr($r, -1) == '*' && strncasecmp($path, $r, strlen($r) - 1) == 0)
					{
						$ignored = TRUE;
						break;
					}
					elseif(strcasecmp($r, $path) == 0)
					{
						$ignored = TRUE;
						break;				
					}
				}
			}
			return $ignored;
		}
	}