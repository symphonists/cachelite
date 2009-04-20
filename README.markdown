# CacheLite
 
Version: 0.1.2
Author: [Max Wheeler](http://makenosound.com)
Build Date: 20 April 2009
Requirements: Symphony 2.0.1


## Installation
 
1. Upload the 'cachelite' folder in this archive to your Symphony 'extensions'
 folder.
 
2. Enable it by selecting the "CacheLite", choose Enable from the
  with-selected menu, then click Apply.
 
3. Go to "System" -> "Preferences" and enter the cache period (in seconds).

4. Profit.


## Usage

Caching is done on a per-page basis. To flush the cache for an individual page
simply append ?flush to the end of its URL. To flush the cache for the entire
site you just need to append ?flush=site to any URL in the site.

You can exclude URLs from the cache by adding them to the list of excluded pages
in "System" -> "Preferences". Each URL must sit on a separate line and wildcards
(*) may be used at the end of URLs to match *everything* below that URL.