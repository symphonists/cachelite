# CacheLite
 
Version: 1.0.0
Author: [Max Wheeler](http://makenosound.com)  
Build Date: 05 August 2009  
Requirements: Symphony 2.0.6+


## Installation
 
1. Upload the 'cachelite' folder in this archive to your Symphony 'extensions'
 folder.
 
2. Enable it by selecting the "CacheLite", choose Enable from the
  with-selected menu, then click Apply.
 
3. Go to "System" -> "Preferences" and enter the cache period (in seconds).

4. The output of your site will now be cached.


## Usage

Caching is done on a per-URL basis. To flush the cache for an individual page
simply append `?flush` to the end of its URL. To flush the cache for the entire
site you just need to append `?flush=site` to any URL in the site. You *must* be
logged in to flush the cache.

You can exclude URLs from the cache by adding them to the list of excluded pages
in "System" - "Preferences". Each URL must sit on a separate line and wildcards
(\*) may be used at the end of URLs to match *everything* below that URL.

Excluded pages are assumed to originate from the root. All the following
examples will resolve to the same page (providing there are none below it in 
the hierarchy):

	/about-us/get-in-touch/*
	http://root.com/about-us/get-in-touch/
	about-us/get-in-touch*
	/about-us/get*

Note that caching is *not* done for logged in users. This lets you add administrative 
tools to the frontend of your site without them being cached for normal users.

## Compatibility ##

Due to changes in the Symphony core, version 1.0.0+ of the CacheLite extension 
only works with Symphony 2.0.6+. Versions prior to 1.0.0 are compatible with 
Symphony 2.0.1-2.0.3. If you're using 2.0.4-5 then you should upgrade :p
