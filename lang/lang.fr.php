<?php

	$about = array(
		'name' => 'Français',
		'author' => array(
			'name' => 'Deux Huit Huit',
			'email' => 'open-source (at) deuxhuithuit.com',
			'website' => 'http://www.deuxhuithuit.com/',
		),
		'release-date' => '2018-04-26',
	);
	
	
	/*
	 * EXTENSION: Cache Lite
	 * Localisation strings
	 */

	$dictionary = array(

		'Cache Period' =>
		'Période de cache',

		'Length of cache period in seconds.' =>
		'Longueur de la période de cache en secondes.',

		'Excluded URLs' =>
		'URL exclues',

		'Add a line for each URL you want to be excluded from the cache. Add a <code>*</code> to the end of the URL for wildcard matches.' =>
		'Ajoutez une ligne pour chaque URL que vous souhaitez exclure du cache. Ajoutez un <code> * </ code> à la fin des URLs pour les correspondances génériques. (Wildcard)',

		'%s Show comments in page source?' =>
		'%s Afficher les commentaires dans le code source de la page?',

		'%s Expire cache when entries are created/updated through the backend?' =>
		'%s Expiration du cache lorsque les entrées sont créées / mises à jour via le backend?',

		'Expire cache for pages showing this entry' =>
		'Expiration du cache pour les pages affichant cette entrée',

		'Expire cache for pages showing content from this section' =>
		'Expiration du cache pour les pages affichant le contenu de cette section',

		'Expire cache for the passed URL' =>
		'Expiration du cache pour URL transmise',

		'CacheLite: Expiring the cache' =>
		'CacheLite: expiration du cache',

		'When editing existing entries (one or many, supports the <em>Allow Multiple</em> option) any pages showing this entry will be flushed. Add the following in your form to trigger this filter:' =>
		'Lors de la modification des entrées existantes (une ou plusieurs, prend en charge les options <em> Autoriser plusieurs </ em>), toutes les pages affichant cette entrée seront vidées. Ajoutez ce qui suit dans votre formulaire pour déclencher ce filtre:',

		'This will flush the cache of pages using any entries from this event&#8217;s section. Since you may want to only run it when creating new entries, this will only run if you pass a specific field in your HTML:' =>
		'Cela videra le cache des pages utilisant des entrées lié à cet événement. Puisque vous voudrez peut-être exécuter lors de la création de nouvelles entrées, cela ne fonctionnera que si vous passez un champ spécifique dans votre code HTML:',

		'This will expire the cache of the URL at the value you pass it. For example' =>
		'Expirera le cache de URL à la valeur que vous lui transmettez. Par exemple',

		'Will flush the cache for <code>http://domain.tld/article/123/</code>. If no value is passed it will flush the cache of the current page (i.e., the value of <code>action=""</code> in you form):' =>
		'Vide le cache pour <code> http: //domain.tld/article/123/ </ code>. Si aucune valeur passée, le cache de la page en cours sera vidé (La valeur de <code> action = "" </ code> dans votre formulaire):',

		'This section is scheduled to be removed from the cache. Website will reflect the new state soon.' =>
		'Cette section est prévue pour être retirée du cache. Le site Web reflètera bientôt le nouvel état.',

		'This entry is scheduled to be removed from the cache. Website will reflect the new state soon.' =>
		'Cette entrée est prévue pour être retirée du cache. Le site Web reflètera bientôt le nouvel état.',
	);