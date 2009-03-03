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
						 'version' => '0.1.1',
						 'release-date' => '2009-03-03',
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
		}

    /*-------------------------------------------------------------------------
    	Caching
    -------------------------------------------------------------------------*/
    
    public function intercept_page(&$page)
    {
      $frontend = Frontend::instance();
      $logged_in = $frontend->isLoggedIn();
            
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
        print $output;
        if ($this->_get_comment_pref() == 'yes') echo "<!-- Cache served: ". $cl->_fileName  ." -->";
        exit();
      }
    }
    
    public function write_page_cache(&$output)
    {
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
        if ($this->_get_comment_pref() == 'yes') echo "<!-- Cache generated: ". $cl->_fileName  ." -->";
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
		
  }