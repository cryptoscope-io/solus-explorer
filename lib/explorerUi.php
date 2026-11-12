<?php

use Decimal\Decimal;
use WebSocket\Exception;

  include_once __DIR__ . '/common.php';
  // shadow ledger (Gold Rush) helpers - inert unless "shadow_accounting" is enabled
  include_once __DIR__ . '/loaderShadow.php';

  /**
   * A quantum (post-quantum migration) address is a native witness v16 program, rendered as a
   * bech32m string under the coin's segwit prefix.
   *
   * @param string $address
   * @return bool
   */
  function ui_isQuantumAddress($address){
      global $settings;
      if (!is_string($address) || $address === "") { return false; }
      if (!isset($settings["segwit"]["bech32_prefix"])) { return false; }
      $prefix = $settings["segwit"]["bech32_prefix"];
      $sep    = isset($settings["segwit"]["bech32_separator"]) ? (string)$settings["segwit"]["bech32_separator"] : "1";
      $head   = $prefix.$sep;
      if (strpos($address, $head) !== 0) { return false; }
      // bech32 charset position of the witness version character; 's' == 16
      $vchar = substr($address, strlen($head), 1);
      return ($vchar === "s");
  }

  /**
   * Address labels (ADDRESSES.alias, ADDRESSGROUPS.tags) are operator input and may have
   * been written with mongosh, so every output path escapes them here.
   */
  function ui_labelBadge($text, string $class = 'badge-info ml-1 mb-1') : string {
      $text = (string)$text;
      if ($text === '') { return ''; }
      return "<span class='badge ".$class."'>".h($text)."</span>&nbsp;";
  }
  function ui_labelBadges($alias, $tags, string $alias_class = 'badge-info ml-1 mb-1', ?string $tag_class = null) : string {
      $out = ui_labelBadge($alias, $alias_class);
      foreach ((is_array($tags) || is_object($tags)) ? $tags : [] as $tag) {
          $out .= ui_labelBadge($tag, $tag_class ?? $alias_class);
      }
      return $out;
  }
  // CSV field: quoted, and a leading = + - @ tab or CR made inert so spreadsheets don't run it as a formula
  function ui_csvText($text) : string {
      $text = (string)$text;
      if ($text !== '' && strpos("=+-@\t\r", $text[0]) !== false) { $text = "'".$text; }
      return '"'.str_replace('"', '""', $text).'"';
  }
  // '' when a new alias or tag is acceptable, otherwise the message for the admin
  function ui_labelError(string $label) : string {
      if ($label === '') { return 'Enter a label.'; }
      if (!preg_match('//u', $label)) { return 'The label is not valid UTF-8 text.'; }
      if ($label !== trim($label)) { return 'Remove the spaces at the start or end.'; }
      if (preg_match('/[<>"\'\p{Cc}]/u', $label)) { return 'Labels can\'t contain < > " \' or control characters.'; }
      if (preg_match_all('/./su', $label) > 32) { return 'Use at most 32 characters.'; }
      return '';
  }
  // case-insensitive key for spotting duplicate tags; without mbstring only ASCII letters fold
  function ui_labelKey(string $label) : string {
      return function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
  }

  // Utilities
    function milliseconds() {
        $mt = explode(' ', microtime());
        return ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000));
    }
    function bytesToGb($bytes, $dec_precision = 3){
        $gbs = cdec($bytes)/1073741824;
        return dflt($gbs, $dec_precision);
    }
    function normalizeSi($value, $dec_precision = 3){
        $res = new stdClass();
        try {
            if ($value >= 1000000000000000000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000000000000000000,$dec_precision);
                $res->dimension = "Y";
            } else if ($value >= 1000000000000000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000000000000000,$dec_precision);
                $res->dimension = "Z";
            } else if ($value >= 1000000000000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000000000000,$dec_precision);
                $res->dimension = "E";
            } else if ($value >= 1000000000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000000000,$dec_precision);
                $res->dimension = "P";
            } else if ($value >= 1000000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000000,$dec_precision);
                $res->dimension = "T";
            } else if ($value >= 1000000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000000,$dec_precision);
                $res->dimension = "G";
            } else if ($value >= 1000000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value, $dec_precision)/1000000,$dec_precision);
                $res->dimension = "M";
            } else if ($value >= 1000) {
                $res->origin = $value;
                $res->value = dflt(cdec($value/1000, $dec_precision ),$dec_precision);
                $res->dimension = "k";
            } else {
                $res->origin = $value;
                $res->value = is_int($value) ? $value : dflt(cdec($value),$dec_precision);
                $res->dimension = "";
            }
        } catch (Exception $ex) {
            $res->origin = "---";
            $res->value = "err";
            $res->dimension = "";
        }
        return $res;
    }
    function normalizeSiToScale($value, $scale, $dec_precision = 3){
        $res = new stdClass();
        try {
            if ($scale == "Y" || $scale == "y") {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000000000000000000,$dec_precision);
                $res->dimension = "Y";
            } else if ($scale == "Z" || $scale == "z")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000000000000000,$dec_precision);
                $res->dimension = "Z";
            } else if ($scale == "E" || $scale == "e")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000000000000,$dec_precision);
                $res->dimension = "E";
            } else if ($scale == "P" || $scale == "p")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000000000,$dec_precision);
                $res->dimension = "P";
            } else if ($scale == "T" || $scale == "t")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000000,$dec_precision);
                $res->dimension = "T";
            } else if ($scale == "G" || $scale == "g")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000000,$dec_precision);
                $res->dimension = "G";
            } else if ($scale == "M" || $scale == "m")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000000,$dec_precision);
                $res->dimension = "M";
            } else if ($scale == "K" || $scale == "k")  {
                $res->origin = $value;
                $res->value = dflt(cdec($value)/1000,$dec_precision);
                $res->dimension = "k";
            } else {
                $res->origin = $value;
                $res->value = dflt(cdec($value),$dec_precision);
                $res->dimension = "";
            }
        } catch (Exception $ex) {
            $res->origin = "---";
            $res->value = "err";
            $res->dimension = "";
        }
        return $res;
    }
    function timediffToParts($timediff){
        $res = new stdClass();
        $res->string = "";
        if ($timediff >= 0 ) {
            (int)$res->days = floor($timediff/86400);
            (int)$res->hours = floor(($timediff-($res->days*86400))/3600);
            (int)$res->minutes = floor(($timediff-($res->days*86400) - ($res->hours*3600))/60);
            (int)$res->seconds = floor($timediff-($res->days*86400) - ($res->hours*3600) - ($res->minutes*60));
            $res->diff = $timediff;
            if ($res->days > 0){$res->string = $res->string." ".$res->days."d ";}
            $res->string = $res->string.str_pad($res->hours,2,'0', STR_PAD_LEFT);
            $res->string = $res->string.":".str_pad($res->minutes,2,'0', STR_PAD_LEFT);
            $res->string = $res->string.":".str_pad($res->seconds,2,'0', STR_PAD_LEFT);
        } else {
            (int)$res->days =-1;
            (int)$res->hours=-1;
            (int)$res->minutes=-1;
            (int)$res->seconds =-1;
            (int)$res->diff = -1;
            $res->string = "N/A";
        }
        return $res;
    }
    function splitFloat($number, $precision, $decimals = 8){
        $result = new stdClass();
        if (gettype($number) != "object" && is_numeric($number)){
            if ($number<0) { $mult = -1; } else { $mult = 1; }                         
            $r_number   = round($number*$mult,$precision)*$mult;                 
            (string)$result->whole    = "0";                                           
            (string)$result->fraction = "00000000";                                    
            $wn = floor($r_number*$mult);                                                
            (string)$result->whole = $wn*$mult;
            (string)$result->fraction= substr(number_format(round($r_number-$wn*$mult,$precision)*$mult,$precision,'.',''),2);
            $result->fraction = str_pad(substr($result->fraction,0,$decimals),$decimals,'0', STR_PAD_RIGHT);
            $result->number = (float)($result->whole.".".$result->fraction);
            (string)$result->string = ((int)$result->fraction == 0) ?  (string)$result->whole : $result->whole.".".$result->fraction;
        } else if (gettype($number) == "object") {
            if (get_class($number) == "MongoDB\BSON\Decimal128"){
                $result->number           = $number;
                $result->string             = number_format((string) $number, $precision,".",""); 
                $s = explode(".",$result->string);
                if (count($s)>1){
                    (string)$result->whole    = $s[0];
                    (string)$result->fraction = $s[1];
                    $result->fraction = str_pad(substr($result->fraction,0,$decimals),$decimals,'0', STR_PAD_RIGHT);
                } else {
                    (string)$result->whole = "-";
                    (string)$result->fraction = "--";
                }
            } else if (get_class($number) == "Decimal\Decimal"){
                $result->number           = $number;
                $result->string             = (string) $number->round($precision)->toFixed($decimals); 
                $s = explode(".",$result->string);
                if (count($s)>1){
                    (string)$result->whole    = $s[0];
                    (string)$result->fraction = $s[1];
                    $result->fraction = str_pad(substr($result->fraction,0,$decimals),$decimals,'0', STR_PAD_RIGHT);
                } else {
                    (string)$result->whole    = $s[0];
                    (string)$result->fraction = str_pad('',$decimals,'0', STR_PAD_RIGHT);
                }
            } else {
                $result->number           = $number;
                (string)$result->whole    = "-";
                (string)$result->fraction = "--";
                $result->string             = ""; 
            }

        } else {
            //var_dump($number);
            $result->number           = $number;
            (string)$result->whole    = "-";
            (string)$result->fraction = "--";
            $result->string           = "";
        }
        return $result;
    }
    function getIpEndpointInfo($ip){
        $res = new stdClass();
        $res->endpoint = null;
        $res->version = null;
        $res->ip = null;
        $res->port = null;

        preg_match('/^(.*):([0-9]*)$/', $ip, $parts);
        if (count($parts)>0){
            $res->endpoint = $parts[0];
            $res->ip = $parts[1];
            $res->port = (int)$parts[2];
            substr_count($res->ip, ':')>0 ? $res->version = 6 : $res->version = 4;
        }
        return $res;
    }
    function hex2rgba($color, $opacity = false) {
 
        $default = 'rgb(0,0,0)';
     
        //Return default if no color provided
        if(empty($color))
              return $default; 
     
        //Sanitize $color if "#" is provided 
            if ($color[0] == '#' ) {
                $color = substr( $color, 1 );
            }
     
            //Check if color has 6 or 3 characters and get values
            if (strlen($color) == 6) {
                    $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
            } else if ( strlen( $color ) == 3 ) {
                    $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
            } else {
                    return $default;
            }
     
            //Convert hexadec to rgb
            $rgb =  array_map('hexdec', $hex);
     
            //Check if opacity is set(rgba or rgb)
            if($opacity){
                if(abs($opacity) > 1) {$opacity = 1.0;}
                $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
            } else {
                $output = 'rgb('.implode(",",$rgb).')';
            }
     
            //Return rgb(a) color string
            return $output;
    }
    function hex2bytes($hexString){
        $len=strlen($hexString);
        $bytes="";
        for ($i=0;$i<$len;$i=$i+2){
            $charnum=hexdec(substr($hexString,$i,2));
            $bytes.=chr($charnum);
        }
    
        return $bytes;
    }
    function set_null() {
        return null;
    }
    function set_NAN() {
        return "'NaN'";
    }
    function set_zero() {
        return 0;
    }
    function uriEncode($string){
        $res = strtr($string,[
            '!' => '%21',
            "#" => '%23',
            "$" => '%24',
            "%" => '%25',
            "&" => "%26",
            "'" => "%27",
            "(" => '%28',
            ")" => '%29',
            '*' => '%2A',
            "+" => '%2B',
            "," => '%2C',
            "/" => '%2F',
            ":" => '%3A',
            ";" => '%3B',
            "=" => '%3D',
            "?" => '%3F',
            "@" => '%40',
            "[" => '%5B',
            "]" => '%5D',
        ]);
        return $res;
    }
    function clearWebParameter($string){
        return strtr($string,["_"=>""," "=>"","."=>"",","=>"","!"=>"","\\"=>"","'"=>"","\""=>"","{"=>"","}"=>"",":"=>"",";"=>""]);
    }

    /**
     * Sanitize input from GET or POST parameters while maintaining case sensitivity.
     * 
     * @param string $inputName The name of the GET or POST parameter.
     * @param string $method The method of the request, either "get" or "post".
     * @return string Sanitized input.
     */
    function sanitizeInput($inputName, $method = 'post') {
        $inputType = strtolower($method) === 'post' ? INPUT_POST : INPUT_GET;
        $value = filter_input($inputType, $inputName, FILTER_UNSAFE_RAW);
        // Arrays and missing parameters become "", so no operator can reach a query.
        if (!is_string($value)) return '';
        // What FILTER_SANITIZE_STRING did: drop NUL bytes and tags, encode quotes.
        $value = strip_tags(str_replace("\0", '', $value));
        $value = str_replace(["'", '"'], ['&#39;', '&#34;'], $value);
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

  // UI functions

    function ui_getRootURL() : string {
        global $settings;
        if (isset($settings["explorer_address"]) && !empty($settings["explorer_address"])){
            return $settings["explorer_address"];
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }
    }

    function ui_getCoinName() : string{
        global $settings;
        if (isset($settings["coin_name"]) && !empty($settings["coin_name"])){
            return $settings["coin_name"];
        } else {
            return strtoupper($settings["coin_symbol"]);
        }
    }

    function ui_getCanonical(): string {
        global $settings;
        // let's set canoncal to cookie_path
        if (isset($settings['cookie_domain']) && !empty($settings['cookie_path']) 
            && isset($settings['cookie_path']) && !empty($settings['cookie_path'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $uri = $_SERVER['REQUEST_URI'];
                $canonical = $scheme . '://' . $settings['cookie_domain'] . $settings['cookie_path']. strtok((string)$uri, '#');
            return htmlspecialchars($canonical, ENT_QUOTES);
        }


        // Detect scheme
        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';

        // Host (falls back if not set)
        $host = $_SERVER['HTTP_HOST'];

        // Path + query
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // strip fragments (#hash) — not part of canonical
        $uri = strtok($uri, '#');

        return htmlspecialchars($scheme . '://' . $host . $uri, ENT_QUOTES);
    }

    function ui_getCanonicalRootURL(){
        global $settings;
        if (isset($settings['cookie_domain']) && !empty($settings['cookie_path']) 
            && isset($settings['cookie_path']) && !empty($settings['cookie_path'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $canonicalRoot = $scheme . '://' . $settings['cookie_domain'] . $settings['cookie_path'];
            return $canonicalRoot;
        }

                // Detect scheme
        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';

        // Host (falls back if not set)
        $host = $_SERVER['HTTP_HOST'];
        return htmlspecialchars($scheme . '://' . $host, ENT_QUOTES);
    }

    // Visitor country as reported by Cloudflare (CF-IPCountry), "" when absent or not a
    // real ISO code (Cloudflare uses XX for unknown and T1 for Tor).
    function ui_GetVisitorCountry(): string {
        $cc = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
        return (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX') ? $cc : '';
    }

    // True when the visitor must go through TCF consent (EU, EEA, UK, Switzerland).
    // An unknown country counts as in scope. settings "consent_countries" overrides the list.
    function ui_ConsentRequired(): bool {
        global $settings;
        $cc = ui_GetVisitorCountry();
        if ($cc === '') return true;
        $countries = $settings['consent_countries'] ?? [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT',
            'LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',   // EU
            'IS','LI','NO',                                       // EEA
            'GB','CH',                                            // UK GDPR, Swiss FADP
        ];
        return in_array($cc, $countries, true);
    }

    function ui_GetWaterfallAdsData(): stdClass {
        global $settings;

        $ads_data = new stdClass;
        $ads_data->enabled = false;

        if (isset($settings["ads_enabled"])&&!$settings["ads_enabled"]){
            return $ads_data;
        }

        if (isset($settings["ads_priority"]) && !empty($settings["ads_priority"]) && is_array($settings["ads_priority"])){
            $ads_data->priority = $settings["ads_priority"];
        } else {
            return $ads_data;
        }
        
        if (isset($settings["ads_config"])&&!empty($settings["ads_config"])&&is_array($settings["ads_config"])){
            if (in_array("sevio", $ads_data->priority)) {
                if (isset($settings["ads_config"]["sevio_account_id"]) && !empty($settings["ads_config"]["sevio_account_id"]) &&
                    isset($settings["ads_config"]["sevio_inventory_id"]) && !empty($settings["ads_config"]["sevio_inventory_id"])){
                    $ads_data->sevio_account_id = $settings["ads_config"]["sevio_account_id"];
                    $ads_data->sevio_inventory_id = $settings["ads_config"]["sevio_inventory_id"];
                } else {
                    $ads_data->priority = array_values(array_diff($ads_data->priority, ["sevio"]));
                }
            }
            if (in_array("adsense", $ads_data->priority)){
                if (isset($settings["ads_config"]["adsense_client_id"])&&!empty($settings["ads_config"]["adsense_client_id"])){
                    $ads_data->adsense_client_id = $settings["ads_config"]["adsense_client_id"];
                } else {
                    $ads_data->priority = array_values(array_diff($ads_data->priority, ["adsense"]));
                }
            }
            if (count($ads_data->priority)==0){
                return $ads_data;
            }
            if (isset($settings["ads_config"]["units"]) && !empty($settings["ads_config"]["units"]) && is_array($settings["ads_config"]["units"])){
                $units = new stdClass;
                foreach ($settings["ads_config"]["units"] as $unit_id => $unit_config){
                    $units->$unit_id = new stdClass;
                    if (in_array("sevio",$ads_data->priority)){
                        if (isset($unit_config["sevio_id"]) && !empty($unit_config["sevio_id"])){
                            $units->$unit_id->sevio_id = $unit_config["sevio_id"];
                        }
                    }
                    if (in_array("bitmedia",$ads_data->priority)){
                        if (isset($unit_config["bitmedia_id"]) && !empty($unit_config["bitmedia_id"])){
                            $units->$unit_id->bitmedia_id = $unit_config["bitmedia_id"];
                        }
                    }
                    if (in_array("adsense",$ads_data->priority)){
                        if (isset($unit_config["adsense_id"]) && !empty($unit_config["adsense_id"])){
                            $units->$unit_id->adsense_id = $unit_config["adsense_id"];
                        }
                    }
                }
                $ads_data->units = $units;
                $ads_data->enabled = true;
            } else {
                return $ads_data;
            }
        } else {
            return $ads_data;
        }
        return $ads_data;
    }

    function ui_GetAdsData(){
        global $settings;
        $ads_data = new stdClass;
        $ads_data->enabled = false;
       
        $providers = ui_getAdsProviders();
        if (count($providers) > 0){
            $name=_pickAdvertiser($providers);
            if (is_null($name)||empty($name)){
                return $ads_data;
            }
            $ads_data=$providers[$name];
            $ads_data->provider = $name;
            if (isset($ads_data->fallback)){
                $ads_data->fallback_data = _validateBitmedia($settings["ads_providers"][$ads_data->fallback]);
            }
            $ads_data->enabled = true;
        }
        return $ads_data;
    }

    function ui_getAdsProviders(){
        $ads_providers = array();
        global $settings;
        if (ui_getAdsActivationStatus()){
            if (!isset($settings["ads_providers"]) || empty($settings["ads_providers"])){
                return $ads_providers;
            }
            
            foreach ($settings["ads_providers"] as $provider => $provider_settings) {
                if (isset($provider_settings["weight"]) && $provider_settings["weight"] > 0){
                    $pd = ui_validateAdsProvider($provider, $provider_settings);
                    if ($pd != null){
                        $ads_providers[$provider] = $pd;
                    }
                }
            }
        }
        return $ads_providers;
    }

    function ui_validateAdsProvider($provider, $provider_settings){
        switch ($provider) {
            case "adsense":
                return _validateAdSense($provider_settings);
                break;
            case "bitmedia":
                return _validateBitmedia($provider_settings);    
                break;
            case "sevio":
                return _validateSevio($provider_settings);
                break;
            case "adcash":
                return _validateAdcash($provider_settings);
                break;
            case "infolinks":
                return _validateInfolinks($provider_settings);
                break;
            default:
                break;
        }

        return null;
    }

    function _validateAdSense($provider_settings){
        $provider_data = new stdClass;
        $provider_data->weight = $provider_settings["weight"];
        // client_id is required
        if (!isset($provider_settings["client_id"]) || empty($provider_settings["client_id"])){
            return null;
        }
        $provider_data->client_id = $provider_settings["client_id"];

        // units are required
        if (!isset($provider_settings["units"]) || !is_array($provider_settings["units"]) || count($provider_settings["units"]) == 0){
            return null;
        }

        $provider_data->units = new stdClass;
        $units = $provider_settings["units"];
        if (!isset($units["l_desk_728"])||empty($units["l_desk_728"])||!isset($units["l_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->l_desk_728 = $units["l_desk_728"];
        }
        if (!isset($units["r_desk_728"])||empty($units["r_desk_728"])||!isset($units["r_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->r_desk_728 = $units["r_desk_728"];
        }
        if (!isset($units["l_mob_320"])||empty($units["l_mob_320"])||!isset($units["l_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->l_mob_320 = $units["l_mob_320"];
        }
        if (!isset($units["m_mob_320"])||empty($units["m_mob_320"])||!isset($units["m_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->m_mob_320 = $units["m_mob_320"];
        }
        if (!isset($units["r_mob_320"])||empty($units["r_mob_320"])||!isset($units["r_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->r_mob_320 = $units["r_mob_320"];
        }

        return $provider_data;
    }

    function _validateSevio($provider_settings){
        $provider_data = new stdClass;
        $provider_data->weight = $provider_settings["weight"];
        
        // sevio_account_id and sevio_inventory_id are required
        if (!isset($provider_settings["sevio_account_id"]) || empty($provider_settings["sevio_account_id"] || 
            !isset($provider_settings["sevio_inventory_id"]) || empty($provider_settings["sevio_inventory_id"]))){
            return null;   
        }
        
        $provider_data->sevio_account_id = $provider_settings["sevio_account_id"];
        $provider_data->sevio_inventory_id = $provider_settings["sevio_inventory_id"];
        // units are required
        if (!isset($provider_settings["units"]) || !is_array($provider_settings["units"]) || count($provider_settings["units"]) == 0){
            return null;
        }

        $provider_data->units = new stdClass;
        $units = $provider_settings["units"];
        if (!isset($units["l_desk_728"])||empty($units["l_desk_728"])||!isset($units["l_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->l_desk_728 = $units["l_desk_728"];
        }
        if (!isset($units["r_desk_728"])||empty($units["r_desk_728"])||!isset($units["r_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->r_desk_728 = $units["r_desk_728"];
        }
        if (!isset($units["l_mob_320"])||empty($units["l_mob_320"])||!isset($units["l_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->l_mob_320 = $units["l_mob_320"];
        }
        if (!isset($units["m_mob_320"])||empty($units["m_mob_320"])||!isset($units["m_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->m_mob_320 = $units["m_mob_320"];
        }
        if (!isset($units["r_mob_320"])||empty($units["r_mob_320"])||!isset($units["r_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->r_mob_320 = $units["r_mob_320"];
        }
        if (isset($provider_settings["fallback"]) && !empty($provider_settings["fallback"])){
            $provider_data->fallback = $provider_settings["fallback"];
        }

        return $provider_data;
    }

    function _validateBitmedia($provider_settings){
        $provider_data = new stdClass;
        $provider_data->weight = $provider_settings["weight"];

        // units are required
        if (!isset($provider_settings["units"]) || !is_array($provider_settings["units"]) || count($provider_settings["units"]) == 0){
            return null;
        }

        $provider_data->units = new stdClass;
        $units = $provider_settings["units"];
        if (!isset($units["l_desk_728"])||empty($units["l_desk_728"])||!isset($units["l_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->l_desk_728 = $units["l_desk_728"];
        }
        if (!isset($units["r_desk_728"])||empty($units["r_desk_728"])||!isset($units["r_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->r_desk_728 = $units["r_desk_728"];
        }
        if (!isset($units["l_mob_320"])||empty($units["l_mob_320"])||!isset($units["l_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->l_mob_320 = $units["l_mob_320"];
        }
        if (!isset($units["m_mob_320"])||empty($units["m_mob_320"])||!isset($units["m_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->m_mob_320 = $units["m_mob_320"];
        }
        if (!isset($units["r_mob_320"])||empty($units["r_mob_320"])||!isset($units["r_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->r_mob_320 = $units["r_mob_320"];
        }

        return $provider_data;
    }

    function _validateAdcash($provider_settings){
        $provider_data = new stdClass;
        $provider_data->weight = $provider_settings["weight"];

        // units are required
        if (!isset($provider_settings["units"]) || !is_array($provider_settings["units"]) || count($provider_settings["units"]) == 0){
            return null;
        }

        $provider_data->units = new stdClass;
        $units = $provider_settings["units"];
        if (!isset($units["l_desk_728"])||empty($units["l_desk_728"])||!isset($units["l_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->l_desk_728 = $units["l_desk_728"];
        }
        if (!isset($units["r_desk_728"])||empty($units["r_desk_728"])||!isset($units["r_desk_728"]["id"])){
            return null;
        } else {
            $provider_data->units->r_desk_728 = $units["r_desk_728"];
        }
        if (!isset($units["l_mob_320"])||empty($units["l_mob_320"])||!isset($units["l_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->l_mob_320 = $units["l_mob_320"];
        }
        if (!isset($units["m_mob_320"])||empty($units["m_mob_320"])||!isset($units["m_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->m_mob_320 = $units["m_mob_320"];
        }
        if (!isset($units["r_mob_320"])||empty($units["r_mob_320"])||!isset($units["r_mob_320"]["id"])){
            return null;
        } else {
            $provider_data->units->r_mob_320 = $units["r_mob_320"];
        }

        return $provider_data;
    }

    function _validateInfolinks($provider_settings){
        $provider_data = new stdClass;
        $provider_data->weight = $provider_settings["weight"];
        if (!isset($provider_settings["infolinks_pid"]) || is_null($provider_settings["infolinks_pid"]) ||
            !isset($provider_settings["infolinks_wsid"]) || is_null($provider_settings["infolinks_wsid"])){
            return null;
        }

        $provider_data->infolinks_pid = $provider_settings["infolinks_pid"];
        $provider_data->infolinks_wsid = $provider_settings["infolinks_wsid"];

        return $provider_data;
    }

    function _pickAdvertiser($providers): ?string {
        // Calculate the total sum of weights
        $total = 0;
        
        foreach ($providers as $name => $provider_settings) {
            $total += $provider_settings->weight;
        }
        if ($total <= 0) {
            return null;
        }

        // Get a random value between 1 and total
        $rand = mt_rand(1, $total);

        // Walk through the advertisers until threshold is reached
        $cumulative = 0;
        foreach ($providers as $name => $provider_settings) {
            $cumulative += $provider_settings->weight;
            if ($rand <= $cumulative) {
                return $name;
            }
        }

        // Should never reach here if inputs are valid
        return null;
    }

    function ui_checkMagicNumber($number){
        global $settings;
        if (is_string($number) && $number !== '' && !empty($settings["magic_key"]) && hash_equals((string)$settings["magic_key"], $number)){
            return true;
        } else {
            return false;
        }
    }
    function ui_getAdsActivationStatus(){
        global $settings;
        if (!isset($settings["ads_enabled"]) || !$settings["ads_enabled"]){
            return false;
        }
        if (isset($settings["subscription_enabled"]) && $settings["subscription_enabled"]){
            $exp_ts = strtotime($settings["subscription_endtime"]);
            if (time() > $exp_ts) {
                if (isset($settings["ads_enabled"]) && $settings["ads_enabled"]){
                    return true;
                }
            }
        } else {
            if (isset($settings["ads_enabled"]) && $settings["ads_enabled"]){
                return true;
            }
        }
        return false;
    }

    function ui_getExpiredStatus(){
        global $settings;

        if (isset($settings["subscription_enabled"]) && $settings["subscription_enabled"]){
            $exp_ts = strtotime($settings["subscription_endtime"]);
            if (time() > $exp_ts + 1*7*24*60*60) {
              // expired more than two weeks, redirect to expired page (to be done)
              return "complete";
            } else if (time() > $exp_ts) {
              // expired less than two weeks, stop syncing
              if (isset($settings["ads_enabled"]) && $settings["ads_enabled"]){
                return "no";
              }
              return "yes";
            } else if (time() > $exp_ts - 7*24*60*60) {
              return "soon";
            }
        } else {
            return "no";
        }
    }

    function ui_GetDaemonVersionHumanReadable($ver) : string {
        if (is_numeric($ver)){
            $ver = str_pad($ver,8,"0",STR_PAD_LEFT);
            $ver_hr = intval(substr($ver,0,2)).".".intval(substr($ver,2,2)).".".intval(substr($ver,4,2)).".".intval(substr($ver,6,2));
            return $ver_hr;
        } else {
            return $ver;
        }
    }

    function getSkinName(){
        global $settings;
        if (array_key_exists("explorer_skin",$settings)){
            return $settings["explorer_skin"];
        } else {
            return "solus-skin";
        }
    }
    function aggregateVins($vins){
        $res = array();
        foreach($vins as $vin){
            if (array_key_exists($vin->vin_address,$res)){
                $res[$vin->vin_address] = rpp(rpp($res[$vin->vin_address]) + rpp($vin->vin_amount));
            } else {
                $res[$vin->vin_address] = rpp($vin->vin_amount);
            }
        }
        return $res;
    }
    function aggregateVouts($vouts){
        $res = array();
        foreach($vouts as $vout){
            if (array_key_exists($vout->vout_address,$res)){
                $res[$vout->vout_address] = rpp(rpp($res[$vout->vout_address]) + rpp($vout->vout_amount));
            } else {
                $res[$vout->vout_address] = rpp($vout->vout_amount);
            }
        }
        return $res;
    }
    function getCollateralAmount(){ // old
        global $settings;
        global $rpc;

        $res = @cdec(0);
        $mc = $settings["masternode_collateral"];
        if ($mc == -1 && isset($settings["variable_collateral"])) {
            $bh = rpc_getblockcount();
            $res = 0;
            $vc = $settings["variable_collateral"];
            foreach ($vc as $key => $val){
                if ($bh >= $key){
                    if (is_numeric($val)){
                        $res = @cdec($val);
                    } else if (is_array($val)){
                            $res = $val;
                    }
                    break;  
                }
            }
        } else {
            $res = $mc;
        }
        return $res;
    }
    function ui_getCollateralAmounts(int $height){
        global $settings;
        $res = null;

        $mn_conf = ui_getMasternodeConfig($height);
        if (!is_null($mn_conf)){
            $res = array();
            foreach ($mn_conf["tiers"] as $tier_name=>$tier_conf){
                $res[$tier_name] = $tier_conf["collateral_amount"];
            }
        }
        return $res;
    }
    function ui_getMasternodesCount(int $height){
        global $settings;
        $res = null;
        $mn_conf = ui_getMasternodeConfig($height);
        if (!is_null($mn_conf)){
            $mn_methods = null; $mn_filter = null;
            foreach ($mn_conf as $tier_name=>$tier_conf){
                if ($tier_conf["method_set"] != $mn_methods || $tier_conf["tier_filter"] != $mn_filter){

                }
            }
        }

        return $res;
    }
    function ui_getMasternodeConfig(int $height){
        global $settings;
        $res = null;
        if (count($settings["masternode_config"]) > 1){
            foreach ($settings["masternode_config"] as $st_blk=>$conf){
                if ($st_blk <= $height){
                    $mn_conf = $conf;
                    break;
                }
            }
        } 
        return $res;
    }
    function ui_GetUserIpAddress(){
        return client_ip();
    }

    /**
 * Get client connection details + UA-CH (User-Agent Client Hints).
 *
 * The client IP follows client_ip() (trusted_proxies).
 */
    function ui_getClientInfo(): array {
        $res = array();
        // ---- Client & proxy IPs ----
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $res["clientIp"] = client_ip();
        if ($res["clientIp"] !== $remoteIp) {
            $res["proxyIp"] = $remoteIp;
        }

        // ---- Classic UA ----
        $res["user_agent"] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $browser = ui_GetBrowser();
        $res["browser"]   = $browser["browser"];
        $res["browser_version"]   = $browser["browser_version"];


        // ---- UA-CH headers (raw) ----
        $h = static function(string $key): ?string {
            return $_SERVER[$key] ?? null;
        };

        $secChUa                = $h('HTTP_SEC_CH_UA');                     // e.g. "Chromium";v="126", "Google Chrome";v="126"
        $secChUaMobile          = $h('HTTP_SEC_CH_UA_MOBILE');               // ?1 / ?0
        $secChUaPlatform        = $h('HTTP_SEC_CH_UA_PLATFORM');             // "Windows", "Android", "iOS", "macOS", "Linux"
        $secChUaPlatformVersion = $h('HTTP_SEC_CH_UA_PLATFORM_VERSION');     // "15.6.1", etc.
        //$secChUaArch            = $h('HTTP_SEC_CH_UA_ARCH');                 // "x86", "arm"
        //$secChUaBitness         = $h('HTTP_SEC_CH_UA_BITNESS');              // "64"
        $secChUaModel           = $h('HTTP_SEC_CH_UA_MODEL');                // "Pixel 7" (mobile only)
        //$secChUaFullVersion     = $h('HTTP_SEC_CH_UA_FULL_VERSION');         // "126.0.6478.62"
        //$secChUaFullVersionList = $h('HTTP_SEC_CH_UA_FULL_VERSION_LIST');    // brand list with full versions

        // ---- Parsers ----
        $parseBrands = static function (?string $header): array {
            // From: "Chromium";v="126", "Google Chrome";v="126"
            if (!$header) return [];
            $out = [];
            if (preg_match_all('/"([^"]+)"\s*;\s*v="([^"]+)"/', $header, $m, PREG_SET_ORDER)) {
                foreach ($m as $row) {
                    $out[] = ['brand' => $row[1], 'version' => $row[2]];
                }
            }
            return $out;
        };

        $res["brands"]             = $parseBrands($secChUa);
        // 'full_version'       => $secChUaFullVersion,
        // 'full_version_list'  => $parseBrands($secChUaFullVersionList),
        $res["mobile"]             = ($secChUaMobile === '?1');
        $res["platform"]           = trim($secChUaPlatform, '"') ?? null;
        $res["platform_version"]   = trim($secChUaPlatformVersion, '"') ?? null;
        //'arch'               => trim($secChUaArch ?? '', '"'),
        //'bitness'            => trim($secChUaBitness ?? '', '"'),
        $res["model"]              = trim($secChUaModel, '"') ?? null;
        // raw headers (optional; comment out if you prefer not to keep them)
        // 'raw' => [
        //     'Sec-CH-UA'                    => $secChUa,
        //     'Sec-CH-UA-Full-Version'       => $secChUaFullVersion,
        //     'Sec-CH-UA-Full-Version-List'  => $secChUaFullVersionList,
        //     'Sec-CH-UA-Mobile'             => $secChUaMobile,
        //     'Sec-CH-UA-Platform'           => $secChUaPlatform,
        //     'Sec-CH-UA-Platform-Version'   => $secChUaPlatformVersion,
        //     'Sec-CH-UA-Arch'               => $secChUaArch,
        //     'Sec-CH-UA-Bitness'            => $secChUaBitness,
        //     'Sec-CH-UA-Model'              => $secChUaModel,
        // ],
        

        return $res;
    }

    function ui_GetUserAgent(){
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }


    function ui_GetBrowser(): array {
        $ua  = $_SERVER['HTTP_USER_AGENT']     ?? '';
        $ch  = $_SERVER['HTTP_SEC_CH_UA']      ?? '';
        $ch_mob = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
        $ua = trim($ua);
        $ch = trim($ch);

        // Case 1: Nothing set
        if ($ua === '' && $ch === '') {
            return ["browser" => "unset", "browser_version" => null];
        }

        // ---- Prefer Client Hints if available ----
        if ($ch !== '') {
            // Example: "Chromium";v="125", "Google Chrome";v="125", "Not:A-Brand";v="99"
            $brands = [];
            foreach (explode(',', $ch) as $part) {
                if (preg_match('/"([^"]+)";v="([^"]+)"/', $part, $m)) {
                    $brands[$m[1]] = $m[2];
                }
            }

            $browser = 'unknown';
            $version = null;

            $browser = 'unknown';
            foreach ($brands as $brand => $ver) {
                if (stripos($brand, 'Chrome') !== false) {
                    $browser = 'Chrome';
                    $version = $ver;
                    continue;
                } elseif (stripos($brand, 'Chromium') !== false) {
                    if ($browser=='unknown'){
                        $browser = 'Chromium';
                        $version = $ver;
                    }
                    continue;
                } elseif (stripos($brand, 'Safari') !== false) {
                    $browser = 'Safari';
                    $version = $ver;
                    continue;
                } elseif (stripos($brand, 'Firefox') !== false) {
                    $browser = 'Firefox';
                    $version = $ver;
                    continue;
                } elseif (stripos($brand, 'Edge') !== false) {
                    $browser = 'Edge';
                    $version = $ver;
                    continue;
                } elseif (stripos($brand, 'Opera') !== false) {
                    $browser = 'Opera';
                    $version = $ver;
                    continue;
                }
            }

            // Add Mobile if CH indicates so
            if ($ch_mob === '?1') {
                $browser .= ' Mobile';
            }

            return ["browser" => $browser, "browser_version" => $version];
        }

        // ---- Fallback: Parse User-Agent ----
        if ($ua !== '') {
            $browser = 'unknown';

            if (preg_match('/Edg/i', $ua)) {
                $browser = 'Edge';
            } elseif (preg_match('/OPR|Opera/i', $ua)) {
                $browser = 'Opera';
            } elseif (preg_match('/Chrome/i', $ua)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Safari/i', $ua)) {
                $browser = 'Safari';
            } elseif (preg_match('/Firefox/i', $ua)) {
                $browser = 'Firefox';
            } elseif (preg_match('/MSIE|Trident/i', $ua)) {
                $browser = 'Internet Explorer';
            }

            // Detect Mobile
            if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
                $browser .= ' Mobile';
            }

            return ["browser" => $browser, "browser_version" => null];
        }

        return ['browser' => 'unknown', 'browser_version' => null];
    }

    function getAddressGroupMasternodes($rtype, $rid){
        global $settings;
        (string)$groupid = null;
        if ($rtype == "address"){
            // get groupid by addres
            $ars = db_getAddressData($rid);
            if (count($ars)>0){
                $groupid = $ars[0]->group_id;
            } else {
                return null;
            }
        } else {
            $groupid = $rid;
        }
        try {
            $col = getCollateralAmount(); //2.0.166
            if (is_numeric($col)){
                $mncoll = rpp((float)$col);
            } else if (is_array($col)){
                $mncoll = isset($col["regular"])?$col["regular"]:0;
            }
        }
        catch (Exception $e){
            $mncoll = 0;
        }
    
        // get addresses by groupid
        //$stime = milliseconds();
        if (isset($settings["dip2_support"]) && $settings["dip2_support"]){
            $gal = db_getAddressesByGroupId($groupid,0,0,[],["_id" => 1]);
        } else {
            $gal = db_getAddressesByGroupId($groupid,0,0,['balance' => ['$gte' => $mncoll]]);
        }
        //echo "getAddressesByGroupId time => ".(milliseconds()-$stime)." ms".PHP_EOL;
        
        if (count($gal)>0){
            $mnaddr = array();
            foreach ($gal as $addr){
                $mnaddr[$addr->_id] = $addr;
            }
            $res = new stdClass();
            $res->mns = array();
            $res->states = array();
            try {
                //$stime = milliseconds();
                $mns = ui_getMasternodeStatus();
                //echo "getMasternodeStatus time => ".(milliseconds()-$stime)." ms".PHP_EOL;
            } catch (Exception $e) {}

            if (count($mns)>0){
                foreach ($mns as $mn){
                    if (array_key_exists($mn->addr,$mnaddr)){
                        array_push($res->mns, $mn);  
                        if (array_key_exists($mn->status, $res->states)){
                            $res->states[$mn->status] +=1;
                        } else {
                            $res->states[$mn->status] = 1;
                        }
                    }           
                }
            }
                
            if (count($res->mns)>0){
                return $res;
            } else {
                return null;
            }
        } else {
            return null;
        }

    }
    function getChartColors($number = 1, $opacity = 1.0, $as_array = false){
        global $settings;
        if ($number == 0) {$number = 1;}
        if (array_key_exists("chart_colors",$settings)){
            $colorlist = '';
            $colorarray = array();
            $plen=count($settings["chart_colors"]);
            if ($plen > $number){
                $step=floor(($plen+1)/$number);
            } else {
                $step=1; $number=$plen;
            }
            if ($step < 1){ $step = 1;}
            $n=0;
            for ($i = 0; $i<$number; $i++){
                $colorlist = $colorlist.',"'.hex2rgba($settings["chart_colors"][$n],$opacity).'"';
                array_push($colorarray, hex2rgba($settings["chart_colors"][$n],$opacity));
                $n += $step;
            }
            if ($as_array){
               return $colorarray;
            } else {
               return (trim($colorlist,',')); 
            }
            
        } else {
            return null;
        }

    }
    function getChartColorArray($number = 1){
        global $settings;
        if (array_key_exists("chart_colors",$settings)){
            $colorarray = array();
            $plen=count($settings["chart_colors"]);
            if ($plen > $number){
                $step=floor(($plen+1)/$number);
            } else {
                $step=1; $number=$plen;
            }
            if ($step < 1){ $step = 1;}
            $n=0;
            for ($i = 0; $i<$number; $i++){
                array_push($colorarray,$settings["chart_colors"][$n]);
                $n += $step;
            }
            return $colorarray;
        } else {
            return null;
        }
    }
    function getNamedColor($name){
        global $settings;
        if (array_key_exists("named_colors", $settings)){
            if (array_key_exists($name,$settings["named_colors"])){
                return $settings["named_colors"][$name];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
    function getRandomColor(){
        global $settings;
        $rc = str_pad(dechex(random_int(10,245)),2,"0",STR_PAD_LEFT);
        $gc = str_pad(dechex(random_int(10,245)),2,"0",STR_PAD_LEFT);
        $bc = str_pad(dechex(random_int(10,245)),2,"0",STR_PAD_LEFT);

        return '#'.$rc.$gc.$bc;
    }
    function getMarketLink($exchange, $base, $quote){
        global $settings;
        $ex = strtolower($exchange);
        $markets = array();
        
        if (isset($settings['markets'])){
            $markets = $settings['markets'];
            $link = null;
            foreach($markets as $m){
                if (isset($m['exchange']) && $m['exchange']==strtolower($exchange) && $m['base']==$base && $m['quote']==$quote){
                    if (isset($m['link']))  { 
                        $link = $m['link']; 
                    } else {
                        switch ($ex) {
                            case "coinbase-pro":
                                return "https://pro.coinbase.com/trade/".$quote."-".$base;
                                break;
                            case "crypto-bridge":
                                return "https://wallet.crypto-bridge.org/market/BRIDGE.".$quote."_BRIDGE.".$base;
                                break;
                            case "crex24":
                                return "https://crex24.com/exchange/".$quote."-".$base;
                                break;
                            case "graviex":
                                return "https://graviex.net/markets/".strtolower($quote).strtolower($base);
                                break;
                            case "stex":
                                return "https://app.stex.com/ru/trade/pair/".$base."/".$quote;
                                break;
                            case "binance":
                                return "https://api.binance.com/api/v1/ticker/24hr?symbol=".$quote.$base;
                                break;
                            case "escodex":
                                return "https://wallet.escodex.com/market/ESCODEX.".$quote."_ESCODEX.".$base;
                                break;
                            case "bitforex":
                                return "https://www.bitforex.com/en/spot/".strtolower($quote)."_".strtolower($base);
                                break;
                            case "hitbtc":
                                return "https://hitbtc.com/".$quote."-to-".$base;
                                break;
                            case "kukoin":
                                return "https://kukoin.com/trade";
                                break;
                            case "exmo":
                                return "https://exmo.com/en/trade#?pair=".$quote."_".$base;
                                break;
                            case "tradesatoshi":
                                return "https://tradesatoshi.com/Exchange/?market=".$quote."_".$base;
                                break;
                            case "coinexchange":
                                return "https://www.coinexchange.io/market/".$quote."/".$base;
                                break;
                            case "bitfinex":
                                return "https://www.bitfinex.com/SAN/";
                                break;
                            case "poloniex":
                                return "https://poloniex.com/exchange#".strtolower($base)."_".strtolower($quote);
                                break;
                            case "bittrex":
                                return "https://international.bittrex.com/Market/Index?MarketName=".$base."-".$quote;
                                break;
                            case "birake":
                                return "https://trade.birake.com/market/BIRAKE.".$quote."_BIRAKE.".$base;
                                break;
                            case "coinex":
                                return "https://www.coinex.com/exchange?currency=".strtolower($base)."&dest=".strtolower($quote);
                                break;
                            case "livecoin":
                                return "https://www.livecoin.net/en/trading/".strtoupper($quote)."_".strtoupper($base);
                                break;
                            case "mercatox":
                                return "https://mercatox.com/exchange/".strtoupper($quote)."/".strtoupper($base);
                                break;
                            case "bisq":
                                return "https://bisq.network/markets/?currency=".strtolower($quote)."_".strtolower($base);
                                break;
                            case "deliondex":
                                return "https://dex.delion.online/market/DELION.".$quote."_DELION.".$base;
                                break;
                            case "probit":
                                return "https://www.probit.com/app/exchange/".$quote."-".$base;
                                break;
                            case "bitfineon":
                                return "https://exchange.bitfineon.com/trade/".$quote."-".$base;
                                break;
                            case "huobi":
                                return "https://www.huobi.com/exchange/".strtolower($quote)."_".strtolower($base)."/";
                                break;
                            case "southxchange":
                                return "https://www.southxchange.com/Market/Book/".$quote."/".$base;
                                break;
                            case "hotbit":
                                return "https://www.hotbit.io/exchange?symbol=".$quote."_".$base;
                                break;
                            case "altilly":
                                return "https://www.altilly.com/market/".$quote."_".$base;
                                break;
                            case "finexbox":
                                return "https://www.finexbox.com/market/pair/".strtoupper($quote)."-".strtoupper($base).".html";
                                break;
                            case "okex":
                                return "https://www.okex.com/trade-spot/".strtolower($quote)."-".strtolower($base);
                                break;
                            case "vcc":
                                return "https://vcc.exchange/exchange/basic?currency=".strtolower($base)."&coin=".strtolower($quote);
                                break;
                            case "exchangeassets":
                                return "https://exchange-assets.com/en/?market=".strtolower($quote)."_".strtolower($base);
                                break;
                            case "dex-trade":
                                return "https://dex-trade.com/spot/trading/".strtoupper($quote).strtoupper($base);
                                break;
                            default:
                                return null;
                        }
                    }
                    
                }
            }
            return $link;
        } 
    }
    function getGoogleTagId(){
        global $settings;
        if (
            isset($settings["google_tag_id"]) && !empty($settings["google_tag_id"])
            // &&isset($settings["cmp_enabled"]) && $settings["cmp_enabled"])
            ){
            return $settings["google_tag_id"];
        } else {
            return "";
        }
    }
    // Side menu badge ("Operated by", "Hosted by", ...): label, then the logo
    // (or the name when there is no logo), linked when $href is set.
    function ui_RenderMenuBadge($label, $name, $href, $img, $height, $col){
        global $settings;
        if (empty($img) && empty($name)) {
            return;
        }
        $inner = !empty($img)
            ? '<img class="p-0 m-0" src="'.$settings["explorer_address"].'/'.ltrim($img, '/').'" height="'.$height.'" alt="'.htmlspecialchars($name ?: $label).'" loading="lazy">'
            : htmlspecialchars($name);
        echo '<div class="col '.$col.'">';
        echo '<p class="pl-2 m-0 py-0">'.htmlspecialchars($label).'</p>';
        if (!empty($href)) {
            echo '<a class="pl-2 m-0 py-0 logo" href="'.htmlspecialchars($href).'" target="_blank" rel="noopener">'.$inner.'</a>';
        } else {
            echo '<span class="pl-2 m-0 py-0 logo">'.$inner.'</span>';
        }
        echo '</div>';
    }
    function getCopyrightYears(){
        $res = new stdClass();
        $res->start = 2019;
        $res->end = (int)date("Y");
        if ($res->start != $res->end) {
            $res->string = $res->start."–".$res->end;
            $res->html = $res->start."&#8209;".$res->end;
        } else {
            $res->string = $res->end;
            $res->html = $res->end;
        }
        return $res;
    }

  // RPC Requests
    function getDaemonInfo(){
        global $settings;
        $res = new stdClass();
        try{
            if ($settings["getinfo_method"] == 'getinfo'){
                $info = rpc_getinfo();
            } else if ($settings["getinfo_method"] == 'getnetworkinfo') {
                $info = rpc_getnetworkinfo();
            }
            $res->status = "ONLINE";
            $res->version = "unknown";
            if(isset($info->version)){ 
                $res->version = ui_GetDaemonVersionHumanReadable($info->version) ;
            } else if (isset($info->client_version)){
                $res->version = ui_GetDaemonVersionHumanReadable($info->client_version) ;
            }
            $res->protocolversion = "unknown";
            if (isset($info->protocolversion)){
                $res->protocolversion = $info->protocolversion;
            } else if(isset($info->protocol_version)){
                $res->protocolversion = $info->protocol_version;
            }
            $res->connections = !isset($info->connections) ? "unknown" : $info->connections;
        } catch (Exception $e) {
            $res->status = "OFFLINE";
            $res->version = "not available";
            $res->protocolversion = "not available";
            $res->connections = "not available";
        }
        return $res;
    }
    function getRpcCoinSupply(){
        global $settings;
        $res = new stdClass();
        try {
            if ($settings["getinfo_method"] == 'getinfo'){
                $info = (array)rpc_getinfo();
            } else if ($settings["getinfo_method"] == 'getnetworkinfo') {
                $info = (array)rpc_getnetworkinfo();
            }
            $res->moneysupply = !isset($info["moneysupply"]) ? "---" : fpp($info["moneysupply"]);
            $res->zerocoinsupply = !isset($info[$settings["z_supply_mark"]]) ? "---" : $info[$settings["z_supply_mark"]]["total"];

        } catch (Exception $ex) {
            $res->moneysupply = "---";
            $res->zerocoinsupply = "---";
        }
        return $res;
    }
    function ui_getMempoolInfo(){
        global $settings;
        $res = new stdClass();
        try {

            $info = rpc_getmempoolinfo();

            $res->size = !isset($info->size) ? "---" : (int)$info->size;
            $res->bytes = !isset($info->bytes) ? "---" : (int)$info->bytes;

        } catch (Exception $ex) {
            $res->size = "---";
            $res->bytes = "---";
        }
        return $res;
    }
    function ui_getMasternodeCount($ignore_cache = true){
        global $settings;
        $res = new stdClass();
        $cache_timeout = 900; // seconds
        $load_from_daemon = false;
        if (!$ignore_cache) {
            $cache = db_getMasternodeCache(time()-$cache_timeout);
            if (count($cache)<1){
                $load_from_daemon = true;
            } else {
                // proceed with cache
                $cache = $cache[0];
                if (isset($cache->count)){
                        $res = $cache->count;
                } else {
                    $load_from_daemon = true;
                }
            }
        } else {
            $load_from_daemon = true;
        }
        if ($load_from_daemon){
            try {
                if (isset($settings['count_masternodes_method'])) {
                    if ($settings['count_masternodes_method'] == 'masternode_count'){
                        $mnc = rpc_masternodecount();
                    } else if ($settings['count_masternodes_method'] == 'getmasternodecount') {
                        $mnc = (array)rpc_getmasternodecount();
                    } else if ($settings['count_masternodes_method'] == 'smartnode_count') {
                        $mnc = (array)rpc_smartnodecount();
                    } else {
                        $mnc = rpc_masternodecount();
                    }
                } else {
                    $mnc = rpc_masternodecount();
                }
                if (is_array($mnc)){
                    if (count($mnc)>1) {
                        if (array_key_exists("total",$mnc)) { $res->total = $mnc["total"]; } else { $res->total = "---"; }
                        if (array_key_exists("enabled",$mnc)) { $res->enabled = $mnc["enabled"]; }else { $res->enabled = "---"; }
                        if (array_key_exists("stable",$mnc)) { $res->stable = $mnc["stable"]; }else { $res->stable = "---"; }
                        if (array_key_exists("inqueue",$mnc)) { $res->inqueue = $mnc["inqueue"]; }else { $res->inqueue = "---"; }
                        if (array_key_exists("ipv4",$mnc)) { $res->ipv4 = $mnc["ipv4"]; }
                        if (array_key_exists("ipv6",$mnc)) { $res->ipv6 = $mnc["ipv6"]; }
                        if (array_key_exists("onion",$mnc)) { $res->onion = $mnc["onion"]; }
                    } else {
                        $res->total = $mnc[0];
                        $res->enabled = "---";
                        $res->stable = "---";
                        $res->inqueue = "---";
                        $res->ipv4 = "---";
                        $res->ipv6 = "---";
                        $res->onion = "---";
                    }
                } else if (is_numeric($mnc)) {
                    $res->total = $mnc;
                    $res->enabled = "---";
                    $res->stable = "---";
                    $res->inqueue = "---";
                    $res->ipv4 = "---";
                    $res->ipv6 = "---";
                    $res->onion = "---";
                    $ml = ui_getMasternodeStatus();
                    if (count($ml)>0){
                        $res->enabled = 0;
                        $res->ipv4 = 0;
                        $res->ipv6 = 0;
                    }
                    foreach($ml as $mn){
                        if($mn->status == "ENABLED"){$res->enabled++;};
                        if($mn->network == "ipv4"){$res->ipv4++;};
                        if($mn->network == "ipv6"){$res->ipv6++;};
                    }
                } else if (is_object($mnc)) {
                    if (isset($mnc->total)) { $res->total = $mnc->total; } else { $res->total = "---"; }
                    if (isset($mnc->enabled)) { $res->enabled = $mnc->enabled; }else { $res->enabled = "---"; }
                    if (isset($mnc->stable)) { $res->stable = $mnc->stable; }else { $res->stable = "---"; }
                    if (isset($mnc->inqueue)) { $res->inqueue = $mnc->inqueue; }else { $res->inqueue = "---"; }
                    if (isset($mnc->ipv4)) { $res->ipv4 = $mnc->ipv4; }
                    if (isset($mnc->ipv6)) { $res->ipv6 = $mnc->ipv6; }
                    if (isset($mnc->onion)) { $res->onion = $mnc->onion; }
                    if (isset($mnc->detailed)){ $res->detailed = $mnc->detailed; }
                } else {
                    $res->total = "---";
                    $res->enabled = "---";
                    $res->stable = "---";
                    $res->inqueue = "---";
                    $res->ipv4 = "---";
                    $res->ipv6  = "---";
                    $res->onion = "---";
                }
            } catch (Exception $ex) {
                $res->total = "---";
                $res->enabled = "---";
                $res->stable = "---";
                $res->inqueue = "---";
                $res->ipv4 = "---";
                $res->ipv6 = "---";
                $res->onion = "---";
            }   
        }
        return $res;
    }
    function ui_getMasternodeStatus($address = "", $ignore_cache = true){
        global $settings;
        $res = array();
        $cache_timeout = 290; // seconds
        $load_from_daemon = false;
        if (!empty($address)) {$ignore_cache = true;}
        if (!$ignore_cache) {
            // load from cache
            $cache = db_getMasternodeCache(time()-$cache_timeout);
            if (count($cache)<1){
                $load_from_daemon = true;
            } else {
                // proceed with cache
                $cache = $cache[0];
                if (isset($cache->list)){
                    foreach($cache->list as $mn){
                        $res[$mn->txhash."-".$mn->outidx] = $mn;
                    }
                } else {
                    $load_from_daemon = true;
                }
            }
        } else {
            $load_from_daemon = true;
        }

        // LOAD FROM DAEMON
        if ($load_from_daemon){
            try {
                if ($settings["list_masternodes_method"] == 'listmasternodes'){
                    $info = rpc_listmasternodes($address);
                    //var_dump($info);
                    if (count($info) > 0) {
                        foreach($info as $mnr){
                            $mn = new stdClass();

                            // "rank": 10,
                            $mn->rank = (isset($mnr->rank)) ? $mnr->rank : -1;
                            // "network": "ipv4",
                            $mn->network = $mnr->network;
                            // "txhash": "4e2393cd56e933c1829de994f5c3f5ae4e946a071ac0e6557cbc4d03b9e069d8",
                            $mn->txhash = $mnr->txhash;
                            // "outidx": 0,
                            $mn->outidx = $mnr->outidx;
                            // "status": "ENABLED",
                            $mn->status = $mnr->status;
                            // "addr": "<address>",
                            $mn->addr = $mnr->addr;
                            // "version": 70914,
                            $mn->version = $mnr->version;
                            // "lastseen": 1550050582,
                            $mn->lastseen = $mnr->lastseen;
                            // "activetime": 1094968,
                            $mn->activetime = $mnr->activetime;
                            // "tier": 1
                            if (isset($mnr->tier)) {$mn->tier = $mnr->tier;}
                            // "lastpaid": 1550050204
                            $mn->lastpaid = $mnr->lastpaid;
                            $res[$mn->txhash."-".$mn->outidx] = $mn;
                        }
                    }
                } else if ($settings["list_masternodes_method"] == 'masternode_list'){ //STD
                    $info = rpc_masternodelist($address);
                    //var_dump($info);
                    if (count($info) > 0) {
                        foreach($info as $mnr){
                            $mn = new stdClass();

                            // "rank": 10,
                            $mn->rank = $mnr->rank;
                            // "network": "ipv4",
                            $mn->network = $mnr->network;
                            // "txhash": "4e2393cd56e933c1829de994f5c3f5ae4e946a071ac0e6557cbc4d03b9e069d8",
                            $mn->txhash = $mnr->txhash;
                            // "outidx": 0,
                            $mn->outidx = $mnr->outidx;
                            // "status": "ENABLED",
                            $mn->status = $mnr->status;
                            // "addr": "<address>",
                            $mn->addr = $mnr->addr;
                            // "version": 70914,
                            $mn->version = $mnr->version;
                            // "lastseen": 1550050582,
                            $mn->lastseen = $mnr->lastseen;
                            // "activetime": 1094968,
                            $mn->activetime = $mnr->activetime;
                            // "lastpaid": 1550050204
                            $mn->lastpaid = $mnr->lastpaid;
                            $res[$mn->txhash."-".$mn->outidx] = $mn;
                        }
                    }
                } else if ($settings["list_masternodes_method"] == 'masternodelist') { //DIP2
                    $info = (array)rpc_masternodelist($address);
                    //var_dump($info);
                    if (count($info) > 0) {
                        foreach($info as $key=>$mnr){
                            $mn = new stdClass();

                            $ks = explode("-",$key);
                            // "rank": 10,
                            $mn->rank = "N/A";
                            // "network": "ipv4",
                            $mn->network = "ipv".getIpEndpointInfo($mnr->address)->version;;
                            // "txhash": "4e2393cd56e933c1829de994f5c3f5ae4e946a071ac0e6557cbc4d03b9e069d8",
                            $mn->txhash = $ks[0];
                            // "outidx": 0,
                            $mn->outidx = $ks[1];
                            // "status": "ENABLED",
                            $mn->status = $mnr->status;
                            // "addr": "<address>",
                            $mn->addr = $mnr->payee;
                            // "version": 70914,
                            $mn->version = "N/A";
                            // "lastseen": 1550050582,
                            $mn->lastseen = $mnr->lastpaidtime;
                            // "activetime": 1094968,
                            $mn->activetime = "N/A";
                            // "lastpaid": 1550050204
                            $mn->lastpaidtime = $mnr->lastpaidtime;
                            $mn->lastpaidblock = $mnr->lastpaidblock;
                            $mn->owneraddr = $mnr->owneraddress;
                            $mn->votingaddr = $mnr->votingaddress;
                            $mn->colladdr = $mnr->collateraladdress;
                            $mn->pubkeyoperator = $mnr->pubkeyoperator;

                            if (isset($mnr->type) && $mnr->type!="" && strtolower($mnr->type)!="regular") {
                                $mn->tier = $mnr->type;
                            }

                            $res[$key] = $mn;
                        }
                    }
                } else if ($settings["list_masternodes_method"] == 'smartnode_list_json') { //DIP2
                    $info = (array)rpc_smartnode_list_json($address);
                    //var_dump($info);
                    if (count($info) > 0) {
                        foreach($info as $key=>$mnr){
                            $mn = new stdClass();

                            $ks = explode("-",$key);
                            // "rank": 10,
                            $mn->rank = "N/A";
                            // "network": "ipv4",
                            $mn->network = "ipv".getIpEndpointInfo($mnr->address)->version;;
                            // "txhash": "4e2393cd56e933c1829de994f5c3f5ae4e946a071ac0e6557cbc4d03b9e069d8",
                            $mn->txhash = $ks[0];
                            // "outidx": 0,
                            $mn->outidx = $ks[1];
                            // "status": "ENABLED",
                            $mn->status = $mnr->status;
                            // "addr": "<address>",
                            $mn->addr = $mnr->payee;
                            // "version": 70914,
                            $mn->version = "N/A";
                            // "lastseen": 1550050582,
                            $mn->lastseen = $mnr->lastpaidtime;
                            // "activetime": 1094968,
                            $mn->activetime = "N/A";
                            // "lastpaid": 1550050204
                            $mn->lastpaidtime = ($mnr->lastpaidtime == 0) ? null : $mnr->lastpaidtime;
                            $mn->lastpaidblock = ($mnr->lastpaidblock == 0) ? null: $mnr->lastpaidtime;
                            $mn->owneraddr = $mnr->owneraddress;
                            $mn->votingaddr = $mnr->votingaddress;
                            $mn->colladdr = $mnr->collateraladdress;
                            $res[$key] = $mn;
                        }
                    }
                
                } else if ($settings["list_masternodes_method"] == 'masternode_list_full') { //PHC
                    $info = rpc_masternodelistfull($address);
                    if (isset($settings["list_masternodes_columnmap"])){
                        $cmap = $settings["list_masternodes_columnmap"];
                    } else {
                        $cmap = [
                            "status"        => 0,
                            "version"       => 1,
                            "addr"          => 2,
                            "lastpaid"      => 4,
                            "activetime"    => 5,
                            "lastseen"      => 6,

                        ];
                    }
                    foreach($info as $key=>$data){
                        $mn = new stdClass();
                        $ka = preg_split('/-/', $key,-1,PREG_SPLIT_NO_EMPTY);
                        $da = preg_split('/\s+/', $data,-1,PREG_SPLIT_NO_EMPTY);
                        $mn->rank = "N/A";
                        $mn->network = "ipv".getIpEndpointInfo($da[3])->version;
                        $mn->txhash     = $ka[0];
                        $mn->outidx     = $ka[1];
                        $mn->status     = $da[$cmap["status"]];
                        $mn->addr       = $da[$cmap["addr"]];
                        $mn->version    = $da[$cmap["version"]];
                        $mn->lastseen   = $da[$cmap["lastseen"]];
                        $mn->activetime = $da[$cmap["activetime"]];
                        $mn->lastpaid   = $da[$cmap["lastpaid"]];

                        $mn->lastseen = max($mn->lastseen, $mn->lastpaid);
                        $res[$mn->txhash."-".$mn->outidx] = $mn;

                    }
                } else if ($settings["list_masternodes_method"] == 'masternode_list-details') { //Nexalt
                    $info = rpc_masternodelistdetails($address);
                    $mdp = "masternode-details";
                    if (isset($info) && isset($info->$mdp)){
                        $md = $info->$mdp;
                        if (is_array($md)){
                            foreach ($md as $m){
                                $mn = new stdClass();
                                $mn->rank       = (isset($m->rank)) ? $m->rank: "N/A";
                                $mn->txhash     = (isset($m->vin)) ? $m->vin : "N/A" ;
                                $mn->outidx     = (isset($m->outidx)) ? $m->outidx : "?" ;
                                $mn->status     = "UNKNOWN";
                                if (isset($m->status)){
                                    switch ($m->status) {
                                        case 0:
                                            $mn->status = "EXPIRED";
                                            break;
                                        case 1:
                                            $mn->status = "ENABLED";
                                            break;
                                        default:
                                        $mn->status     = "UNKNOWN";
                                    }
                                }
                                $mn->addr       = (isset($m->pubkey)) ? $m->pubkey : "N/A" ;
                                $mn->version    = (isset($m->protocol)) ? $m->protocol : "N/A" ;
                                $mn->lastseen   = (isset($m->lastseen)) ? $m->lastseen : "N/A" ;
                                $mn->activetime = (isset($m->activeseconds)) ? $m->activeseconds : "N/A" ;
                                $mn->ip_addr    = (isset($m->address)) ? $m->address : "N/A" ;
                                $mn->network = (isset($m->address)) ? "ipv".getIpEndpointInfo($m->address)->version : "N/A";

                                if (!empty($address) && $mn->addr == $address){
                                    $res[$mn->txhash."-".$mn->outidx] = $mn;
                                } else if (empty($address)) {
                                    $res[$mn->txhash."-".$mn->outidx] = $mn; 
                                }
                                
                            }
                        }

                    }

                }
            } catch (Exception $e) {
                //$res->status = "OFFLINE";
                //$res->version = "not available";
                //$res->protocolversion = "not available";
                //$res->connections = "not available";
            }
        }
        return $res;
    }
    function getMasternodeList(){
        $mns = ui_getMasternodeStatus(""); 
        $result = array();
        foreach($mns as $key => $mn){
            $m = array();
            $tier_str = "";
            if (isset($mn->tier)) {
                $tier_str = "<span class='badge badge-info ml-2 upper'><i class='fab fa-hubspot'></i>&nbsp;".$mn->tier."</span>";
            }            
            if (in_array($mn->status,["ENABLED"])) {
            $badge_class="success";
            } else if (in_array($mn->status,["ACTIVE", "PRE-ENABLED", "WATCHDOG_EXPIRED"])) {
            $badge_class="warning";
            } else {
            $badge_class="danger";
            }
            array_push($m,"<a class='mnlist-address primary-text' href='../address/?address=$mn->addr'>$mn->addr</a>".$tier_str);
            array_push($m,"<h6><span class='mnlist-status badge badge-$badge_class'>$mn->status</span></h6>");
            array_push($m,"<span class='mnlist-version '>$mn->version</span>");
            array_push($m,"<span class='mnlist-lastseen'>".date('Y-m-d H:i:s', $mn->lastseen)."</span>");
            array_push($m,"<span class='mnlist-activetime'>".timediffToParts($mn->activetime)->string."</span>");
            array_push($result, $m);
        }
        //return json_encode($result);
        $rstr = json_encode($result);
        $res = strtr($rstr,"{}","[]");
        return $res;
    }
    function getMnSyncStatus(){
        $res = false;
        try {
            $mns = rpc_mnsyncstatus();
            if (isset($mns->IsMasternodeListSynced)) {
                $res = $mns->IsMasternodeListSynced;
            } else if(isset($mns->IsSynced)){
                $res = $mns->IsSynced;
            } else if (isset( $mns->RequestedMasternodeAssets)){
                if ($mns->RequestedMasternodeAssets >=998) { $res =true; } else { $res = false; }
            } else if (is_null($mns)){
                $res=null;
            }
        } catch (Exception $ex) {
            $res = null;
        }
        return $res;
    }
    function getBlockchainSyncStatus(){
        global $settings;
        $res = false;
        if ($settings["getchainsync_method"] == "getblockchaininfo"){
            $bcs = rpc_getblockchaininfo();
            if (!is_null($bcs)){
                if (isset($bcs->blocks) && isset($bcs->headers)){
                    if ($bcs->blocks > 0 && ((int)$bcs->headers - (int)$bcs->blocks) < (int)$settings["maturity"]){
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                return false;
            }
        }  else if ($settings["getchainsync_method"] == "mnsync"){
            try {
                $bcs = rpc_mnsyncstatus();
                if (!is_null($bcs)) {
                    if (isset($bcs->IsBlockchainSynced)){
                        $res = $bcs->IsBlockchainSynced;
                    } else {
                        $res = true;
                    }
                } else {
                    return false;
                }

            } catch (Exception $ex) {
                $res = false;
            }
            return $res;
        } else {
            return true;
        }
    }
    function getNetworkHashrate(){
        $res = new stdClass();
        try{
            $nhr = rpc_getnetworkhashps();
            if ($nhr >= 1000000000000000000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000000000000000000,3);
                $res->dimension = "Y";
            } else if ($nhr >= 1000000000000000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000000000000000,3);
                $res->dimension = "Z";
            } else if ($nhr >= 1000000000000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000000000000,3);
                $res->dimension = "E";
            } else if ($nhr >= 1000000000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000000000,3);
                $res->dimension = "P";
            } else if ($nhr >= 1000000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000000,3);
                $res->dimension = "T";
            } else if ($nhr >= 1000000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000000,3);
                $res->dimension = "G";
            } else if ($nhr >= 1000000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000000,3);
                $res->dimension = "M";
            } else if ($nhr >= 1000) {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr)/1000,3);
                $res->dimension = "k";
            } else {
                $res->hashrate = $nhr;
                $res->value = dflt(cdec($nhr),3);
                $res->dimension = "";
            }
        } catch (Exception $ex) {
            $res->hashrate = "---";
            $res->value = "err";
            $res->dimension = "";
        }
        return $res;
    }
    function getDifficulty(){
        $res = new stdClass();
        try{
            $nd = rpc_getdifficulty();
            if ($nd >= 1000000000000000000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000000000000000000,3);
                $res->dimension = "Y";
            } else if ($nd >= 1000000000000000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000000000000000,3);
                $res->dimension = "Z";
            } else if ($nd >= 1000000000000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000000000000,3);
                $res->dimension = "E";
            } else if ($nd >= 1000000000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000000000,3);
                $res->dimension = "P";
            } else if ($nd >= 1000000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000000,3);
                $res->dimension = "T";
            } else if ($nd >= 1000000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000000,3);
                $res->dimension = "G";
            } else if ($nd >= 1000000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000000,3);
                $res->dimension = "M";
            } else if ($nd >= 1000) {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd)/1000,3);
                $res->dimension = "k";
            } else {
                $res->difficulty = $nd;
                $res->value = dflt(cdec($nd),3);
                $res->dimension = "";
            }
        } catch (Exception $ex) {
            $res->difficulty = "---";
            $res->value = "err";
            $res->dimension = "";
        }
        return $res;
    }
    function getRpcBlockCount(){
        $res = -1;
        try {
            $res = rpc_getblockcount();
        } catch (Exception $ex) {
            $res = -1;
        }
        return $res;
    }
    function getPeersInfo(){
        $res = new stdClass();
        $res->outbound = array();
        $res->inbound  = array();
        $res->ipv6count = 0;
        $res->ipv4count = 0;
        $res->protocols = array();
        $res->clients = array();
        try {
            $r = rpc_getpeerinfo();
        } catch (Exception $e) {
            return $res;
        }
        if (count($r)>0){
            foreach($r as $info){
                $peer = new stdClass();
                $info = (array) $info;
                if (isset($info['addr']))           {
                    $ipe = getIpEndpointInfo($info['addr']);
                    $peer->ip          = $ipe->ip;
                    $peer->port        = $ipe->port;
                    $peer->ipver       = $ipe->version;
                } 
                else {
                    $peer->ip         = null;
                    $peer->port       = null;
                    $peer->ipver      = null;
                }
                if (isset($info['version']))        {$peer->protocol    = (int)$info['version'];}           else {$peer->protocol   = null;}
                if (isset($info['subver']))         {$peer->client      = trim((string)$info['subver'],'/');}         else {$peer->client     = null;}
                if (isset($info['pingtime']))       {$peer->pingtime    = (float)$info['pingtime'];}        else {$peer->pingtime   = null;}
                if (isset($info['synced_blocks']))  {$peer->blocks      = (int)$info['synced_blocks'];}     else {$peer->blocks     = null;}
                if (isset($info['synced_headers'])) {$peer->headers     = (int)$info['synced_headers'];}    else {$peer->headers    = null;}
                if (isset($info['inbound']))        {$peer->inbound     = (bool)$info['inbound'];}          else {$peer->inbound    = null;}
        
                if (!empty($peer->ip)){
                    $peer->ipver == 6 ? $res->ipv6count += 1 : $res->ipv4count += 1;
                    if ($peer->inbound){
                        array_push($res->inbound, $peer);
                    } else {
                        array_push($res->outbound, $peer);
                    }
                    if (array_key_exists($peer->protocol,$res->protocols)){
                        $res->protocols[$peer->protocol] +=1;
                    } else {
                        $res->protocols[$peer->protocol] = 1;
                    }
                    if (array_key_exists($peer->client,$res->clients)){
                        $res->clients[$peer->client] +=1;
                    } else {
                        $res->clients[$peer->client] = 1;
                    }

                }
            }
        }
        //var_dump($res);
        asort($res->protocols);
        asort($res->clients);
        return $res;
    }
    function getBlockHash($block_ix){
        $res = '';
        try {
            $res = rpc_getblockhash($block_ix);
        } catch (Exception $e){
        }
        return $res;
    }
  // MongoDB Requests
    function getTimeSinceLastExplorerBlock(){
        global $dbm;
        global $settings;

        $res = new stdClass();
        $last_block = db_getLastBlocks(1);
        if (count($last_block) > 0){
            (int)$tdif = (time() - $last_block[0]->block_time_sec);
            if ($tdif < 0) {$tdif=0;}
            $res = timediffToParts($tdif);

        } else {
            (int)$res->days =-1;
            (int)$res->hours=-1;
            (int)$res->minutes=-1;
            (int)$res->seconds =-1;
            (int)$res->diff = -1;
            $res->string = "---";
        }
        
        return $res;
    }    
    function getTimeSinceLastDaemonBlock(){
        global $dbm;
        global $settings;

        $res = new stdClass();
        $lbi = rpc_getblockcount();
        $lbh = rpc_getblockhash($lbi);
        $block_data = rpc_getblock($lbh);

        $block_time = isset($block_data->time) ? $block_data->time : null ;
        if (!is_null($block_time)){
            (int)$tdif = (time() - $block_time);
            if ($tdif < 0) {$tdif=0;}
            $res = timediffToParts($tdif);

        } else {
            (int)$res->days =-1;
            (int)$res->hours=-1;
            (int)$res->minutes=-1;
            (int)$res->seconds =-1;
            (int)$res->diff = -1;
            $res->string = "---";
        }
        
        return $res;
    } 
    function getAverageBlockSpacing($period_minutes, $to_timestamp){
        $res = new stdClass();
        $from_timestamp = $to_timestamp - $period_minutes*60;
        $blocks = db_countBlocksByTimeRange($from_timestamp, $to_timestamp);
        if($blocks>0){
            $avg_bs = ($to_timestamp - $from_timestamp)/$blocks;
            $res->totalSeconds = $avg_bs;
            $res->minutes = floor($avg_bs/60);
            if ($res->minutes ==0){
                $res->seconds = dflt($avg_bs,1);
                $res->string=fpp($res->seconds,1)."s";
            } else {
                $res->seconds = floor($avg_bs - $res->minutes * 60);
                $res->string=$res->minutes."m ".str_pad($res->seconds,2,'0', STR_PAD_LEFT)."s";
            }
        } else {
            $res->minutes=-1;
            $res->seconds=-1;
            $res->totalSeconds=-1;
            $res->n = -1;
            $res->string = "---";
        }
        return $res;
    }
    function getBlockRewards($period_minutes, $to_timestamp){
        $res = new stdClass();
        $from_timestamp = $to_timestamp - $period_minutes*60;
        $ba = db_getBlockRewardSumByTimeRange($from_timestamp,$to_timestamp);
        if (count($ba)>0){
            $res->totalFee      = dflt($ba[0]->totalFee);
            $res->totalStake    = dflt($ba[0]->totalStake);
            $res->totalReward   = dflt($ba[0]->totalReward);
            $res->totalMn       = dflt($ba[0]->totalMn);
            $res->totalDev      = dflt($ba[0]->totalDev);
            $res->totalDev      = dflt($ba[0]->totalPow);
            $res->blockCount    = dflt($ba[0]->blockCount);
        } else {
            $res->totalFee      = 0;
            $res->totalStake    = 0;
            $res->totalReward   = 0;
            $res->totalMn       = 0;
            $res->totalDev      = 0;
            $res->totalPow      = 0;
            $res->blockCount    = 1;

        }
        return $res;
    }
    function uiGetDbCoinSupply(){
        global $settings;
        $res = new stdClass();
        $cbs = 0;
        $pcbs = 0;
        $burned = 0;
        try {
            $cbs = db_getAddressData("Coinbase")[0]->sent;
        } catch (Exception $e) {
        }
        try {
            $pcbs = db_getAddressData("PoS Coinbase")[0]->sent;
        } catch (Exception $e) {
        }
        try {
            if (isset($settings['burn_addreses']) && count($settings['burn_addresses'])>0){
                foreach ($settings['burn_addresses'] as $ba){
                    $bb = db_getAddressData($ba);
                    if (count($bb) > 0){
                        $burned += $bb[0]->balance;
                    }
                }
            } else {
                $burned = db_getAddressData("Fee Collector")[0]->received;
            }
            
        } catch (Exception $e) {
        }
        $ms = $cbs+$pcbs;
        ($ms != 0) ? $res->moneysupply = fpp($ms) : $res->moneysupply = "not available";
        $cc = $cbs+$pcbs-$burned;
        ($cc != 0) ? $res->coincirculation = fpp($cc) : $res->coincirculation = "not available";
        $res->burned = fpp($burned);
        try {
            $zc = db_getAddressData("Zerocoin")[0];
            !isset($zc) ? $res->zerocoinsupply = "not available" : $res->zerocoinsupply = fpp($zc->balance);
        } catch (Exception $e) {
            $res->zerocoinsupply = "not available";
        }
        return $res;
    }
    function getTopMovesWithinMinutes($number = 10, $minutes, $up_to  = -1){
        if ($up_to < 0) { $up_to = time(); }
        $res = db_getTopAddressMovesByTimeRange($up_to-($minutes*60), $up_to, $number ,false,true,true);
        return $res;
    }
    function getAddressMovesList($address, $limit = 0, $skip = 0){
        $result = array();

        if(!empty($address)) {
            $rpc_bc = getRpcBlockCount();
            $ams = db_getAddressMovesDataByAddress($address, $limit, $skip); 
            foreach($ams as $am) {
                $m = array();
                $conf = $rpc_bc - $am->block_ix;
                if($conf < 0){$conf="---";}
                if ($am->amount<0) {
                    $am_class="danger";
                  } else if ($am->amount>0) {
                    $am_class="success";
                  } else {
                    $am_class="muted";
                  }
                  $amount_split = splitFloat($am->amount,8,8);
                  array_push($m,"<span class='txlist-timestamp '>".date('Y-m-d H:i:s', $am->move_time_sec)."</span>");
                  array_push($m,"<a class='txlist-txid primary-text' href='../tx/?txid=".$am->tx_id."'>".$am->tx_id."</a>");
                  array_push($m,"<a class='txlist-conf' href='../block/?block=".$am->block_ix."'>" .$conf."</a>");
                  array_push($m,"<span class='txlist-amount text-".$am_class."'>".$amount_split->whole."<small>.".$amount_split->fraction."</small></span>");
                  array_push($result, $m);
            }
        }
    
        //return json_encode($result);
        $rstr = json_encode($result);
        $res = strtr($rstr,"{}","[]");
        return $res;
    }
    function getLatestMarketsData(){
        $markets = new stdClass();
        (int)$markets->timestamp = 0;
        $markets->max_price = @cdec(0);
        $markets->avg_price = @cdec(0);
        $markets->min_price = @cdec(-1);
        $markets->volume_base = @cdec(0);
        $markets->volume_coin = @cdec(0);
        $markets->percent_change = @cdec(0);
        $markets->avg_percent_change = @cdec(0);
        $markets->metrics = array();
        $markets->base_prices = array();
        $markets->base_changes = array();
        $price_num = @cdec(0);
        $avg_pcent_num = @cdec(0);


        $db_mm = db_getLastMarketsMetric();
        if (count($db_mm)>0){
          $mm = $db_mm[0];
          $markets->timestamp = $mm->_id;
          if (count($mm->metrics)>0){
            foreach($mm->metrics as $metric){
                if (@cdec($markets->min_price)== -1){ $markets->min_price = @cdec($metric->latest);}
                if (@cdec($metric->latest) > ($markets->max_price) && $metric->base == 'BTC'){
                    $markets->max_price = @cdec($metric->latest);
                    $markets->percent_change = @cdec($metric->percent_change);
                }
                if (!is_null($metric->quote_volume)&&@cdec($metric->quote_volume)!=0){
                    $avg_pcent_num += (@cdec($metric->percent_change)/100)*@cdec($metric->quote_volume);
                }
                if (@cdec($metric->latest) < ($markets->min_price) && $metric->base == 'BTC'){
                    $markets->min_price = @cdec($metric->latest);
                }
                if (!is_null($metric->latest) && !is_null($metric->base_volume)) {
                    $price_num = $price_num + @cdec($metric->latest) * @cdec($metric->base_volume);
                    $markets->volume_base = $markets->volume_base + @cdec($metric->base_volume);
                    if (!is_null($metric->quote_volume)){
                        $markets->volume_coin = $markets->volume_coin + @cdec($metric->quote_volume);
                    }
                }
                array_push($markets->metrics,$metric);
            }
            try {
                $markets->avg_price = $markets->volume_base > 0 ? $price_num/$markets->volume_base : $markets->max_price;
                $markets->avg_percent_change = $markets->volume_coin > 0 ? 100*$avg_pcent_num/$markets->volume_coin : $markets->percent_change ;
            } catch (Exception $e){
                $markets->avg_price = null;
                $markets->avg_percent_change = null;
            }
          }
          if (count((array)$mm->base_prices)>0){
              foreach ($mm->base_prices as $cur=>$bp) {
                $markets->base_prices[$cur] = @cdec($bp);
              }
            //$markets->base_prices = $mm->base_prices;
          }
          if (count((array)$mm->base_changes)>0){
              foreach ($mm->base_changes as $cur=>$bc){
                $markets->base_changes[$cur] = @cdec($bc);
              }
            //$markets->base_changes = $mm->base_changes;
          }
        }
        return $markets;
    }
    function getTimeSinceTimestamp($timestamp){
        //global $dbm;
        //global $settings;

        $res = new stdClass();
        //$last_block = db_getLastBlocks(1);
        if (!empty($timestamp)){
            (int)$tdif = (time() - $timestamp);
            if ($tdif < 0) {$tdif=0;}
            $res = timediffToParts($tdif);

        } else {
            (int)$res->days =-1;
            (int)$res->hours=-1;
            (int)$res->minutes=-1;
            (int)$res->seconds =-1;
            (int)$res->diff = -1;
            $res->string = "---";
        }
        
        return $res;
    } 
    function getChainMetricsAggregates($end_time, $hours){
        //global $settings;
        $res = new stdClass();
        $res->timestamps = array();
        $res->masternodecount = array();
        $res->masternodecountenabled = array();
        $res->hashrate = array();
        $res->difficulty = array();

        $stime = milliseconds();
        $mm = db_getChainMetricsHourlyAggregates($end_time, $hours);
        $res->qtime = milliseconds()-$stime;
        $since_time = $end_time - $hours*3600 - ($end_time % 3600);

        for ($i=0; $i<$hours+1; $i++){
            $hour = $since_time + 3600*$i;
            $res->timestamps[$i] = date('M, d H:i',$hour);
            $res->masternodecount[$i] = NAN;
            $res->masternodecountenabled[$i] = NAN;
            $res->hashrate[$i] = NAN;
            $res->difficulty[$i] = NAN;
        }
        foreach($mm as $m){
            $i = array_search($m->_id->toDateTime()->format('M, d H:i'), $res->timestamps);
            $res->masternodecount[$i] = floor($m->avgMasternodeCount);
            $res->masternodecountenabled[$i] = floor($m->avgMasternodeCountEnabled);
            $res->hashrate[$i] = ($m->avgHashrate);
            $res->difficulty[$i] = ($m->avgDifficulty);

        }
        return $res;
    }
    function getChainMetricsAggregatesByHour($end_time, $hours){
        $res = new stdClass();
        $res->timestamps = array();
        $res->masternodecount = array();
        $res->masternodecountsum = array();
        $res->masternodecountn = array();
        $res->masternodecountenabled = array();
        $res->masternodecountenabledsum = array();
        $res->masternodecountenabledn = array();
        $res->hashrate = array();
        $res->hashratesum = array();
        $res->hashraten = array();
        $res->difficulty = array();
        $res->difficultysum = array();
        $res->difficultyn = array();
        $start_time = $end_time - $hours*3600 - ($end_time % 3600);
        $stime = milliseconds();
        $mm = db_getChainMetricsByTimeRange($start_time,$end_time);
        $res->qtime = milliseconds()-$stime;
        for ($i=0; $i<$hours+1; $i++){
            $hour = $start_time + 3600*$i;
            $res->timestamps[$i] = date('M, d H:i', $hour);
            $res->masternodecount[$i] = NAN;
            $res->masternodecountenabled[$i] = NAN;
            $res->hashrate[$i] = NAN;
            $res->difficulty[$i] = NAN;

            $res->masternodecountsum[$i] = null;
            $res->masternodecountenabledsum[$i] = null;
            $res->hashratesum[$i] = null;
            $res->difficultysum[$i] = null;

            $res->masternodecountn[$i] = 0;
            $res->masternodecountenabledn[$i] = 0; 
            $res->hashraten[$i] = 0; 
            $res->difficultyn[$i] = 0; 


        }
        foreach($mm as $m){
            $ts = $m->_id - ($m->_id % 3600);
            $i = array_search(date('M, d H:i',$ts), $res->timestamps);
            try {
                $res->masternodecountsum[$i] += $m->metrics->masternode_count->total;
                $res->masternodecountn[$i] += 1;
            } catch (Exception $e){}
            
            try {
                $res->masternodecountenabledsum[$i] += $m->metrics->masternode_count->enabled;  
                $res->masternodecountenabledn[$i] += 1;  
            } catch (Exception $e) {}

            try {
                $res->hashratesum[$i] += $m->metrics->hashrate;  
                $res->hashraten[$i] += 1;  
            } catch (Exception $e) {}
            
            try {
                $res->difficultysum[$i] += $m->metrics->difficulty;  
                $res->difficultyn[$i] += 1;  
            } catch (Exception $e) {}
        }
        for ($i=0; $i<$hours+1; $i++){
            if ($res->masternodecountsum[$i] != null){
                try{
                   $res->masternodecount[$i] = floor($res->masternodecountsum[$i]/$res->masternodecountn[$i]);
                } catch (Exception $e){}
            }
            if ($res->masternodecountsum[$i] != null){
                try{
                   $res->masternodecountenabled[$i] = floor($res->masternodecountenabledsum[$i]/$res->masternodecountenabledn[$i]);
                } catch (Exception $e){}
            }
            if ($res->hashratesum[$i] != null){
                try{
                   $res->hashrate[$i] = ($res->hashratesum[$i]/$res->hashraten[$i]);
                } catch (Exception $e){}
            }
            if ($res->difficultysum[$i] != null){
                try{
                   $res->difficulty[$i] = ($res->difficultysum[$i]/$res->difficultyn[$i]);
                } catch (Exception $e){}
            }
        }
        unset($res->masternodecountsum);
        unset($res->masternodecountn);
        unset($res->masternodecountenabledsum);
        unset($res->masternodecountenabledn);
        unset($res->hashratesum);
        unset($res->hashraten);
        unset($res->difficultysum);
        unset($res->difficultyn);

        return $res;
    }
    function getMarketPriceAggregatesByHour($end_time, $hours){
        $res = new stdClass();
        $res->timestamps = array();
        $res->markets = array();
        $res->vwap = array();
        $res->sum = array();
        $res->n = array();
        $start_time = $end_time - $hours*3600 - ($end_time % 3600);
        $stime = milliseconds();
        $mm = db_getMarketMetricsByTimeRange($start_time,$end_time);
        $res->qtime = milliseconds()-$stime;

        $vwap_sum = array();
        $vwap_n = array();
        for ($i=0; $i<$hours+1; $i++){
            $hour = $start_time + 3600*$i;
            $res->timestamps[$i] = date('M, d H:i', $hour);
        }
        $res->vwap = array_fill(0,$hours+1, NAN);
        $vwap_sum = array_fill(0,$hours+1, null);
        $vwap_n = array_fill(0,$hours+1, 0);
        foreach($mm as $m){
            $ts = $m->_id - ($m->_id % 3600);
            $i = array_search(date('M, d H:i',$ts), $res->timestamps);
            
            if (isset($m->vwap)){
                if (is_null($vwap_sum[$i])) { 
                    $vwap_sum[$i] = @cdec($m->vwap);
                    $vwap_n[$i] = 1; 
                } else {
                    $vwap_sum[$i] += @cdec($m->vwap);
                    $vwap_n[$i] += 1; 
                } 
            }

            foreach($m->metrics as $mx){
                if (!array_key_exists($mx->exchange,$res->markets)){
                    $res->markets[$mx->exchange] = array_fill(0,$hours+1, NAN);
                    $res->sum[$mx->exchange] = array_fill(0,$hours+1, null);
                    $res->n[$mx->exchange] = array_fill(0,$hours+1, 0);
                }
                if (is_null($res->sum[$mx->exchange][$i])){
                    $res->sum[$mx->exchange][$i] = @cdec($mx->latest);
                    $res->n[$mx->exchange][$i] += 1;
                } else {
                    if (!is_null(@cdec($mx->latest))) {
                        $res->sum[$mx->exchange][$i] = @cdec($res->sum[$mx->exchange][$i]) + @cdec($mx->latest);
                        $res->n[$mx->exchange][$i] += 1;
                    }
                }
            }
        }
        for ($i=0; $i<$hours+1; $i++){
            foreach ($res->markets as $exchange => $marray){
                if ($res->sum[$exchange][$i] != null){
                    $res->markets[$exchange][$i] = ($res->sum[$exchange][$i]/$res->n[$exchange][$i]);
                }
            }
            if (!is_null($vwap_sum[$i]) && $vwap_n[$i] != 0){
                $res->vwap[$i] = ($vwap_sum[$i]/$vwap_n[$i]);
            }
        }
        unset($res->sum);
        unset($res->n);
        return $res;
    }
    function ui_getCoinSupplyChartData(){
        global $settings;
        global $tt;
        $tt->createTimer("from_time",2);
        $from_time = db_getBlockByHeight(1)[0]->block_time_sec - (db_getBlockByHeight(1)[0]->block_time_sec % 3600);
        $tt->stopTimer("from_time");
        $tt->createTimer("to_time",2);
        $to_time = db_getBlockByHeight(db_getLastDbBlockNumber())[0]->block_time_sec;
        $tt->stopTimer("to_time");
        //echo "from {" . $from_time . "} => to {". $to_time . "}<br>";
        $tt->createTimer("db_getBlockRewardAggregatesDaily",2);
        $days = db_getBlockRewardAggregatesDaily($from_time, $to_time);
        $tt->stopTimer("db_getBlockRewardAggregatesDaily");
        //var_dump($days);
        $result = array();
        if (count($days)>0){
            for ($i=0; $i<count($days); $i++){
                $d = $days[$i];
                if (isset($settings['supply_chart_source']) && $settings['supply_chart_source'] == 'coincirculation'){
                    $result[$d->_id->toDateTime()->format('Y-m-d')] = dflt($d->coincirculation);
                } else {
                    $result[$d->_id->toDateTime()->format('Y-m-d')] = dflt($d->coinsupply);
                }
                
            }
        }
        return $result;
    }
    function ui_getAddressMovesChartData($address, $from_time, $to_time, $granularity_sec, $time_multiplier = 1000){
        $res = array();
        $data = db_getAddressMovesAggregatesByGranularity($address,$from_time,$to_time,$granularity_sec);
        if (count($data)>0){
            //create fer_timestamps
            $min_timestamp = $data[0]->_id - ($data[0]->_id % $granularity_sec);
            //$now = time();
            $max_timestamp = $to_time - ($to_time % $granularity_sec) + $granularity_sec; // $block_stats[count($block_stats)-1]->_id;
    
            // fill reference timestamps
            for ($ts = $min_timestamp; $ts <= $max_timestamp; $ts += $granularity_sec){
                $ts_ms = $ts*$time_multiplier;
                $res[$ts] = ["date" => $ts_ms];
                //$ref_timestamps[$ts_ms] = ["date" => date('Y-m-d', $ts)];
            }
            $bal = @cdec(0);
            foreach($data as $d){
                $ts = $d->_id;
                if (isset($d->movementsTotal)) {
                    $mov = @cdec($d->movementsTotal);
                    $res[$ts]["mov"] = $mov->toString();
                    $bal += $mov;
                    $res[$ts]["bal"] = ($bal > 0) ? $bal->toString() : 0;
                }
                if (isset($d->receivedTotal)) {
                    $recv = @cdec($d->receivedTotal);
                    $res[$ts]["recv"] = $recv->toString();
                }
                if (isset($d->sentTotal)) {
                    $sent = @cdec($d->sentTotal);
                    $res[$ts]["sent"] = (-$sent)->toString();
                }
            }
        }
        return $res;
    }
    function ui_getAddressMovesChartDataSip070($address, $from_time, $to_time, $granularity_sec, $time_multiplier = 1000){
        $res = array();
        $data = db_getAddressMovesAggregatesByGranularity($address,$from_time,$to_time,$granularity_sec);
        if (count($data)>0){
            //create fer_timestamps
            $min_timestamp = ($data[0]->_id - ($data[0]->_id % $granularity_sec));
            //$now = time();
            $max_timestamp = $to_time - ($to_time % $granularity_sec) + $granularity_sec; // $block_stats[count($block_stats)-1]->_id;
    
            $data_index = [];
            foreach($data as $ix=>$d){
                $data_index[$d->_id] = $ix;
            }

            $bal = @cdec(0);
            // fill reference timestamps
            for ($ts = $min_timestamp; $ts <= $max_timestamp; $ts += $granularity_sec){
                $ts_ms = $ts*$time_multiplier;
                $res[$ts] = ["date" => $ts_ms];
                if (isset($data_index[$ts])) {
                    $d = $data[$data_index[$ts]];
                    if (isset($d->movementsTotal)) {
                        $mov = @cdec($d->movementsTotal);
                        $res[$ts]["mov"] = $mov->toString();
                        $bal += $mov;
                        $res[$ts]["bal"] = ($bal > 0) ? $bal->toString() : 0;
                    }
                    if (isset($d->receivedTotal)) {
                        $recv = @cdec($d->receivedTotal);
                        if ($recv > 0 ){
                            $res[$ts]["recv"] = $recv->toString();
                        }
                    }
                    if (isset($d->sentTotal)) {
                        $sent = @cdec($d->sentTotal);
                        if ($sent > 0) {
                            $res[$ts]["sent"] = (-$sent)->toString();
                        }
                    }
                } else {
                    $res[$ts]["bal"] = ($bal > 0) ? $bal->toString() : 0;
                }
                //$ref_timestamps[$ts_ms] = ["date" => date('Y-m-d', $ts)];
            }
        }
        return $res;
    }
    function ui_getAddressMovesChartDataSip032($address, $from_time, $to_time, $granularity_sec, $time_multiplier = 1000){ //SIP-032
        $res = array();

        // count moves
        $amc = db_getAddressMovesCountByAddress($address);

        if ($amc < 1000){
            // go standard
            return ui_getAddressMovesChartData($address, $from_time, $to_time, $granularity_sec, $time_multiplier);
        } else {
            // get starting date
            $fm = db_getAddressMovesDataWithFilter(['address'=>$address],1,0,['move_time_sec'=>1]);

            //$data = db_getAddressMovesAggregatesByGranularity($address,$from_time,$to_time,$granularity_sec);
            $bal = @cdec(0);
            //create fer_timestamps
            $min_timestamp = $fm[0]->move_time_sec - ($fm[0]->move_time_sec % $granularity_sec);
            $max_timestamp = $to_time - ($to_time % $granularity_sec);//$block_stats[count($block_stats)-1]->_id;
        
                // fill reference timestamps
            for ($ts = $min_timestamp; $ts <= $max_timestamp; $ts += $granularity_sec){
                $ts_ms = $ts*$time_multiplier;
                $res[$ts] = ["date" => $ts_ms];
                $te = $ts + $granularity_sec;
                $data = db_getAddressMovesAggregatesByGranularity($address,$ts,$te,$granularity_sec,false);
                if (count($data)>0){
                    $d = $data[0];
                    if (isset($d->movementsTotal)) {
                        $mov = @cdec($d->movementsTotal);
                        $res[$ts]["mov"] = $mov->toString();
                        $bal += $mov;
                        $res[$ts]["bal"] = ($bal > 0) ? $bal->toString() : 0;
                    }
                    if (isset($d->receivedTotal)) {
                        $recv = @cdec($d->receivedTotal);
                        $res[$ts]["recv"] = $recv->toString();
                    }
                    if (isset($d->sentTotal)) {
                        $sent = @cdec($d->sentTotal);
                        $res[$ts]["sent"] = (-$sent)->toString();
                    }
                }
            }
            return $res;
        }
    }

    function ui_validateChainStats($chain_stats, $granularity_sec){
        $valid = true;
        $block_stats = ui_calculateBlockStats($chain_stats->_id, $granularity_sec);
        //echo date("Y-m-d",$chain_stats->_id).": ";

        if (!is_null($block_stats) && isset($chain_stats->periods)){
            // compare the marker metrics between provided and calculated
            if ($chain_stats->periods != $block_stats->periods) {$valid = false;}
            if ($chain_stats->blocksNumber != $block_stats->blocksNumber) {$valid = false;}
            if ($chain_stats->txNumber != $block_stats->txNumber) {$valid = false;}
            if ($chain_stats->blocksSizeTotal != $block_stats->blocksSizeTotal) {$valid = false;}
            if (@cdec($chain_stats->blockRewards) != @cdec($block_stats->blockRewards)) {$valid = false;}
            if (@cdec($chain_stats->movementsTotal) != @cdec($block_stats->movementsTotal)) {$valid = false;}
            if (@cdec($chain_stats->cdd) != @cdec($block_stats->cdd)) {$valid = false;}
            if (@cdec($chain_stats->coincirculation) != @cdec($block_stats->coincirculation)) {$valid = false;}
        } else {
            $valid = false;
        }
        
        //if ($valid) { echo "valid\n";} else { echo "invalid\n";}

        if (!$valid){
            global $min_time;
            // validate previous 
            $prev_timestamp = $chain_stats->_id - $granularity_sec;
            if ($prev_timestamp >= $min_time){
                $prev_stats = db_getChainStats($prev_timestamp);
                if (count($prev_stats)>0){
                    $prev_stats = $prev_stats[0];
                } else {
                    $prev_stats = (object)["_id"=>$prev_timestamp];
                }
                $result = ui_validateChainStats($prev_stats,$granularity_sec);
                //echo date("Y-m-d",$chain_stats->_id).": ";
                if (!is_null($block_stats)){
                    // calculate extensions
                    $block_stats = ui_calculateBlockStatsExtensions($block_stats,$granularity_sec);
                    // record $chain_stats
                    $result = db_updateChainStats($block_stats);
                    //echo "re-calculated\n";
                } else {
                    $result = db_deleteChainStats($chain_stats->_id);
                    //echo "deleted\n";
                }
                return true;
            } else {
                if (!is_null($block_stats)){
                    // calculate extensions
                    $block_stats = ui_calculateBlockStatsExtensions($block_stats,$granularity_sec);
                    // record $chain_stats
                    $result = db_updateChainStats($chain_stats);
                    return true;
                }
            }

        } else {
            return true;
        }

    }
    function ui_calculateBlockStats($timestamp, $granularity_sec){
        $block_stats = db_getBlockStatsAggregatesByGranularity($timestamp,$timestamp+$granularity_sec,$granularity_sec);
        if (count($block_stats)>0){
            return $block_stats[0];
        } else {
            return null;
        }
    }
    function ui_calculateBlockStatsExtensions($block_stats, $granularity_sec){
        $tx_types = db_getTxTypesAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($tx_types)>0) {
            foreach ($tx_types as $type){
                $block_stats->txTypes[$type->_id->t] = $type->n;
            }   
        }
 
        $tx_sizes = db_getTxSizesAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($tx_sizes)>0) {
            $block_stats->feeSubjectBytes = 0;
            foreach ($tx_sizes as $type){
                $block_stats->txSizes[$type->_id->t] = $type->n;
                if (!in_array($type->_id->t, ['nonstandard','reward','genesis'])){
                    $block_stats->feeSubjectBytes += $type->n;
                }
            }   
        }
 
        $vout_types = db_getVoutTypesAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($vout_types)>0) {
            foreach ($vout_types as $type){
                $block_stats->voutTypes[$type->_id->t] = $type->n;
            }   
        }
 
        $blk_versions = db_getBlockVersionsAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($blk_versions)>0) {
            foreach ($blk_versions as $ver){
                $block_stats->blockVersions[$ver->_id->v] = $ver->n;
            }   
        }
 
        $asset_types = db_getAssetTypesAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($asset_types)>0) {
            foreach ($asset_types as $type){
               $block_stats->assetTypes[$type->_id->t] = $type->n;
               if (array_key_exists("total",$block_stats->assetTypes)){$block_stats->assetTypes["total"]+=$type->n;}else{$block_stats->assetTypes["total"]=$type->n;}

               // SIP-048
               if (!in_array($type->_id->t,['regular_admin','subasset_admin'])){
                   if (array_key_exists("non-admin",$block_stats->assetTypes)){$block_stats->assetTypes["non-admin"]+=$type->n;}else{$block_stats->assetTypes["non-admin"]=$type->n;}
               }
               // eof SIP-048
            }   
        }
 
        $blk_types = db_getBlockTypesAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($blk_types)>0) {
            foreach ($blk_types as $type){
                $block_stats->blockTypes[$type->_id->v] = $type->n;
                if ($type->_id->v == "PoS" && $type->n != 0 && isset($block_stats->stakeBaseTotal)){
                    $block_stats->avgStakeBase = @cmdec(@cdec($block_stats->stakeBaseTotal)/$type->n);
                    $block_stats->avgStakeReward = @cmdec(@cdec($block_stats->stakeRewardsTotal)/$type->n);
                }
            }   
        }
 
        $chain_stats = db_getChainMetricsAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($chain_stats)>0) {
           $chain = $chain_stats[0];
           if (isset($chain->avgHashrate) && !is_null($chain->avgHashrate)) {$block_stats->avgHashrate = @cmdec(@cdec($chain->avgHashrate));}
           if (isset($chain->avgDifficulty) && !is_null($chain->avgDifficulty)) { $block_stats->avgDifficulty = @cmdec(@cdec($chain->avgDifficulty));}
           $block_stats->avgMasternodeCount = (isset($chain->avgMasternodeCount) && !is_null($chain->avgMasternodeCount)) ? @cdec($chain->avgMasternodeCount)->toInt() : 0;
           $block_stats->avgMasternodeCountEnabled = (isset($chain->avgMasternodeCountEnabled) && !is_null($chain->avgMasternodeCountEnabled)) ? @cdec($chain->avgMasternodeCountEnabled)->toInt() : 0;
           if ($block_stats->avgMasternodeCount != 0 && isset($block_stats->mnRewardsTotal)) {
               $block_stats->avgMnReward = @cmdec(@cdec($block_stats->mnRewardsTotal)/$block_stats->avgMasternodeCount);
           }
        }
 
        $markets = db_getMarketAggregatesByGranularity($block_stats->_id,$block_stats->_id+$granularity_sec, $granularity_sec);
        if (count($markets)>0){
            $market = $markets[0];
            if (isset($market->usd)) {$block_stats->btcUsd = $market->usd;}
            if (isset($market->vwap)) {$block_stats->vwapBtc = $market->vwap;}
            if (isset($market->vwap_usd)) {$block_stats->vwapUsd = $market->vwap_usd;}
        }
 
        return $block_stats;
    }

    function ui_calculateChainStatsOnDemand($from_time, $to_time, $granularity_sec){
        global $settings;
        $precision = (int)$settings["default_precision"];
        $res = new stdClass();

        // DEFAULTS //
            // $from_time = 0;//1514998800;
            // $to_time = time();//1515654000;
            // $granularity_sec = 86400;
            // $to_time = ($to_time - $to_time % $granularity_sec) + $granularity_sec;

            //$ref_timestamps = array();

            $res->blockStats_data = array();

            $res->txTypes_data = array();
            $res->txTypes = array();

            $res->voutTypes_data = array();
            $res->voutTypes = array();

            $res->blockVersions_data = array();
            $res->blockVersions = array();

            $res->blockTypes_data = array();
            $res->blockTypes = array();

            $res->moveTypes_data = array();
            $res->moveTypes = array();

            $res->assetTypes_data = array();
            $res->assetTypes = array();

            //$apt->createTimer("db_getBlockStatsAggregatesByGranularity",1);
            $block_stats = db_getBlockStatsAggregatesByGranularity($from_time,$to_time,$granularity_sec);
            //$apt->stopTimer("db_getBlockStatsAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getBlockStatsAggregatesByGranularity");

            //$apt->createTimer("db_getTxTypesAggregatesByGranularity",1);
            $tx_types = db_getTxTypesAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getTxTypesAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getTxTypesAggregatesByGranularity");

            //$apt->createTimer("db_getVoutTypesAggregatesByGranularity",1);
            $vout_types = db_getVoutTypesAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getVoutTypesAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getVoutTypesAggregatesByGranularity");

            //$apt->createTimer("db_getBlockVersionsAggregatesByGranularity",1);
            $blk_versions = db_getBlockVersionsAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getBlockVersionsAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getBlockVersionsAggregatesByGranularity");

            //$apt->createTimer("db_getMarketAggregatesByGranularity",1);
            $markets = db_getMarketAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getMarketAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getMarketAggregatesByGranularity");

            //$apt->createTimer("db_getChainMetricsAggregatesByGranularity",1);
            $chain_stats = db_getChainMetricsAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getChainMetricsAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getChainMetricsAggregatesByGranularity");

            //$apt->createTimer("db_getAssetTypesAggregatesByGranularity",1);
            $asset_types = db_getAssetTypesAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getAssetTypesAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getAssetTypesAggregatesByGranularity");

            //$apt->createTimer("db_getBlockTypesAggregatesByGranularity",1);
            $blk_types = db_getBlockTypesAggregatesByGranularity($from_time,$to_time, $granularity_sec);
            //$apt->stopTimer("db_getBlockTypesAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getBlockTypesAggregatesByGranularity");

            if ((count($block_stats)+count($chain_stats)+count($markets))>0){

                // process tx_types
                if (count($tx_types)>0){
                    foreach ($tx_types as $txType){
                        $ts_ms = $txType->_id->ts;
                        $type = $txType->_id->t;
                        $res->txTypes_data[$ts_ms]["date"] = $ts_ms*1000;
                        //if (array_key_exists($ts_ms,$txTypes_data)){
                            $res->txTypes_data[$ts_ms][$type] = $txType->n;
                            if (!in_array($type,$res->txTypes)){
                                array_push($res->txTypes, $type);
                            }
                            if (in_array($type,["new_asset","transfer_asset","reissue_asset"])){
                                $stype = "asset";
                                if (array_key_exists($stype,$res->txTypes_data[$ts_ms])){
                                    $res->txTypes_data[$ts_ms][$stype] += $txType->n;
                                } else {
                                    $res->txTypes_data[$ts_ms][$stype] = $txType->n;
                                }
                                if (!in_array($stype,$res->txTypes)){
                                    array_push($res->txTypes, $stype);
                                }
                            }
                        //}
                    }
                }

                foreach ($block_stats as $stats){
                    $ts_ms = $stats->_id;
                    $res->blockStats_data[$ts_ms]["date"] = $ts_ms * 1000;
                        $measuredPeriod = (isset($stats->periods)) ? ($stats->periods*3600) : $granularity_sec;
                        if (isset($stats->txNumber)) {$res->blockStats_data[$ts_ms]["txps"] = (@cdec($stats->txNumber)/$measuredPeriod)->toFixed(3);}
                        $rtxNumber = $stats->txNumber - ( (isset($res->txTypes_data[$ts_ms]["reward"])) ? $res->txTypes_data[$ts_ms]["reward"] : 0 ) - ( (isset($res->txTypes_data[$ts_ms]["nonstandard"])) ? $res->txTypes_data[$ts_ms]["nonstandard"] : 0 );
                        $res->blockStats_data[$ts_ms]["rtxps"] = (@cdec($rtxNumber)/$measuredPeriod)->toFixed(3);

                        if (isset($stats->addressCount) && $stats->addressCount>=0) {$res->blockStats_data[$ts_ms]["acnt"] = $stats->addressCount;}
                        if (isset($stats->groupCount) && $stats->groupCount>=0) {$res->blockStats_data[$ts_ms]["gcnt"] = $stats->groupCount;}
                        if (isset($stats->blocksNumber) && isset($stats->blocksSizeTotal) && $stats->blocksNumber>0) {$res->blockStats_data[$ts_ms]["avgbs"] = (@cdec($stats->blocksSizeTotal)/@cdec($stats->blocksNumber))->toFixed(0);}
                        if (isset($stats->cdd)) {$cdd = @cdec($stats->cdd); if ( $cdd->round(0) > 0 ){$res->blockStats_data[$ts_ms]["cdd"] = $cdd->toFixed(0);}}
                        if (isset($stats->cdd) && isset($stats->coincirculation)) {$cc = @cdec($stats->coincirculation); 
                            if ($cc!=0) {
                                $res->blockStats_data[$ts_ms]["ccirc"] = $cc->toFixed(0);
                                $acdd = @cdec($stats->cdd)/$cc;  
                                if ($acdd->round(3)>0){
                                    $res->blockStats_data[$ts_ms]["acdd"] = (@cdec($stats->cdd)/$cc)->toFixed(3);
                                }
                            }
                        }
                        if (isset($stats->burned) ) {$res->blockStats_data[$ts_ms]["cburn"] = (@cdec($stats->burned))->toFixed(0);} 
                        if (isset($stats->coinsupply) ) {$res->blockStats_data[$ts_ms]["csupl"] = (@cdec($stats->coinsupply))->toFixed(0);} 

                        if (isset($stats->movementsTotal)){
                            $move = @cdec($stats->movementsTotal);
                            
                            if ($move > 0){
                                if (!array_key_exists(99,$res->moveTypes)){ $res->moveTypes[99] = ["name"=>"Transfer", "field"=>"rtx"];}
                                if (isset($stats->powRewardsTotal)) {
                                    $pow = @cdec($stats->powRewardsTotal);
                                    if ($pow > 0) {
                                        if (!array_key_exists(0,$res->moveTypes)){ $res->moveTypes[0] = ["name"=>"PoW Rewards", "field"=>"pow"];}
                                        $res->blockStats_data[$ts_ms]["pow"] = $pow->toFixed(0);
                                    }
                                }
                                if (isset($stats->stakeRewardsTotal)) {
                                    $pos = @cdec($stats->stakeRewardsTotal);
                                    if ($pos > 0){
                                        if (!array_key_exists(1,$res->moveTypes)){ $res->moveTypes[1] = ["name"=>"PoS Rewards", "field"=>"pos"];}
                                        $res->blockStats_data[$ts_ms]["pos"] = $pos->toFixed(0);
                                    }
                                }
                                if (isset($stats->mnRewardsTotal)) {
                                    $mn = @cdec($stats->mnRewardsTotal);
                                    if ($mn > 0) {
                                        if (!array_key_exists(2,$res->moveTypes)){ $res->moveTypes[2] = ["name"=>"MN Rewards", "field"=>"mn"];}
                                        $res->blockStats_data[$ts_ms]["mn"] = $mn->toFixed(0);
                                    }
                                }
                                if (isset($stats->devRewardsTotal)) {
                                    $dev = @cdec($stats->devRewardsTotal);
                                    if ($dev > 0){
                                        if (!array_key_exists(3,$res->moveTypes)){ $res->moveTypes[3] = ["name"=>"Dev Rewards", "field"=>"dev"];}
                                        $res->blockStats_data[$ts_ms]["dev"] = $dev->toFixed(0);
                                    }
                                }
                                $res->blockStats_data[$ts_ms]["rtx"] = @cdec($stats->movementsRegularTotal)->toFixed(0);
                                if (isset($stats->feeTotal) && isset($stats->feeSubjectBytes) && $stats->feeSubjectBytes>0){
                                    $res->blockStats_data[$ts_ms]["fpkb"] = ((@cdec($stats->feeTotal))/@cdec($stats->feeSubjectBytes))->toFixed($precision);
                                }
                            }
                        }
                        //$res->blockStats_data[$ts_ms]["astcnt"] = (isset($stats->assetCount) && $stats->assetCount>=0) ? $stats->assetCount/2 : 0;
                        if (isset($stats->stakeBaseTotal)) {$res->blockStats_data[$ts_ms]["sbsum"] = (@cdec($stats->stakeBaseTotal))->toString();} 
                        if (isset($stats->stakeRewardsTotal)) {$res->blockStats_data[$ts_ms]["srsum"] = (@cdec($stats->stakeRewardsTotal))->toString();} 
                }



                // process vout_types
                if (count($vout_types)>0){
                    //$res->voutTypes_data = $ref_timestamps;
                    foreach ($vout_types as $voutType){
                        $ts_ms = $voutType->_id->ts;
                        $type = $voutType->_id->t;
                        $res->voutTypes_data[$ts_ms]["date"] = $ts_ms*1000;
                        //if (array_key_exists($ts_ms,$voutTypes_data)){
                            $res->voutTypes_data[$ts_ms][$type] = $voutType->n;
                            if (!in_array($type,$res->voutTypes)){
                                array_push($res->voutTypes, $type);
                            }
                        //}
                    }
                }

                // process blk_versions
                if (count($blk_versions)>0){
                    //$blockVersions_data = $ref_timestamps;
                    foreach ($blk_versions as $blkVer){
                        $ts_ms = $blkVer->_id->ts;
                        $ver = $blkVer->_id->v;
                        $res->blockVersions_data[$ts_ms]["date"] = $ts_ms*1000;
                        //if (array_key_exists($ts_ms,$res->blockVersions_data)){
                            $res->blockVersions_data[$ts_ms][$ver] = $blkVer->n;
                            if (!in_array($ver,$res->blockVersions)){
                                array_push($res->blockVersions, $ver);
                            }
                        //}
                    }
                }

                // process blk_types
                if (count($blk_types)>0){
                    //$blockTypes_data = $ref_timestamps;
                    foreach ($blk_types as $blkType){
                        $ts_ms = $blkType->_id->ts;
                        $type = $blkType->_id->v;
                        $res->blockTypes_data[$ts_ms]["date"] = $ts_ms*1000;
                        //if (array_key_exists($ts_ms,$res->blockTypes_data)){
                            $res->blockTypes_data[$ts_ms][$type] = $blkType->n;
                            if (!in_array($type,$res->blockTypes)){
                                array_push($res->blockTypes, $type);
                            }
                        //}
                        if ($type == "PoS" && $blkType->n != 0 && isset($res->blockStats_data[$ts_ms]["sbsum"])){
                            $res->blockTypes_data[$ts_ms]["avgsb"] = (@cdec($res->blockStats_data[$ts_ms]["sbsum"])/$blkType->n)->toString();
                            $res->blockTypes_data[$ts_ms]["avgsr"] = (@cdec($res->blockStats_data[$ts_ms]["srsum"])/$blkType->n)->toString();
                        }
                    }
                }

                // process markets
                if (count($markets)>0){
                    foreach ($markets as $market){
                        $ts_ms = $market->_id;
                        $res->blockStats_data[$ts_ms]["date"] = $ts_ms * 1000;
                        //if (array_key_exists($ts_ms,$res->blockStats_data)){
                            if (isset($market->usd)) {$res->blockStats_data[$ts_ms]["btc_usd"] = @cdec($market->usd)->toFixed(2);}
                            if (isset($market->vwap)) {$res->blockStats_data[$ts_ms]["vwap_btc"] = @cdec($market->vwap)->toFixed($precision);}
                            if (isset($market->vwap_usd)) {$res->blockStats_data[$ts_ms]["vwap_usd"] = @cdec($market->vwap_usd)->toFixed(4);}
                        //}
                    }
                }

                // process chain stats
                if (count($chain_stats)>0){
                    foreach ($chain_stats as $chain){
                        $ts_ms = (int)$chain->_id;
                        $res->blockStats_data[$ts_ms]["date"] = $ts_ms * 1000;
                        //if (array_key_exists($ts_ms,$res->blockStats_data)){
                            if (isset($chain->avgHashrate) && !is_null($chain->avgHashrate)) {$res->blockStats_data[$ts_ms]["powhr"] = @cdec($chain->avgHashrate)->toFixed(0);}
                            if (isset($chain->avgDifficulty) && !is_null($chain->avgDifficulty)) { $res->blockStats_data[$ts_ms]["powdif"] = @cdec($chain->avgDifficulty)->toFixed(3);}
                            $res->blockStats_data[$ts_ms]["mnttl"] = (isset($chain->avgMasternodeCount) && !is_null($chain->avgMasternodeCount)) ? @cdec($chain->avgMasternodeCount)->toInt() : 0;
                            $res->blockStats_data[$ts_ms]["mnena"] = (isset($chain->avgMasternodeCountEnabled) && !is_null($chain->avgMasternodeCountEnabled)) ? @cdec($chain->avgMasternodeCountEnabled)->toFixed(0) : 0;
                            if ($res->blockStats_data[$ts_ms]["mnttl"] != 0 && isset($res->blockStats_data[$ts_ms]["mn"])) {$res->blockStats_data[$ts_ms]["avgmnr"] = (@cdec($res->blockStats_data[$ts_ms]["mn"])/$res->blockStats_data[$ts_ms]["mnttl"])->toString();}
                        //}
                    }
                }

                // process asset_types
                if (count($asset_types)>0){
                    //$res->assetTypes_data = $ref_timestamps;
                    foreach ($asset_types as $assetType){
                        $ts_ms = $assetType->_id->ts;
                        $type = $assetType->_id->t;
                        $res->assetTypes_data[$ts_ms]["date"] = $ts_ms*1000;
                        //if (array_key_exists($ts_ms,$assetTypes_data)){
                            $res->assetTypes_data[$ts_ms][$type] = $assetType->n;
                            if (array_key_exists("total",$res->assetTypes_data[$ts_ms])){
                                $res->assetTypes_data[$ts_ms]["total"]+=$assetType->n;
                            }else{
                                $res->assetTypes_data[$ts_ms]["total"]=$assetType->n;
                            }
                            if (!in_array($type,$res->assetTypes)){
                                array_push($res->assetTypes, $type);
                            }
                            // SIP-048
                            if (!in_array($type,['regular_admin','subasset_admin'])){
                                if (array_key_exists("non-admin",$res->assetTypes_data[$ts_ms])){$res->assetTypes_data[$ts_ms]["non-admin"]+=$assetType->n;}else{$res->assetTypes_data[$ts_ms]["non-admin"]=$assetType->n;}
                            }
                            // eof SIP-048
                        //}
                    }
                }
            }
            //$apt->stopTimer("preprocessing");
        // EOF DEFAULT


        return $res;
    }

    function ui_calculateCoinCirculationOnDemand($from_time, $to_time, $granularity_sec){
        global $settings;
        $precision = (int)$settings["default_precision"];
        $res = new stdClass();

            $res->blockStats_data = array();

            //$apt->createTimer("db_getBlockStatsAggregatesByGranularity",1);
            $block_stats = db_getBlockStatsAggregatesByGranularity($from_time,$to_time,$granularity_sec);

            //$apt->stopTimer("db_getBlockStatsAggregatesByGranularity");
            //$z = $apt->getDurationSeconds("db_getBlockStatsAggregatesByGranularity");

            if (count($block_stats)>0){

                foreach ($block_stats as $stats){
                    $ts_ms = $stats->_id;
                    $res->blockStats_data[$ts_ms]["date"] = $ts_ms * 1000;
                    $measuredPeriod = (isset($stats->periods)) ? ($stats->periods*3600) : $granularity_sec;
                    if (isset($stats->coincirculation)) {
                        $res->blockStats_data[$ts_ms]["ccirc"] = @cdec($stats->coincirculation)->toFixed(0);
                    }
                        if (isset($stats->burned) ) {$res->blockStats_data[$ts_ms]["cburn"] = (@cdec($stats->burned))->toFixed(0);} 
                        if (isset($stats->coinsupply) ) {$res->blockStats_data[$ts_ms]["csupl"] = (@cdec($stats->coinsupply))->toFixed(0);} 
                }
            }
            //$apt->stopTimer("preprocessing");
        // EOF DEFAULT

        return $res;
    }
    function ui_calculateCoinCirculationChartData($apt){
        global $settings;
        $res = new stdClass();
        $apt->createTimer("db_getLastBlocks(1,0)",2);
        $last_block = db_getLastBlocks(1,0);
        $apt->stopTimer("db_getLastBlocks(1,0)");
        $apt->createTimer("db_getBlockByHeight(0)",2);
        $genesis = db_getBlockByHeight(0);
        $apt->stopTimer("db_getBlockByHeight(0)");

        if (count($last_block)>0 && count($genesis)>0){

            $to_time = time();//1515654000;
            $granularity_sec = 86400;

            $min_timestamp = ($genesis[0]->block_time_sec - $genesis[0]->block_time_sec % $granularity_sec);
            $max_timestamp = ($to_time - $to_time % $granularity_sec) + $granularity_sec;
            $last_timestamp = $last_block[0]->block_time_sec - $last_block[0]->block_time_sec % $granularity_sec; 
            // create ref timestamps from genesis block time
            $ref_timestamps = array();

            // fill reference timestamps
            $apt->createTimer("fill_ref_timestamps",2);
            for ($ts = $min_timestamp; $ts < $max_timestamp; $ts += $granularity_sec){
                $ts_ms = $ts*1000;
                $ref_timestamps[$ts] = ["date" => $ts_ms];
            }
            ksort($ref_timestamps);
            $apt->stopTimer("fill_ref_timestamps");

            if (isset($settings["calculate_chain_stats"]) && $settings["calculate_chain_stats"]){    
                // load from chainstats
                $apt->createTimer("db_getChainStatsMany",2);
                $chain_stats = db_getChainStatsMany($min_timestamp,$max_timestamp);
                $apt->stopTimer("db_getChainStatsMany");

                if (count($chain_stats)>0){
                    $apt->createTimer("process_chain_stats",2);
                    // populate timestamps
                    $res->blockStats_data       = $ref_timestamps;

                    // iterate through chain_stats
                    foreach ($chain_stats as $cs){

                        if (isset($cs->coincirculation)) {
                            $cs->coincirculation = @cdec($cs->coincirculation);
                            $res->blockStats_data[$cs->_id]["ccirc"] = $cs->coincirculation->toFixed(0);
                        }
                        if (isset($cs->burned) ) {$res->blockStats_data[$cs->_id]["cburn"] = (@cdec($cs->burned))->toFixed(0);} 
                        if (isset($cs->coinsupply) ) {$res->blockStats_data[$cs->_id]["csupl"] = (@cdec($cs->coinsupply))->toFixed(0);} 
                    }
                    $apt->stopTimer("process_chain_stats");

                    // check if data is complete
                    if ($chain_stats[count($chain_stats)-1]->_id != $max_timestamp-$granularity_sec){
                        // calculate remaining on-demand
                        $apt->createTimer("calculate_remaining_on-demand",2);
                        $odres = ui_calculateCoinCirculationOnDemand($chain_stats[count($chain_stats)-1]->_id+$granularity_sec,$max_timestamp,$granularity_sec);

                        // merge blockStats_data data
                        foreach ($odres->blockStats_data as $ts=>$data){
                            //$data["date"]=$ts*1000;
                            $res->blockStats_data[$ts] = $data;
                        }

                        $apt->stopTimer("calculate_remaining_on-demand");
                    }    
                    
                    
                    
                } else {
                    // chain stats, need to calc on-demand
                    $apt->createTimer("no_chainstats_calculate_all_on-demand",2);
                    $odres = ui_calculateCoinCirculationOnDemand($min_timestamp,$max_timestamp,$granularity_sec);

                    // merge blockStats_data data
                    foreach ($odres->blockStats_data as $ts=>$data){
                        //$data["date"]=$ts*1000;
                        $res->blockStats_data[$ts] = $data;
                    }

                    //calculateAllOnDemand($ref_timestamps,$min_timestamp,$max_timestamp,$granularity_sec);
                    $apt->stopTimer("no_chainstats_calculate_all_on-demand");
                }
            } else {
                // calculate all on-demand
                $apt->createTimer("calculate_all_on-demand",2);

                $odres = ui_calculateCoinCirculationOnDemand($min_timestamp,$max_timestamp,$granularity_sec);

                // merge blockStats_data data
                foreach ($odres->blockStats_data as $ts=>$data){
                    //$data["date"]=$ts*1000;
                    $res->blockStats_data[$ts] = $data;
                }

                //calculateAllOnDemand($ref_timestamps,$min_timestamp,$max_timestamp,$granularity_sec);
                $apt->stopTimer("calculate_all_on-demand");

            }

            // arrange data
            $apt->createTimer("arrange_data",2);

            ksort($res->blockStats_data);
            $res->blockStats_data       = array_values($res->blockStats_data);

            $apt->stopTimer("arrange_data");
            $z = $apt->getDurationSeconds("arrange_data");

        }

        $res->apt = $apt;

        return $res;
    }


  // Host Requests
    function getOsVersion(){
        if (false == function_exists("shell_exec") || false == is_readable("/etc/os-release")) {
            return null;
        }

        $os = shell_exec('cat /etc/os-release');
        $listIds    = preg_match_all('/.*=/', $os, $matchListIds);
        $listIds    = $matchListIds[0];

        $listVal    = preg_match_all('/=.*/', $os, $matchListVal);
        $listVal    = $matchListVal[0];

        array_walk($listIds, function(&$v, $k){
            $v = strtolower(str_replace('=', '', $v));
        });

        array_walk($listVal, function(&$v, $k){
            $v = preg_replace('/=|"/', '', $v);
        });

        return array_combine($listIds, $listVal);

    }
    function getCpuLoad($interval_sec){
        
        $load = new stdClass();
        $stat1 = file('/proc/stat'); 
        usleep(floor($interval_sec*1000000));
        $stat2 = file('/proc/stat'); 
        $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0])); 
        $info2 = explode(" ", preg_replace("!cpu +!", "", $stat2[0])); 
        $dif = array(); 
        $dif['user'] = $info2[0] - $info1[0]; 
        $dif['nice'] = $info2[1] - $info1[1]; 
        $dif['sys'] = $info2[2] - $info1[2]; 
        $dif['idle'] = $info2[3] - $info1[3]; 
        $total = array_sum($dif); 
        $load->user = 100*$dif['user']/$total;
        $load->nice = 100*$dif['nice']/$total;
        $load->system = 100*$dif['sys']/$total;
        $load->idle = 100*$dif['idle']/$total;
        return $load;
    }
    function getHostAddress(){
        global $settings;
        if (!empty($settings['explorer_address'])){
            return $settings['explorer_address'];
        } else {
            $protocol   = '';
            $address    = '';

            $protocol = $_SERVER['REQUEST_SCHEME'];
            if (array_key_exists('HTTP_X_FORWARDED_PROTO',$_SERVER)){
                $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
            }


            $possibleHostSources = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
            $sourceTransformations = array(
                "HTTP_X_FORWARDED_HOST" => function($value) {
                    $elements = explode(',', $value);
                    return trim(end($elements));
                }
            );
            foreach ($possibleHostSources as $source)
            {
                if (!empty($address)) break;
                if (empty($_SERVER[$source])) continue;
                $address = $_SERVER[$source];
                if (array_key_exists($source, $sourceTransformations))
                {
                    $address = $sourceTransformations[$source]($address);
                } 
            }

            // Remove port number from host
            // $address = preg_replace('/:\d+$/', '', $address);
            return trim($protocol.'://'.$address);
        }
    }
    // 

  // --- LOGGING --- 
    function ui_LogPageEvent($ip, $page, $duration, $args = array()){
        $event = new stdClass();
        $event->timestamp = time();
        $event->ip = $ip;
        $event->page = $page;
        $event->duration = $duration;
        $event->args = log_args($args);
        $event->user_agent = ui_GetUserAgent();
        $event->browser = ui_GetBrowser();

        // Usage logging must never take a page down when a caller lacks the DB layer.
        if (function_exists('db_recordPageLog')) {
            db_recordPageLog($event);
        }

    }

  // CACHING
    function ui_CreateTopAddressesCache(){
        global $settings;
        $precision = (int)$settings["overview_precision"];
        $ut = new TimeTracker();
        $ut->createTimer("total",0);
        $cache = new stdClass();
        $cache->t = milliseconds();
        $cache->last_block = new stdClass();
        $cache->last_block->number = -1;
        $cache->last_block->hash = "";

        // last db block number and hash
        $ut->createTimer("db_getLastBlocks(1)",1);
        $db_block = db_getLastBlocks(1);
        $ut->stopTimer("db_getLastBlocks(1)",null);
        if (count($db_block) > 0){
               
            // only can create cache if any block processed
            $cache->last_block->number = $db_block[0]->block_ix;
            $cache->last_block->hash = $db_block[0]->_id;

            // last RPC block - not needed at creating the cache
            // db coin supply
            $db_supply = db_getDbCoinSupply(); 
            $cache->coin_supply = new stdClass();
            $cache->coin_supply->circulation = splitFloat(@cmdec($db_supply->coincirculation),$precision,$precision);
            $cache->coin_supply->pow_supply = splitFloat(@cmdec($db_supply->pow_supply), $precision, $precision);
            $cache->coin_supply->pos_supply = splitFloat(@cmdec($db_supply->pos_supply), $precision, $precision);
            $cache->coin_supply->illegal_supply = splitFloat(@cmdec($db_supply->illegal_supply), $precision, $precision);
            $cache->coin_supply->burned = splitFloat(@cmdec($db_supply->burned), $precision, $precision);
            $cache->coin_supply->zerocoin_supply = splitFloat(@cmdec($db_supply->zerocoinsupply), $precision, $precision);
            $cache->coin_supply->shield_supply = splitFloat(@cmdec($db_supply->shieldsupply), $precision, $precision);
            $cache->coin_supply->credit_pool_supply = splitFloat(@cmdec($db_supply->creditpoolsupply), $precision, $precision);
            if (isset($db_supply->airdrop_supply)){
                $cache->coin_supply->airdrop_supply = splitFloat(@cmdec($db_supply->airdrop_supply), $precision, $precision);
            }

            // masternode count
            // to calculate locked coins
            if (array_key_exists("masternode_collateral",$settings)) {
              $masternode_locked_coins = @cdec(0);
              try{
                $masternode_collateral =  getCollateralAmount(); // 2.0.167
              } catch (Exception $e) {
                $masternode_collateral = @cdec(0);
              }

              // tiered masternodes
              if (is_array($masternode_collateral)){
                $masternode_count = ui_getMasternodeCount();
                if (isset($masternode_count->detailed)){
                  foreach ($masternode_count->detailed as $tier => $count){
                    if (isset($count->total)){
                      $tier_collateral = isset($masternode_collateral[$tier])?$masternode_collateral[$tier]:$masternode_collateral["regular"];
                      $tier_count = $count->total;
                      $masternode_locked_coins = $masternode_locked_coins + @cdec($tier_count * $tier_collateral);
                    }
                  }
                }
              } else {
                // single collateral
                $masternodes_total = ui_getMasternodeCount()->total;
                $rpc_masternode_count = (is_numeric($masternodes_total))?intval($masternodes_total):0;
                $masternode_locked_coins = @cdec($rpc_masternode_count * $masternode_collateral);
              }

              $cache->coin_supply->masternode_locked_coins = splitFloat(@cmdec($masternode_locked_coins),$precision, $precision);
              if (@cdec($cache->coin_supply->circulation->number) > 0 && $masternode_locked_coins > 0){ 
                $cache->coin_supply->masternode_locked_percent = splitFloat(@cmdec(@cdec($cache->coin_supply->masternode_locked_coins->number)/@cdec($cache->coin_supply->circulation->number)*100),$precision, $precision);
              } else {
                $cache->coin_supply->masternode_locked_percent = splitFloat(@cmdec(0),$precision, $precision);
              }
            }
             

            // top 100 addresses
            $ut->createTimer("db_getAddresesWithTopBalance(100,0,f,t)",1);
            $top_addresses = db_getAddresesWithTopBalance(100,0,false,true);
            $ut->stopTimer("db_getAddresesWithTopBalance(100,0,f,t)");

            $ut->createTimer("calculateTopAddressesDistribution",1);
            $cache->top_addresses = array();
            $n = 0;
            $cache->distribution = new stdClass();
            $cache->distribution->q1 = new stdClass();
            $cache->distribution->q1->amount = @cdec(0);
            $cache->distribution->q1->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q2 = new stdClass();
            $cache->distribution->q2->amount = @cdec(0);
            $cache->distribution->q2->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q3 = new stdClass();
            $cache->distribution->q3->amount = @cdec(0);
            $cache->distribution->q3->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q4 = new stdClass();
            $cache->distribution->q4->amount = @cdec(0);
            $cache->distribution->q4->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->other = new stdClass();
            $cache->distribution->other->amount = @cdec(0);
            $cache->distribution->other->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q_total = new stdClass();
            $cache->distribution->q_total->amount = @cdec(0);
            $cache->distribution->q_total->percent = splitFloat(0, $precision, $precision);

            $ut->createTimer("db_getAddressGroupTags",2);
            foreach ($top_addresses as $addr){
                $rank = $n+1;
                $address = new stdClass();
                $address->rank = $rank;
                $address->addr = $addr->_id;
                $address->alias = (isset($addr->alias))?$addr->alias:null;
                $address->group_id = $addr->group_id;
                $ut->continueTimer("db_getAddressGroupTags");
                $grp = db_getAddressGroupTags($addr->group_id);
                if (isset($grp->tags) && count($grp->tags) > 0){
                    $address->tags = $grp->tags;
                } else {
                    $address->tags = array();
                }
                $ut->stopTimer("db_getAddressGroupTags");
                $address->bal = splitFloat($addr->balance, $precision, $precision);
                $address->bal_percent = splitFloat(@cdec($addr->balance)/$db_supply->coincirculation*100, $precision, $precision);
                $cache->top_addresses[$rank] = $address;
                if ($n <= 24){
                    $cache->distribution->q1->amount += @cdec($addr->balance);
                } else if($n > 24 && $n <= 49){
                    $cache->distribution->q2->amount += @cdec($addr->balance);
                } else if($n > 49 && $n <= 74){
                    $cache->distribution->q3->amount += @cdec($addr->balance);
                } else {
                    $cache->distribution->q4->amount += @cdec($addr->balance);
                }
                $n++;
            }

            $cache->distribution->q_total->amount =splitFloat(@cmdec($cache->distribution->q1->amount + 
                                                    $cache->distribution->q2->amount + 
                                                    $cache->distribution->q3->amount + 
                                                    $cache->distribution->q4->amount), $precision, $precision);

            $cache->distribution->other->amount = $db_supply->coincirculation - @cdec($cache->distribution->q_total->amount->number) ;
                                                    
            // calculate percents. A chain with nothing in circulation yet
            // would divide by zero here, which is fatal on PHP 8.
            if ($db_supply->coincirculation != 0) {
            $cache->distribution->q1->percent = $cache->distribution->q1->amount/$db_supply->coincirculation*100;
            $cache->distribution->q2->percent = $cache->distribution->q2->amount/$db_supply->coincirculation*100;
            $cache->distribution->q3->percent = $cache->distribution->q3->amount/$db_supply->coincirculation*100;
            $cache->distribution->q4->percent = $cache->distribution->q4->amount/$db_supply->coincirculation*100;
            $cache->distribution->q_total->percent = splitFloat(@cmdec($cache->distribution->q1->percent +
                                                                      $cache->distribution->q2->percent +
                                                                      $cache->distribution->q3->percent +
                                                                      $cache->distribution->q4->percent), 2, 2);

            $cache->distribution->other->percent = splitFloat(@cmdec(100 -$cache->distribution->q1->percent -
                                                                    $cache->distribution->q2->percent - 
                                                                    $cache->distribution->q3->percent -
                                                                    $cache->distribution->q4->percent), 2, 2);
            
            $cache->distribution->q1->percent = splitFloat(@cmdec($cache->distribution->q1->percent), 2, 2);
            $cache->distribution->q2->percent = splitFloat(@cmdec($cache->distribution->q2->percent), 2, 2);
            $cache->distribution->q3->percent = splitFloat(@cmdec($cache->distribution->q3->percent), 2, 2);
            $cache->distribution->q4->percent = splitFloat(@cmdec($cache->distribution->q4->percent), 2, 2);
            }

            // convert amounts to splits with precision 2; also with nothing
            // in circulation, the pages read whole/fraction from them
            $cache->distribution->q1->amount = splitFloat(@cmdec($cache->distribution->q1->amount), 2, 2);
            $cache->distribution->q2->amount = splitFloat(@cmdec($cache->distribution->q2->amount), 2, 2);
            $cache->distribution->q3->amount = splitFloat(@cmdec($cache->distribution->q3->amount), 2, 2);
            $cache->distribution->q4->amount = splitFloat(@cmdec($cache->distribution->q4->amount), 2, 2);
            $cache->distribution->other->amount = splitFloat(@cmdec($cache->distribution->other->amount), 2, 2);
            // end calculate percents
            $ut->stopTimer("calculateTopAddressesDistribution");
        }

        // record current cache
        $ut->createTimer("db_recordTopAddressesCache()",1);
        db_recordTopAddressesCache($cache);
        $ut->stopTimer("db_recordTopAddressesCache()");
        $ut->stopTimer("total");
        //echo json_encode($tt->getCollection());
        return $cache;
    }

    function ui_GetTopAddressesData(){
        $t = milliseconds();
        // get cache
        $data = null;
        $cache = db_getTopAddressesCache();
        if (count($cache)>0){
            if (isset($cache[0]->data)){
                $data = $cache[0]->data;  
            }
        }

        $outdated = false;
        if ($data != null){
            // check chache is not outdated

            // // get last db block
            // $last_blocks = db_getLastBlocks(1);            

            // // check height if height and block hash are the same
            // if (count($last_blocks)>0){
            //     $last_block = $last_blocks[0];
            //     if ($last_block->block_ix> -1 && ($last_block->block_ix != $data->last_block->number || $last_block->_id != $data->last_block->hash)){
            //         $outdated = true;
            //     }
            // } 
            // if cache is older than 2 minutes, we consider it outdated
            if (isset($data->t) && is_numeric($data->t)&&milliseconds()-$data->t >= 120000){
                $outdated = true;
            }
        }

        if ($data != null && !$outdated){
            // if cache is ok - return it
            return $data;
            
        }

        // if outdated or no cache, create new cache and return it
        $data = ui_CreateTopAddressesCache();
        return $data;
    }

    function ui_CreateTopAddressGroupsCache(){
        global $settings;
        $precision = (int)$settings["overview_precision"];
        $ut = new TimeTracker();
        $ut->createTimer("total",0);
        $cache = new stdClass();
        $cache->t = milliseconds();
        $cache->last_block = new stdClass();
        $cache->last_block->number = -1;
        $cache->last_block->hash = "";

        // last db block number and hash
        $ut->createTimer("db_getLastBlocks(1)",1);
        $db_block = db_getLastBlocks(1);
        $ut->stopTimer("db_getLastBlocks(1)",null);
        if (count($db_block) > 0){

            // only can create cache if any block processed
            $cache->last_block->number = $db_block[0]->block_ix;
            $cache->last_block->hash = $db_block[0]->_id;

             // last RPC block - not needed at creating the cache
            // db coin supply
            $db_supply = db_getDbCoinSupply(); 
            $cache->coin_supply = new stdClass();
            $cache->coin_supply->circulation = splitFloat(@cmdec($db_supply->coincirculation),$precision,$precision);
            $cache->coin_supply->pow_supply = splitFloat(@cmdec($db_supply->pow_supply), $precision, $precision);
            $cache->coin_supply->pos_supply = splitFloat(@cmdec($db_supply->pos_supply), $precision, $precision);
            $cache->coin_supply->illegal_supply = splitFloat(@cmdec($db_supply->illegal_supply), $precision, $precision);
            $cache->coin_supply->burned = splitFloat(@cmdec($db_supply->burned), $precision, $precision);
            $cache->coin_supply->zerocoin_supply = splitFloat(@cmdec($db_supply->zerocoinsupply), $precision, $precision);
            $cache->coin_supply->shield_supply = splitFloat(@cmdec($db_supply->shieldsupply), $precision, $precision);
            $cache->coin_supply->credit_pool_supply = splitFloat(@cmdec($db_supply->creditpoolsupply), $precision, $precision);
            if (isset($db_supply->airdrop_supply)){
                $cache->coin_supply->airdrop_supply = splitFloat(@cmdec($db_supply->airdrop_supply), $precision, $precision);
            }

            // masternode count
            // to calculate locked coins
            if (array_key_exists("masternode_collateral",$settings)) {
              $masternode_locked_coins = @cdec(0);
              try{
                $masternode_collateral =  getCollateralAmount(); // 2.0.167
              } catch (Exception $e) {
                $masternode_collateral = @cdec(0);
              }

              // tiered masternodes
              if (is_array($masternode_collateral)){
                $masternode_count = ui_getMasternodeCount();
                if (isset($masternode_count->detailed)){
                  foreach ($masternode_count->detailed as $tier => $count){
                    if (isset($count->total)){
                      $tier_collateral = isset($masternode_collateral[$tier])?$masternode_collateral[$tier]:$masternode_collateral["regular"];
                      $tier_count = $count->total;
                      $masternode_locked_coins = $masternode_locked_coins + @cdec($tier_count * $tier_collateral);
                    }
                  }
                }
              } else {
                // single collateral
                $masternodes_total = ui_getMasternodeCount()->total;
                $rpc_masternode_count = (is_numeric($masternodes_total))?intval($masternodes_total):0;
                $masternode_locked_coins = @cdec($rpc_masternode_count * $masternode_collateral);
              }

              $cache->coin_supply->masternode_locked_coins = splitFloat(@cmdec($masternode_locked_coins),$precision, $precision);
              if (@cdec($cache->coin_supply->circulation->number) > 0 && $masternode_locked_coins > 0){ 
                $cache->coin_supply->masternode_locked_percent = splitFloat(@cmdec(@cdec($cache->coin_supply->masternode_locked_coins->number)/@cdec($cache->coin_supply->circulation->number)*100),$precision, $precision);
              } else {
                $cache->coin_supply->masternode_locked_percent = splitFloat(@cmdec(0),$precision, $precision);
              }
            }
            
            $ut->createTimer("db_getTopAddressGroupsByBalance(100,0,f,t)",1);
            $top_groups = db_getTopAddressGroupsByBalance(100,0,false,true);
            $ut->stopTimer("db_getTopAddressGroupsByBalance(100,0,f,t)",null);

            $ut->createTimer("calculateTopAddressGroupsDistribution",1);
            $cache->top_groups = array();
            $n = 0;
            $cache->distribution = new stdClass();
            $cache->distribution->q1 = new stdClass();
            $cache->distribution->q1->amount = @cdec(0);
            $cache->distribution->q1->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q2 = new stdClass();
            $cache->distribution->q2->amount = @cdec(0);
            $cache->distribution->q2->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q3 = new stdClass();
            $cache->distribution->q3->amount = @cdec(0);
            $cache->distribution->q3->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q4 = new stdClass();
            $cache->distribution->q4->amount = @cdec(0);
            $cache->distribution->q4->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->other = new stdClass();
            $cache->distribution->other->amount = @cdec(0);
            $cache->distribution->other->percent = splitFloat(0, $precision, $precision);
            $cache->distribution->q_total = new stdClass();
            $cache->distribution->q_total->amount = @cdec(0);
            $cache->distribution->q_total->percent = splitFloat(0, $precision, $precision);

            foreach ($top_groups as $grp){
                $rank = $n+1;
                $group = new stdClass();
                $group->rank = $rank;
                $group->group_id = $grp->_id;
                $group->bal = splitFloat($grp->balance, $precision, $precision);
                $group->bal_percent = splitFloat(@cdec($grp->balance)/$db_supply->coincirculation*100, $precision, $precision);
                $group->address_count = $grp->address_count;
                if (isset($grp->tags)){ $group->tags = $grp->tags; } else { $group->tags = array(); }
                $cache->top_groups[$rank] = $group;
                if ($n <= 24){
                    $cache->distribution->q1->amount += @cdec($grp->balance);
                } else if($n > 24 && $n <= 49){
                    $cache->distribution->q2->amount += @cdec($grp->balance);
                } else if($n > 49 && $n <= 74){
                    $cache->distribution->q3->amount += @cdec($grp->balance);
                } else {
                    $cache->distribution->q4->amount += @cdec($grp->balance);
                }
                $n++;
            }

            $cache->distribution->q_total->amount =splitFloat(@cmdec($cache->distribution->q1->amount + 
                                                    $cache->distribution->q2->amount + 
                                                    $cache->distribution->q3->amount + 
                                                    $cache->distribution->q4->amount), $precision, $precision);

            $cache->distribution->other->amount = $db_supply->coincirculation - @cdec($cache->distribution->q_total->amount->number) ;
                                                    
            // calculate percents. A chain with nothing in circulation yet
            // would divide by zero here, which is fatal on PHP 8.
            if ($db_supply->coincirculation != 0) {
            $cache->distribution->q1->percent = $cache->distribution->q1->amount/$db_supply->coincirculation*100;
            $cache->distribution->q2->percent = $cache->distribution->q2->amount/$db_supply->coincirculation*100;
            $cache->distribution->q3->percent = $cache->distribution->q3->amount/$db_supply->coincirculation*100;
            $cache->distribution->q4->percent = $cache->distribution->q4->amount/$db_supply->coincirculation*100;
            $cache->distribution->q_total->percent = splitFloat(@cmdec($cache->distribution->q1->percent +
                                                                      $cache->distribution->q2->percent +
                                                                      $cache->distribution->q3->percent +
                                                                      $cache->distribution->q4->percent), 2, 2);

            $cache->distribution->other->percent = splitFloat(@cmdec(100 - $cache->distribution->q1->percent -
                                                                    $cache->distribution->q2->percent - 
                                                                    $cache->distribution->q3->percent -
                                                                    $cache->distribution->q4->percent), 2, 2);
            
            $cache->distribution->q1->percent = splitFloat(@cmdec($cache->distribution->q1->percent), 2, 2);
            $cache->distribution->q2->percent = splitFloat(@cmdec($cache->distribution->q2->percent), 2, 2);
            $cache->distribution->q3->percent = splitFloat(@cmdec($cache->distribution->q3->percent), 2, 2);
            $cache->distribution->q4->percent = splitFloat(@cmdec($cache->distribution->q4->percent), 2, 2);
            }

            // convert amounts to splits with precision 2; also with nothing
            // in circulation, the pages read whole/fraction from them
            $cache->distribution->q1->amount = splitFloat(@cmdec($cache->distribution->q1->amount), 2, 2);
            $cache->distribution->q2->amount = splitFloat(@cmdec($cache->distribution->q2->amount), 2, 2);
            $cache->distribution->q3->amount = splitFloat(@cmdec($cache->distribution->q3->amount), 2, 2);
            $cache->distribution->q4->amount = splitFloat(@cmdec($cache->distribution->q4->amount), 2, 2);
            $cache->distribution->other->amount = splitFloat(@cmdec($cache->distribution->other->amount), 2, 2);
            $ut->stopTimer("calculateTopAddressGroupsDistribution");

        }
        
        // record current cache
        $ut->createTimer("db_recordTopAddressGroupsCache()",1);
        db_recordTopAddressGroupsCache($cache);
        $ut->stopTimer("db_recordTopAddressGroupsCache()");
        $ut->stopTimer("total");
        return $cache;
    }

    function ui_GetTopAddressGroupsData(){
        // get cache
        $data = null;
        $cache = db_getTopAddressGroupsCache();
        if (count($cache)>0){
            if (isset($cache[0]->data)){
                $data = $cache[0]->data;  
            }
        }

        $outdated = false;
        if ($data != null){
            // check chache is not outdated

            // // get last db block
            // $last_blocks = db_getLastBlocks(1);            

            // // check height if height and block hash are the same
            // if (count($last_blocks)>0){
            //     $last_block = $last_blocks[0];
            //     if ($last_block->block_ix> -1 && ($last_block->block_ix != $data->last_block->number || $last_block->_id != $data->last_block->hash)){
            //         $outdated = true;
            //     }
            // } 
            // if cache is older than 2 minutes, we consider it outdated
            if (isset($data->t) && is_numeric($data->t)&&milliseconds()-$data->t >= 120000){
                $outdated = true;
            }
        }

        if ($data != null && !$outdated){
            // if cache is ok - return it
            return $data;
            
        }

        // if outdated or no cache, create new cache and return it
        $data = ui_CreateTopAddressGroupsCache();
        return $data;
    }

    function ui_CreateMiningCache(){
        global $settings;
        $precision = (int)$settings["default_precision"];
        $ut = new TimeTracker();
        $ut->createTimer("total",0);
        $cache = new stdClass();
        $cache->t = milliseconds();
        $cache->last_block = new stdClass();
        $cache->last_block->number = -1;
        $cache->last_block->hash = "";

        // last db block number and hash
        $ut->createTimer("db_getLastBlocks(1)",1);
        $db_block = db_getLastBlocks(1);
        $ut->stopTimer("db_getLastBlocks(1)",null);
        if (count($db_block) > 0){
            // only can create cache if any block processed
            $cache->last_block->number = $db_block[0]->block_ix;
            $cache->last_block->hash = $db_block[0]->_id;
            $cache->average = new stdClass();
            $cache->average->pow_reward = splitFloat(@cmdec(0), $precision, $precision);
            $cache->average->block_reward = splitFloat(@cmdec(0), $precision, $precision);

            $cache->total = new stdClass();
            $cache->total->blocks = 0;
            $cache->total->pow_reward = splitFloat(@cmdec(0), $precision, $precision);
            $cache->total->block_reward = splitFloat(@cmdec(0), $precision, $precision);

            $cache->hashrate = new stdClass();
            $hr = getNetworkHashrate();
            $cache->hashrate->value = splitFloat($hr->value,3,3);
            $cache->hashrate->dimension = $hr->dimension;

            $cache->difficulty = new stdClass();
            $diff = getDifficulty();
            $cache->difficulty->value = splitFloat($diff->value,3,3);
            $cache->difficulty->dimension = $diff->dimension;

            $etime = $db_block[0]->block_time_sec;
             
            $ut->createTimer("db_getBlocksByTimeRange(-86400,t,t,t)",1);
            $blocks = db_getBlocksByTimeRange($etime-86400, $etime, true, true, true);
            $ut->stopTimer("db_getBlocksByTimeRange(-86400,t,t,t)");
            if (count($blocks)>0){
                $cache->total->blocks = count($blocks);
                // last block metrics
                $cache->last_block->pow_reward = splitFloat(@cmdec($blocks[0]->pow_reward), $precision, $precision); //lmr
                $cache->last_block->block_reward = splitFloat(@cmdec($blocks[0]->block_reward), $precision, $precision); //lbr

                //$block_count = count($blocks);
                //$avg_pow_reward = @cdec(0); // average mining reward

                $ut->createTimer('calculateDistribution',1);
                $ut->createTimer('db_getAddressTags()',2);

                $blocks_reward_total = @cdec(0);
                $blocks_reward_pow = @cdec(0);
                //$tags = array(); // temp associative array "address" => { "tags => ['string'], "alias" => 'string'} ?
                $miners = array(); // associative array "address =>{ "mined_blocks"=>int, "mined_coins"=>dec, tags => ['string'], "alias" => 'string'}
                $reward_per_block = new stdClass();
                $reward_per_block->pow = array();
                $reward_per_block->pow_blocks = 0;
                $reward_per_block->non_pow = array();
                $reward_per_block->non_pow_blocks = 0;
                //$reward_per_block->labels = array();
                $champions = array(); // array of mining champions {"address" => amount}
                foreach ($blocks as $n=>$block){
                    $blocks_reward_total += @cdec($block->block_reward);
                    if($block->type == 'PoW'){ 
                        $reward_per_block->pow_blocks += 1;
                        $blocks_reward_pow += @cdec($block->pow_reward);
                        $reward_per_block->pow[$block->block_ix] = @cmdec($block->block_reward);
                        if (empty($block->pow_winner)){
                            continue;
                        }
                        if (array_key_exists($block->pow_winner, $champions)){
                            $champions[$block->pow_winner] += 1;
                        } else {
                            $champions[$block->pow_winner] = 1;
                        }

                        if (array_key_exists($block->pow_winner, $miners)){
                            $miners[$block->pow_winner]->mined_blocks++;
                            $miners[$block->pow_winner]->mined_coins += @cdec($block->pow_reward);
                        } else {
                            $miners[$block->pow_winner] = new stdClass();
                            $miners[$block->pow_winner]->mined_blocks = 1;
                            $miners[$block->pow_winner]->mined_coins = @cdec($block->pow_reward);
                            $miners[$block->pow_winner]->tags = array();
                            $miners[$block->pow_winner]->alias = "";

                            $ut->continueTimer('db_getAddressTags()');
                            $grp = db_getAddressTags($block->pow_winner);
                            $ut->stopTimer('db_getAddressTags()');
                            if ($grp->has_tags && count($grp->group_tags) > 0) {
                                $miners[$block->pow_winner]->tags = $grp->group_tags;
                            }
                            if ($grp->has_alias){
                                $miners[$block->pow_winner]->alias = $grp->address_alias;
                            }                        
                        }

                    } else {
                        $reward_per_block->non_pow_blocks += 1;
                        $reward_per_block->non_pow[$block->block_ix] = @cmdec($block->block_reward);
                    }
                }
                $cache->miners = $miners;
                $cache->reward_per_block = $reward_per_block;
                $cache->total->blocks_reward = splitFloat(@cmdec($blocks_reward_total), $precision, $precision);
                $cache->total->pow_reward = splitFloat(@cmdec($blocks_reward_pow), $precision, $precision);

                // average block reward
                if ($reward_per_block->pow_blocks > 0){
                    $cache->average->pow_reward = splitFloat(@cmdec($blocks_reward_pow/$reward_per_block->pow_blocks), $precision, $precision);
                    $cache->average->block_reward = splitFloat(@cmdec($blocks_reward_total/$reward_per_block->pow_blocks), $precision, $precision);
                }
                $ut->createTimer('sortChampions',1);
                arsort($champions);
                $ut->stopTimer('sortChampions');

                $cache->pie_chart = new stdClass;
                $cache->pie_chart->labels = array();
                $cache->pie_chart->values = array();
                if (count($champions)>0){
                    $n = count($champions);
                    if ($n>=9) {
                        $n = 9;
                    }
                    //$champions = array_slice($champions, 0, 9);
                    $i = 0;
                    $champion_blocks = 0;
                    foreach ($champions as $addr=>$blocks){
                        if (array_key_exists($addr, $cache->miners) && $cache->miners[$addr]->alias != ""){
                            array_push($cache->pie_chart->labels,$cache->miners[$addr]->alias);
                        } else {
                            array_push($cache->pie_chart->labels,$addr);
                        }
                        array_push($cache->pie_chart->values,$blocks);
                        $champion_blocks += $blocks;
                        $i++;
                        if ($i>=$n) {break;}
                    }

                    array_push($cache->pie_chart->labels, "Other");
                    array_push($cache->pie_chart->values, $reward_per_block->pow_blocks - $champion_blocks);
                    
                }   

                // convert mined coind to mdec
                $ut->createTimer('convertMinedCoins',1);
                foreach ($cache->miners as $addr => $miner){
                    $cache->miners[$addr]->mined_coins = splitFloat(@cmdec($miner->mined_coins), $precision, $precision);
                }
                $ut->stopTimer('convertMinedCoins');
            }

            $ut->createTimer('getChainMetricsAggregates(time(),168)',1);
            $cache->aggregates = getChainMetricsAggregates($etime,168);
            $ut->stopTimer('getChainMetricsAggregates(time(),168)');
        }
        // record current cache
        $ut->createTimer("db_recordMiningCache()",1);
        db_recordMiningCache($cache);
        $ut->stopTimer("db_recordMiningCache()");
        $ut->stopTimer("total");
        return $cache;
    }

    function ui_GetMiningData(){
        // get cache
        $data = null;
        $cache = db_getMiningCache();
        if (count($cache)>0){
            if (isset($cache[0]->data)){
                $data = $cache[0]->data;  
            }
        }

        $outdated = false;
        if ($data != null){
            // check chache is not outdated

            // // get last db block
            // $last_blocks = db_getLastBlocks(1);            

            // // check height if height and block hash are the same
            // if (count($last_blocks)>0){
            //     $last_block = $last_blocks[0];
            //     if ($last_block->block_ix> -1 && ($last_block->block_ix != $data->last_block->number || $last_block->_id != $data->last_block->hash)){
            //         $outdated = true;
            //     }
            // } 
            // if cache is older than 2 minutes, we consider it outdated
            if (isset($data->t) && is_numeric($data->t)&&milliseconds()-$data->t >= 120000){
                $outdated = true;
            }
        }

        if ($data != null && !$outdated){
            // if cache is ok - return it
            return $data;
            
        }

        // if outdated or no cache, create new cache and return it
        $data = ui_CreateMiningCache();
        return $data;
    }

    /**
     * Pre-compute the Gold Rush figures that come from our own records.
     *
     * The shadow aggregations read the synthetic slice of VOUTS, which keeps
     * growing for the whole length of the reward window, so the page must not
     * run them per request. Live daemon state (phase, reward pools) is cheap
     * and stays uncached in the page itself.
     *
     * No TimeTracker here on purpose: this can be rebuilt from the web page,
     * which does not load timeTracker.php. The loader times the whole call.
     */
    function ui_CreateGoldRushCache(){
        $cache = new stdClass();
        $cache->t = milliseconds();
        $cache->last_block = new stdClass();
        $cache->last_block->number = -1;
        $cache->last_block->hash = "";

        $db_block = db_getLastBlocks(1);
        if (count($db_block) > 0){
            $cache->last_block->number = $db_block[0]->block_ix;
            $cache->last_block->hash = $db_block[0]->_id;
        }

        // credited totals by channel. Amounts are stored as strings: Decimal
        // does not survive a BSON round-trip, and the page only formats them.
        $t = db_getShadowTotals();
        $cache->totals = new stdClass();
        $cache->totals->credited     = is_null($t) ? "0" : (string)$t->credited;
        $cache->totals->pow_credited = is_null($t) ? "0" : (string)$t->pow_credited;
        $cache->totals->pos_credited = is_null($t) ? "0" : (string)$t->pos_credited;
        $cache->totals->unspent      = is_null($t) ? "0" : (string)$t->unspent;
        $cache->totals->payout_count = is_null($t) ? 0   : (int)$t->payout_count;
        $cache->totals->pow_count    = is_null($t) ? 0   : (int)$t->pow_count;
        $cache->totals->pos_count    = is_null($t) ? 0   : (int)$t->pos_count;

        // top quantum recipients
        $cache->recipients = array();
        foreach (db_getShadowTopRecipients(10) as $r){
            $e = new stdClass();
            $e->_id    = $r->_id;
            $e->count  = (int)$r->count;
            $e->amount = (string)@cdec($r->amount);
            array_push($cache->recipients, $e);
        }

        // most recent payouts
        $cache->recent = array();
        foreach (db_getShadowRecentPayouts(25) as $p){
            $e = new stdClass();
            $e->block_ix    = (int)$p->block_ix;
            $e->address     = $p->address;
            $e->amount      = (string)@cdec($p->amount);
            $e->shadow_mode = isset($p->shadow_mode) ? $p->shadow_mode : "-";
            array_push($cache->recent, $e);
        }

        db_recordGoldRushCache($cache);
        return $cache;
    }

    function ui_GetGoldRushData(){
        // get cache
        $data = null;
        $cache = db_getGoldRushCache();
        if (count($cache)>0){
            if (isset($cache[0]->data)){
                $data = $cache[0]->data;
            }
        }

        $outdated = false;
        if ($data != null){
            // if cache is older than 2 minutes, we consider it outdated
            if (isset($data->t) && is_numeric($data->t)&&milliseconds()-$data->t >= 120000){
                $outdated = true;
            }
        }

        if ($data != null && !$outdated){
            // if cache is ok - return it
            return $data;
        }

        // if outdated or no cache, create new cache and return it
        $data = ui_CreateGoldRushCache();
        return $data;
    }


    function ui_CreateOverviewCache(){
        global $settings;
        $movements_overview_days = isset($settings["movements_overview_days"]) ? (int)$settings["movements_overview_days"] : 7;
        $precision = (int)$settings["default_precision"];
        $current_time = time();
        $start_time = $current_time - $movements_overview_days*86400;
        $ut = new TimeTracker();
        $ut->createTimer("total",0);
        $cache = new stdClass();
        $cache->t = milliseconds();
        $cache->last_block = new stdClass();
        $cache->last_block->number = -1;
        $cache->last_block->hash = "";

        // last db block number and hash
        $ut->createTimer("db_getLastBlocks(1)",1);
        $db_block = db_getLastBlocks(1);
        $ut->stopTimer("db_getLastBlocks(1)");
        if (count($db_block) > 0){
            // only can create cache if any block processed
            $cache->last_block->number = $db_block[0]->block_ix;
            $cache->last_block->hash = $db_block[0]->_id;

            $ut->createTimer("getAverageBlockSpacing",1);
            $cache->block_spacing = getAverageBlockSpacing(1440, time())->string;
            $ut->stopTimer("getAverageBlockSpacing");

            $ut->createTimer("db_getDbCoinSupply",1);
            $cache->coin_supply = db_getDbCoinSupply(); 
            $ut->stopTimer("db_getDbCoinSupply");
            // converting to mongodb decimal
            if (isset($cache->coin_supply->pow_supply)){
                $cache->coin_supply->pow_supply = @cmdec($cache->coin_supply->pow_supply);
            }
            if (isset($cache->coin_supply->pos_supply)){
                $cache->coin_supply->pos_supply = @cmdec($cache->coin_supply->pos_supply);
            }
            if (isset($cache->coin_supply->illegal_supply)){
                $cache->coin_supply->illegal_supply = @cmdec($cache->coin_supply->illegal_supply);
            }
            if (isset($cache->coin_supply->airdrop_supply)){
                $cache->coin_supply->airdrop_supply = @cmdec($cache->coin_supply->airdrop_supply);
            }
            if (isset($cache->coin_supply->coincirculation)){
                $cache->coin_supply->coincirculation = @cmdec($cache->coin_supply->coincirculation);
            }
            if (isset($cache->coin_supply->moneysupply)){
                $cache->coin_supply->moneysupply = @cmdec($cache->coin_supply->moneysupply);
            }
            if (isset($cache->coin_supply->burned)){
                $cache->coin_supply->burned = @cmdec($cache->coin_supply->burned);
            }
            if (isset($cache->coin_supply->zerocoinsupply)){
                $cache->coin_supply->zerocoinsupply = @cmdec($cache->coin_supply->zerocoinsupply);
            }
            if (isset($cache->coin_supply->shieldsupply)){
                $cache->coin_supply->shieldsupply = @cmdec($cache->coin_supply->shieldsupply);
            }
            if (isset($cache->coin_supply->creditpoolsupply)){
                $cache->coin_supply->creditpoolsupply = @cmdec($cache->coin_supply->creditpoolsupply);
            }

            $ut->createTimer("credit_pool_activity",1);
            $cache->credit_pool = new stdClass();
            $cache->credit_pool->address = "Credit Pool";
            $cache->credit_pool->from_time = $start_time;
            $cache->credit_pool->to_time = $current_time;
            $cp_totals = db_getAddressMovesInOutTotalsByTimeRange("Credit Pool", $start_time, $current_time);
            $cache->credit_pool->in = @cmdec($cp_totals->in);
            $cache->credit_pool->out = @cmdec($cp_totals->out);
            $ut->stopTimer("credit_pool_activity");

            $cache->hashrate = new stdClass();
            $ut->createTimer("getNetworkHashrate",1);
            $hr = getNetworkHashrate();
            $ut->stopTimer("getNetworkHashrate");
            $cache->hashrate->value = splitFloat($hr->value,3,3);
            $cache->hashrate->dimension = $hr->dimension;

            $cache->difficulty = new stdClass();
            $ut->createTimer("getDifficulty",1);
            $diff = getDifficulty();
            $ut->stopTimer("getDifficulty");
            $cache->difficulty->value = splitFloat($diff->value,3,3);
            $cache->difficulty->dimension = $diff->dimension;
            
            $cache->counts = new stdClass();
            if (isset($settings["show_pages"]["masternodes"]) && $settings["show_pages"]["masternodes"]){
                $ignore_cache = true;
                if (isset($settings["masternode_caching_enabled"]) && $settings["masternode_caching_enabled"]){$ignore_cache = false;}
                $ut->createTimer("ui_getMasternodeCount",1);
                $rpc_mnc = ui_getMasternodeCount($ignore_cache);
                $cache->counts->mn_total = $rpc_mnc->total;
                $cache->counts->mn_enabled = $rpc_mnc->enabled;
                $ut->stopTimer("ui_getMasternodeCount");
            } else {
                $cache->mempool = new stdClass();
                $ut->createTimer("ui_getMempoolInfo",1);
                $rpc_mp = ui_getMempoolInfo();
                $ut->stopTimer("ui_getMempoolInfo");
                $cache->mempool->size = $rpc_mp->size;
                $mp_bytes = normalizeSi($rpc_mp->bytes,3);
                $cache->mempool->bytes_string = fpp($mp_bytes->value,3)." ".$mp_bytes->dimension."Bytes";
            }

            $ut->createTimer("db_getDbCollectionStats('ADDRESSES')",1);
            $addresses_stats = db_getDbCollectionStats("ADDRESSES"); //db_getAddressCount();
            $ut->stopTimer("db_getDbCollectionStats('ADDRESSES')");
            $cache->counts->addresses = $addresses_stats["count"]??0;

            $ut->createTimer("db_getDbCollectionStats('ADDRESSGROUPS')",1);
            $addressgroups_stats = db_getDbCollectionStats("ADDRESSGROUPS");//db_getAddressGroupCount();
            $ut->stopTimer("db_getDbCollectionStats('ADDRESSGROUPS')");
            $cache->counts->addressgroups = $addressgroups_stats["count"]??0;

            $ut->createTimer("db_getDbCollectionStats('TXS')",1);
            $txs_stats = db_getDbCollectionStats("TXS"); // db_getTxCount();
            $ut->stopTimer("db_getDbCollectionStats('TXS')");
            $cache->counts->txs = $txs_stats["count"]??0;

            $ut->createTimer("db_getDbCollectionStats('VOUTS')",1);
            $vouts_stats = db_getDbCollectionStats("VOUTS"); // db_getVoutsCount();
            $ut->stopTimer("db_getDbCollectionStats('VOUTS')");
            $cache->counts->vouts = $vouts_stats["count"]??0;

            if ( isset($settings["show_pages"]["markets"]) && $settings["show_pages"]["markets"] ){
                $cache->market_data = new stdClass();
                $ut->createTimer("getLatestMarketsData()",1);
                $markets_data = getLatestMarketsData();
                $ut->stopTimer("getLatestMarketsData()");

                // update timestamp
                $cache->market_data->timestamp = $markets_data->timestamp;
                // capitalization
                $coin_circulation = @cdec($cache->coin_supply->coincirculation,$precision);

                $base_price_btc = isset($markets_data->avg_price) ? @cdec($markets_data->avg_price) : @cdec(0);
                $btc_usd_price = isset($markets_data->base_prices["USD"]) ? @cdec($markets_data->base_prices["USD"]) : @cdec(0);
                $btc_eur_price  = isset($markets_data->base_prices["EUR"]) ? @cdec($markets_data->base_prices["EUR"]) : @cdec(0);
                $cache->market_data->capitalization = new stdClass();
                // in BTC
                $cache->market_data->capitalization->in_btc = @cmdec($coin_circulation * $base_price_btc, $precision);
                // in USD
                $cache->market_data->capitalization->in_usd = @cmdec($coin_circulation *  $base_price_btc * $btc_usd_price, $precision);
                // in EUR
                $cache->market_data->capitalization->in_eur = @cmdec($coin_circulation * $base_price_btc * $btc_eur_price,  $precision);

                // BTC price
                $cache->market_data->base_prices = array();
                foreach ($markets_data->base_prices as $k=>$v){
                    $cache->market_data->base_prices[$k] = @cmdec($v);
                }
                // BTC price change
                $cache->market_data->base_changes = array();
                foreach ($markets_data->base_changes as $k=>$v){
                    $cache->market_data->base_changes[$k] = @cmdec($v);
                }

                // list of market metrics
                $cache->market_data->metrics = $markets_data->metrics ?? array();

                $cache->market_data->avg_prices = new stdClass(); 
                $cache->market_data->avg_prices->in_btc = @cmdec($markets_data->avg_price);
                $cache->market_data->avg_prices->in_usd = @cmdec($markets_data->avg_price * $btc_usd_price);
                $cache->market_data->avg_prices->in_eur = @cmdec($markets_data->avg_price * $btc_eur_price);

                $cache->market_data->avg_percent_change = @cmdec($markets_data->avg_percent_change);
            }

            $cache->top_days = $movements_overview_days;
            $start_time = $current_time - $movements_overview_days*86400;
            // top spenting movements
            $ut->createTimer("db_getTopAddressMovesByTimeRange(..,5,t,t,t,f,t)",1);
            $top_sam = db_getTopAddressMovesByTimeRange($start_time,  $current_time, 5, true, true, true, false, true);
            $ut->stopTimer("db_getTopAddressMovesByTimeRange(..,5,t,t,t,f,t)");

            $ut->createTimer("db_getAddressTags()",1);
            foreach ($top_sam as $n=>$am){
                $ut->continueTimer("db_getAddressTags()");
                $at = db_getAddressTags($am->address);
                $ut->stopTimer("db_getAddressTags()");
                if ($at->has_alias || $at->has_tags){
                    if ($at->has_alias && !empty($at->address_alias)){
                        $am->address_alias = $at->address_alias;
                    }            
                    if (isset($at->group_tags) && count($at->group_tags) > 0){
                        $am->tags = $at->group_tags;
                    }
                    $top_sam[$n] = $am;
                }
            }
            $cache->top_spent = $top_sam;
                            
            // top receiving movements
            $ut->createTimer("db_getTopAddressMovesByTimeRange(..,5,f,t,t,f,t)",1);
            $top_ram = db_getTopAddressMovesByTimeRange($start_time, $current_time, 5, false, true, true, false, true);
            $ut->stopTimer("db_getTopAddressMovesByTimeRange(..,5,f,t,t,f,t)");

            foreach ($top_ram as $n=>$am){
                $ut->continueTimer("db_getAddressTags()");
                $at = db_getAddressTags($am->address);
                $ut->stopTimer("db_getAddressTags()");
                if ($at->has_alias || $at->has_tags){
                    if ($at->has_alias && !empty($at->address_alias)){
                        $am->address_alias = $at->address_alias;
                    }            
                    if (isset($at->group_tags) && count($at->group_tags) > 0){
                        $am->tags = $at->group_tags;
                    }
                    $top_ram[$n] = $am;
                }
            }
            $cache->top_received = $top_ram;
        }
        // record current cache
        $ut->createTimer("db_recordOverviewCache()",1);
        db_recordOverviewCache($cache);
        $ut->stopTimer("db_recordOverviewCache()");
        $ut->stopTimer("total");
        //var_dump($ut->getCollection());
        return $cache;
    }

    function ui_GetOverviewData(){
        // get cache
        $data = null;
        $cache = db_getOverviewCache();
        if (count($cache)>0){
            if (isset($cache[0]->data)){
                $data = $cache[0]->data;  
            }
        }

        $outdated = false;
        if ($data != null){
            // check chache is not outdated

            // // get last db block
            // $last_blocks = db_getLastBlocks(1);            

            // // check height if height and block hash are the same
            // if (count($last_blocks)>0){
            //     $last_block = $last_blocks[0];
            //     if ($last_block->block_ix> -1 && ($last_block->block_ix != $data->last_block->number || $last_block->_id != $data->last_block->hash)){
            //         $outdated = true;
            //     }
            // } 
            // if cache is older than 2 minutes, we consider it outdated
            if (isset($data->t) && is_numeric($data->t)&&milliseconds()-$data->t >= 120000){
                $outdated = true;
            }
        }

        if ($data != null && !$outdated){
            // if cache is ok - return it
            return $data;
            
        }

        // if outdated or no cache, create new cache and return it
        $data = ui_CreateOverviewCache();
        return $data;
    }

?>
