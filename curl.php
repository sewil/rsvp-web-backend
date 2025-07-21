<?php
function curl_post_async($url, $params = array()) {
    // create POST string
    $post_params = array();
    foreach ($params as $key => &$val) 
    {
        $post_params[] = $key . '=' . urlencode($val);
    }
    $post_string = implode('&', $post_params);

    // get URL segments
    $parts = parse_url($url);

    // workout port and open socket
    $port = isset($parts['port']) ? $parts['port'] : 80;
    $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);

    // create output string
    $output  = "POST " . $parts['path'] . " HTTP/1.1\r\n";
    $output .= "Host: " . $parts['host'] . "\r\n";
    $output .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $output .= "Content-Length: " . strlen($post_string) . "\r\n";
    $output .= "Connection: Close\r\n\r\n";
    $output .= isset($post_string) ? $post_string : '';

    // send output to $url handle
    fwrite($fp, $output);
    fclose($fp);
}

/**

 * Send a POST requst using cURL

 * @param string $url to request

 * @param array $post values to send

 * @param array $options for cURL

 * @return string

 */

function curl_post($url, array $post = NULL, array $options = array()) {
    $defaults = array(
        CURLOPT_POST => 1,

        CURLOPT_HEADER => 0,

        CURLOPT_URL => $url,

        CURLOPT_FRESH_CONNECT => 1,

        CURLOPT_RETURNTRANSFER => 1,

        CURLOPT_FORBID_REUSE => 1,

        CURLOPT_TIMEOUT => 4,

        CURLOPT_POSTFIELDS => http_build_query($post)
    );

    $ch = curl_init();

    curl_setopt_array($ch, ($options + $defaults));

    if (!$result = curl_exec($ch)) {
        log_error(curl_error($ch));
    }

    curl_close($ch);

    return $result;

}

/**

 * Send a GET requst using cURL

 * @param string $url to request

 * @param array $get values to send

 * @param array $options for cURL

 * @return string

 */
function curl_get($url, array $get = NULL, array $options = array()) {
    $defaults = array(
        CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get),

        CURLOPT_HEADER => 0,

        CURLOPT_RETURNTRANSFER => TRUE,

        CURLOPT_TIMEOUT => 4
    );

    $ch = curl_init();

    curl_setopt_array($ch, ($options + $defaults));

    if (!$result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }

    curl_close($ch);

    return $result;
}
?>