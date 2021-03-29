<?php
function str_endwith($a, $b)
{
    $lb = strlen($b);
    if (strlen($a) < $lb) return false;
    if ($a === $b) return true;
    return substr($a, -$lb) === $b;
}

    include 'cache.php';
    
    /* set framework settings */
    $cf = new php_simplecache;
    
    $cf->pconfig = array('path' => '/^\\/[_\\-\\/a-zA-Z0-9]{0,256}\\.(woff|woff2|eot|ttf)$/');

    $cf->dbdbname = 'simplecache';
    $cf->dbtblname = 'font';

    $cf->url_head = 'https://fonts.gstatic.com';
    $cf->cache_life = 30 * 24 * 3600;
    
    /* set callback */
    $cf->ctype_byplist_func = function ($plist) {
        $rpath = $plist['path'];
        if (str_endwith($rpath, '.woff')) return 'font/woff';
        else if (str_endwith($rpath, '.woff2')) return 'font/woff2';
        else if (str_endwith($rpath, '.eot')) return 'font/eot';
        else if (str_endwith($rpath, '.ttf')) return 'font/ttf';
        return NULL;
    };

    $cf->url_tail_func = function ($plist) {
        return $plist['path'];
    };

    /* execute framework */
    $cf->execute();
?>
