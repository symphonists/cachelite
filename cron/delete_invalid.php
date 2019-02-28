<?php

define('DOCROOT', str_replace('/extensions/cachelite/cron', '', rtrim(dirname(__FILE__), '\\/') ));

if (file_exists(DOCROOT . '/vendor/autoload.php')) {
	require_once(DOCROOT . '/vendor/autoload.php');
	require_once(DOCROOT . '/symphony/lib/boot/bundle.php');
}
else {
	require_once(DOCROOT . '/symphony/lib/boot/bundle.php');
	require_once(DOCROOT . '/symphony/lib/core/class.cacheable.php');
	require_once(DOCROOT . '/symphony/lib/core/class.symphony.php');
	require_once(DOCROOT . '/symphony/lib/core/class.administration.php');
	require_once(DOCROOT . '/symphony/lib/toolkit/class.general.php');
}

// creates the DB
Administration::instance();

require_once(DOCROOT . '/extensions/cachelite/extension.driver.php');

$ext = new Extension_cachelite;

while (true) {
	$next = Symphony::Database()
		->select(['*'])
		->from('tbl_cachelite_invalid')
		->limit(1)
		->execute()
		->rows();

	if (empty($next)) {
		break;
	}
	$next = current($next);
	if (empty($next)) {
		break;
	}
	$pages = [];
	$section_id = $next['section_id'];
	$entry_id = $next['entry_id'];
	echo "Fetching pages from section $section_id and entry $entry_id" . PHP_EOL;
	if ($section_id) {
		$pages = $ext->getPagesByContent($next['section_id'], 'section');
	} elseif ($entry_id) {
		$pages = $ext->getPagesByContent($next['entry_id'], 'entry');
	}
	echo 'Found ' . count($pages). ' pages.' . PHP_EOL;
	foreach($pages as $page) {
		echo 'Removing ' . substr($page['page'], 0, 64);
		$url = $page['page'];
		$ext->removeByUrl($page['page']);
		sleep(1);
		echo '. Done.' . PHP_EOL;
	}

	Symphony::Database()
		->delete('tbl_cachelite_invalid')
		->where(['section_id' => $section_id])
		->where(['entry_id' => $entry_id])
		->execute()
		->success();

	sleep(1);
}

$ext->optimizePageTable();
