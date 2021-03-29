<?php
    include 'cache.php';
    
    /* detect user agent */
    $client_ua = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : "";
    if (!is_string($client_ua)) die('client ua error');
    $not_ie_flag = strpos($client_ua, 'MSIE') === false ? 1 : 0;

    /* set framework settings */
    $cf = new php_simplecache;
    
    $cf->pconfig = array('family' => '/^[ :,|a-zA-Z0-9]{0,256}$/',
                         'subset' => '/^[,\\-a-zA-Z0-9]{0,256}$/',
                         'effect' => '/^[|\\-a-zA-Z0-9]{0,256}$/');

    $cf->dbdbname = 'simplecache';
    $cf->dbtblname = 'css';

    $cf->url_head = 'https://fonts.googleapis.com/css';
    $cf->cache_life = 30 * 24 * 3600;
    $cf->ctype = 'text/css';
    
    $cf->ua = $not_ie_flag ? 'Firefox/38.0' : 'MSIE';
    
    /* set callback functions */
    $cf->make_key_func = function ($url_tail) {
        global $not_ie_flag;
        return ($not_ie_flag ? '1' : '0') . $url_tail;
    };

    $cf->data_filter_func = function ($data) {
        $my_fontstatic_url = '/your/url/to/font.php?path=';
        //$data = str_replace('http://fonts.gstatic.com', $my_fontstatic_url, $data);
        $data = str_replace('https://fonts.gstatic.com', $my_fontstatic_url, $data);
        return $data;
    };

    /* execute framework */
    $cf->execute();
?>
