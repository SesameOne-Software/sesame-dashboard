<?php

	function client_ip()
{
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	else
	{
		return $_SERVER['REMOTE_ADDR'];
	}
}

# Check user's avatar type
function is_animated($avatar)
{
	$ext = substr($avatar, 0, 2);
	if ($ext == "a_")
	{
		return ".gif";
	}
	else
	{
		return ".png";
	}
}

function xor_str($str) {
    $copy = $str;
    $len = strlen($str);
    $key = "uuIVnEc82GNr5uem4yZ0CzmXG3F27hT01cYmnU0jPP5O014GkMTtxgkywC9HAgUJE9a4YyZ9Yk6PzkR4Oj5VmC2ByCuABb4p1dqQ1YkHil6yhaPTOX4K3SmRSp6Rbf5w";
    $key_len = strlen($key);
    
    for($i = 0; $i < $len; $i++) {
        $copy[$i] = $str[$i] ^ $key[$i % $key_len];
    }

    return $copy;
}

function is_valid_sha256($hash) {
    return preg_match('/^[a-f0-9]{64}$/', $hash);
}

?>