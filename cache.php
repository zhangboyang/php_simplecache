<?php
class php_simplecache {

    private $mysqli;
    
    /* db settings */
    var $dbaddr = 'localhost';
    var $dbuser = 'your_username';
    var $dbpasswd = 'your_password';
    var $dbdbname;
    var $dbtblname;
    /* CREATE TABLE tblname (hash CHAR(40) PRIMARY KEY, expires DATETIME, data LONGBLOB, atime DATETIME, ctime DATETIME, count INT UNSIGNED, hashkey LONGTEXT); */
    /* SELECT hash, LENGTH(data), expires, atime, ctime, count, hashkey FROM tblname ORDER BY atime; */
    
    /* user settings */
    var $pconfig; /* arg config: key => regex */
    var $pdconfig = NULL; /* default arg: key => default value */
    var $url_head;
    var $cache_life; /* in seconds */
    var $ctype = NULL;
    var $ua = NULL;
    var $force_ipv6 = false;
    
    /* callback functions */
    var $url_tail_func = NULL; /* arg: $plist; ret: url_tail */
    var $ctype_byplist_func = NULL; /* arg: $plist; ret: ctype */
    var $ctype_bydata_func = NULL; /* arg: $data; ret: ctype */
    var $make_key_func = NULL; /* arg: $url_tail; ret: key to be hashed */
    var $data_filter_func = NULL; /* arg: $data; ret: newdata */

    private function fail($msg)
    {
        http_response_code(503);
        die($msg);
    }

    private function fetch_url($url, $ctype, $ua)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!is_null($ua)) curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($this->force_ipv6 === true) curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
        $data = curl_exec($ch);
        if ($data === false) $this->fail('remote fetch failed');
        $info = curl_getinfo($ch);
        if (!is_null($ctype) && strpos($info['content_type'], $ctype) !== 0) $this->fail('content-type mismath');
        curl_close($ch);
        return $data;
    }

    private function parse_parameters($pconfig)
    {
        $plist = array();
        foreach ($pconfig as $pkey => $ppreg) {
            $pval = array_key_exists($pkey, $_GET) ? $_GET[$pkey] : '';
            if (!is_string($pval)) $this->fail('parameter \'' . $pkey . '\' is not string');
            if (!preg_match($ppreg, $pval)) {
                if (!is_null($this->pdconfig) && array_key_exists($pkey, $this->pdconfig))
                    $pval = $this->pdconfig[$pkey];
                else
                    $this->fail('preg_match for \'' . $pkey .'\' failed');
            }
            $plist[$pkey] = $pval;
        }
        return $plist;
    }


    private function make_url_tail($plist)
    {
        $url_tail = '';
        $emptyflag = 1;
        foreach ($plist as $pkey => $pval)
            if ($pval !== '') {
                if ($emptyflag) { $url_tail = '?'; $emptyflag = 0; }
                else $url_tail .= '&';
                $url_tail .= $pkey . '=' . urlencode($pval);
            }
        return $url_tail;
    }


    private function check_cache($key, $hash)
    {
        $stmt = $this->mysqli->prepare('SELECT data FROM ' . $this->dbtblname . ' WHERE hash = ? AND hashkey IS NOT NULL AND expires > NOW()');
        $stmt->bind_param('s', $hash);
        if (!$stmt->execute()) $this->fail('SELECT failed');
        $stmt->store_result();
        $stmt->bind_result($data);
        $cachehit = $stmt->fetch();
        $stmt->close();
        if ($cachehit) {
            $stmt = $this->mysqli->prepare('UPDATE ' . $this->dbtblname . ' SET atime = NOW(), count = count + 1 WHERE hash = ?');
            $stmt->bind_param('s', $hash);
            if (!$stmt->execute()) $this->fail('UPDATE failed');
            $stmt->close();
            return $data;
        } else return false;
    }

    private function save_cache($key, $hash, $data)
    {
        $stmt = $this->mysqli->prepare('INSERT INTO ' . $this->dbtblname . ' (hash, expires, data, atime, ctime, count, hashkey) VALUES (?, NOW() + INTERVAL ' . $this->cache_life . ' SECOND, ?, NOW(), NOW(), 1, ?) ON DUPLICATE KEY UPDATE expires = VALUES(expires), data = VALUES(data), atime = NOW(), count = count + 1, hashkey = ?');
        $null = NULL;
        $stmt->bind_param('sbss', $hash, $null, $key, $key);
        $stmt->send_long_data(1, $data);
        if (!$stmt->execute()) $this->fail('INSERT failed');
        $stmt->close();
    }

    private function write_to_client($data, $ctype)
    {
        /* max-age has different meaning to cache_life */
        $max_age = $this->cache_life;
        $etag = sha1($data);

        $etagflag = 0;
        if (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER)) {
            $cetag = $_SERVER['HTTP_IF_NONE_MATCH'];
            if (strpos($cetag, $etag) !== false) $etagflag = 1;
        }

        if ($etagflag) {
            header("HTTP/1.1 304 Not Modified");
            header('Cache-Control: public, max-age=' . $max_age);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
            exit(0);
        }

        if (!is_null($ctype)) header('Content-Type: ' . $ctype);
        header('Access-Control-Allow-Origin: *');
        header('Timing-Allow-Origin: *');
        header('Cache-Control: public, max-age=' . $max_age);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
        header('ETag: "' . $etag . '"');

        echo $data;
        exit(0);
    }

    function execute()
    {
        $plist = $this->parse_parameters($this->pconfig);
        $url_tail = is_null($this->url_tail_func) ? $this->make_url_tail($plist) : $this->url_tail_func->__invoke($plist);
        if (!is_null($this->ctype_byplist_func)) $this->ctype = $this->ctype_byplist_func->__invoke($plist);
        $key = is_null($this->make_key_func) ? $url_tail : $this->make_key_func->__invoke($url_tail);
        $hash = sha1($key);
        $this->mysqli = new mysqli($this->dbaddr, $this->dbuser, $this->dbpasswd, $this->dbdbname);
        $data = $this->check_cache($key, $hash);
        if ($data === false) {
            $data = $this->fetch_url($this->url_head . $url_tail, $this->ctype, $this->ua);
            if (!is_null($this->data_filter_func)) $data = $this->data_filter_func->__invoke($data);
            $this->save_cache($key, $hash, $data);
        }
        if (!is_null($this->ctype_bydata_func)) $this->ctype = $this->ctype_bydata_func->__invoke($data);
        $this->mysqli->close();
        $this->write_to_client($data, $this->ctype);
    }
}
?>
