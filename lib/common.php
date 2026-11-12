<?php 
/*
 * Solus Explorer
 * Copyright (c) 2019-2026 Cryptoscope.io, unclear#0122
 * Released under the MIT License, see LICENSE in the repository root.
 */
    global $version;
    $version="2.0.264";
    global $project_url;
    $project_url = "https://github.com/cryptoscope-io/solus-explorer";
    // URL folder of the admin area: public/admin-console/.
    const ADMIN_PATH = "admin-console";
    /*
     * The client's address. REMOTE_ADDR, unless the request came through a proxy
     * listed in $settings["trusted_proxies"] (IPs or CIDR ranges); then the
     * right-most X-Forwarded-For entry that is not itself a trusted proxy.
     * Client-IP and the left part of X-Forwarded-For are client-controlled and
     * never used on their own.
     */
    function client_ip(){
        global $settings;
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        $trusted = (isset($settings['trusted_proxies']) && is_array($settings['trusted_proxies'])) ? $settings['trusted_proxies'] : [];
        if ($remote === null || !ip_in_ranges($remote, $trusted)) return $remote;
        $hops = array_reverse(array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
        foreach ($hops as $hop) {
            if (!filter_var($hop, FILTER_VALIDATE_IP)) break;
            if (!ip_in_ranges($hop, $trusted)) return $hop;
        }
        return $remote;
    }

    function ip_in_ranges(string $ip, array $ranges){
        $bin = @inet_pton($ip);
        if ($bin === false) return false;
        foreach ($ranges as $range) {
            $parts = explode('/', (string)$range, 2);
            $net = @inet_pton($parts[0]);
            if ($net === false || strlen($net) !== strlen($bin)) continue;
            $bits = isset($parts[1]) ? (int)$parts[1] : strlen($bin) * 8;
            $bytes = intdiv($bits, 8);
            if (substr($bin, 0, $bytes) !== substr($net, 0, $bytes)) continue;
            $rest = $bits % 8;
            if ($rest === 0) return true;
            $mask = chr((0xff << (8 - $rest)) & 0xff);
            if ((substr($bin, $bytes, 1) & $mask) === (substr($net, $bytes, 1) & $mask)) return true;
        }
        return false;
    }

    /*
     * Request parameters as stored in PAGELOG/APILOG: at most 20 scalar values,
     * keys up to 32 and values up to 128 characters, no cookies, no keys that
     * MongoDB would treat as operators or paths.
     */
    function log_args($args){
        if (!is_array($args)) return array();
        $out = array();
        foreach ($args as $k => $v) {
            if (count($out) >= 20) break;
            if (!is_scalar($v) || (isset($_COOKIE[$k]) && $_COOKIE[$k] === $v)) continue;
            $k = substr(strtr((string)$k, ['$' => '_', '.' => '_']), 0, 32);
            $out[$k] = is_string($v) ? substr($v, 0, 128) : $v;
        }
        return $out;
    }

    /*
     * JSON body of a beacon: refused above 16 KB, decoded into an array or null.
     */
    function beacon_body(){
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) return null;
        $raw = file_get_contents("php://input", false, null, 0, 16385);
        if ($raw === false || strlen($raw) > 16384) return null;
        $in = json_decode($raw, true);
        return is_array($in) ? $in : null;
    }

    /* Text for HTML element content and quoted attributes. */
    function h($s){
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function random_hex($byte_length){
        $bytes = random_bytes($byte_length);
        return bin2hex($bytes);
    }
?>