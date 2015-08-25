<?php
    /* simple and faster version using filesystem instead of mysql */
	$MAXLOOPCNT = 1024;
	$BUFSIZE = 1024;
	$DATAPATH = 'yourpath/';
	$CACHELIFE = 3600 * 24 * 7;

	$e = $_GET['e'];
	$s = $_GET['s'];
	$d = $_GET['d'];
	$r = $_GET['r'];

	if (!is_string($e) || !is_string($s) || !is_string($d) || !is_string($r))
		die('argument is not string');

	if (!preg_match('/^[a-z0-9]{0,15}$/', $d))
		$d = 'identicon';
	if (!preg_match('/^[a-z0-9]{32}$/', $e) ||
	    !preg_match('/^[0-9]{1,4}$/', $s) ||
	    !preg_match('/^[a-z0-9]{0,15}$/', $d) ||
	    !preg_match('/^[A-Z]$/', $r))
		die('illegal argument');
	
	$url = 'https://secure.gravatar.com/avatar/' . $e .
	       '?s=' . $s .
	       '&d=' . $d .
	       '&r=' . $r;
	
	$fid = $e . '_' . $s . '_' . $d . '_' . $r;

	$curtime = time();
	if (file_exists($DATAPATH . ($fn = $fid . '.png')) &&
	    ($mtime = filemtime($DATAPATH . $fn)) !== false &&
	    $curtime - $mtime <= $CACHELIFE)
		goto done;
	if (file_exists($DATAPATH . ($fn = $fid . '.jpg')) &&
	    ($mtime = filemtime($DATAPATH . $fn)) !== false &&
	    $curtime - $mtime <= $CACHELIFE)
		goto done;
	
	$remoteimg = fopen($url, 'rb');
	$imghead = fread($remoteimg, 4);
	if ($imghead === "\xff\xd8\xff\xe0")
		$filetype = 'jpg';
	else if ($imghead === "\x89\x50\x4e\x47")
		$filetype = 'png';
	else
		die('unknown filetype');

	$fn = $fid . '.' . $filetype;
	$localimg = fopen($DATAPATH . $fn, 'wb');

	fwrite($localimg, $imghead);
	$loopcnt = 0;
	while (!feof($remoteimg)) {
		if (++$loopcnt > $MAXLOOPCNT)
			die('too many data');
		fwrite($localimg, fread($remoteimg, $BUFSIZE));
	}

done:
	header('Cache-Control: max-age=' . $CACHELIFE);

	if (preg_match('/\.png$/', $fn))
		header('Content-Type: image/png');
	else if (preg_match('/\.jpg$/', $fn))
		header('Content-Type: image/jpeg');
	else
		die('error');

	readfile($DATAPATH . $fn);
	exit(0);

?>

