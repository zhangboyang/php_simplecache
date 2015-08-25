<?php
    include 'cache.php';
    
    /* set framework settings */
    $cf = new php_simplecache;
    
	$cf->pconfig = array('e' => '/^[a-z0-9]{32}$/',
                        's' => '/^[0-9]{1,4}$/',
                        'd' => '/^[a-z0-9]{0,15}$/',
                        'r' => '/^[A-Z]$/');
    
    $cf->pdconfig = array('d' => 'identicon');

    $cf->dbdbname = 'avatar';
    $cf->dbtblname = 'avatar';

	$cf->url_head = 'https://secure.gravatar.com/avatar/';
	$cf->cache_life = 30 * 24 * 3600;
	
	/* set callback */
	$cf->url_tail_func = function ($plist) {
	    return $plist['e'] .
               '?s=' . $plist['s'] .
               '&d=' . $plist['d'] .
               '&r=' . $plist['r'];
	};
	
	$cf->ctype_bydata_func = function ($data) {
	    if (strncmp($data, "\xff\xd8\xff\xe0", 4) === 0) return 'image/jpeg';
	    if (strncmp($data, "\x89\x50\x4e\x47", 4) === 0) return 'image/png';
	    return NULL;
    };

    /* execute framework */
	$cf->execute();
?>
