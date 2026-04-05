<?php

namespace Juliandresval\PhpUtils;

use ArrayObject;
use DateTime;
use Throwable;

class Functions
{
    public static function isCli(): bool
    {
        return (
            php_sapi_name() === 'cli'
            || !isset($_SERVER['HTTP_HOST'])
            || !isset($_SERVER['REMOTE_ADDR'])
            || !isset($_SERVER['SERVER_ADDR'])
        );
    }

    public static function isHttp(): bool
    {
        return !self::isCli();
    }

    /**
     * @param mixed $var
     * @param $exit
     * @return void
     */
    public static function echo_var(mixed $var, $exit = false)
    {
        error_reporting(0);
        $var = self::check_var($var);

        if (self::isCli()) {
            echo htmlentities(print_r($var, 1)) . PHP_EOL;
        } else {
            echo "<pre style='text-align: initial; border-radius: 4px; border: 1px #0c2b45 solid; padding: 4px'>" . PHP_EOL . htmlentities(print_r($var, 1)) . "</pre>" . PHP_EOL;
        }

        if ($exit) {
            exit();
        }

        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    }

    public static function echo_json($var = '', $exit = false)
    {
        echo_var(json_encode($var, JSON_PRETTY_PRINT), $exit);
    }

    public static function error_to_array(Throwable $error): array
    {
        return [
            'class' => get_class($error),
            'code' => $error->getCode(),
            'location' => $error->getFile() . ':' . $error->getLine(),
            'messages' => implode('|', array_filter([
                $error->getMessage(),
                $error->getPrevious()?->getMessage(),
                $error->getPrevious()?->getPrevious()?->getMessage(),
            ])),
        ];
    }

    public static function error_to_string(Throwable $error): string
    {
        $error = error_to_array($error);
        return <<<TXT
  class: {$error['class']}
  location: {$error['location']}
  messages: {$error['messages']}
  TXT;
    }

    public static function echo_error(Throwable $error, $exit = false)
    {
        error_reporting(0);
        $var = [
            'class' => get_class($error),
            'code' => $error->getCode(),
            'messages' => [
                $error->getMessage(),
                $error->getPrevious()?->getMessage(),
                $error->getPrevious()?->getPrevious()?->getMessage(),
            ],
            'location' => [
                ($error->getFile() ?? '') . ':' . ($error->getLine() ?? ''),
                ($error->getPrevious()?->getFile() ?? '') . ':' . ($error->getPrevious()?->getLine() ?? '')
            ],
            'trace' => self::check_var($error->getTrace())
        ];
        echo "<pre style='text-align: initial; border-radius: 4px; border: 1px #0c2b45 solid; padding: 4px'>" . PHP_EOL . print_r(
                $var,
                1
            ) . "</pre>" . PHP_EOL;
        if ($exit) {
            exit();
        }
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    }

    public static function write_log($var, string $filename = '', $rewrite = false)
    {
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], 'keepalive')) {
            $filename = 'keepalive.log';
        } else {
            $filename = empty($filename) ? __DIR__ . '/../logs/general_' . date('Y-m-d') . '.log' : $filename;
        }

        if (is_string($var)) {
            if (stripos(
                    $var,
                    'SELECT * FROM elemento_configuracionpersonal WHERE asignado = ? AND borrado = 0'
                ) !== false
                or stripos(
                    $var,
                    'SELECT * FROM elemento_configuracionpersonal WHERE (asignado = ?) AND (borrado = 0)'
                ) !== false) {
                $var = PHP_EOL . get_trace_string() . PHP_EOL . $var;
                $filename = __DIR__ . '/../logs/configuracionpersonal_' . date('Y-m-d') . '.log';
            }
        }

        //$filename = (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd()) . DIRECTORY_SEPARATOR .  $filename;

        $data = (is_array($var) || is_object($var) || is_bool($var) || is_null($var)) ? convert_to_json(
            self::check_var($var),
            JSON_PRETTY_PRINT | JSON_FORCE_OBJECT
        ) : $var;

        if (!empty($GLOBALS['microtime_ini'])) {
            $microtime_diff = round(microtime(true) - $GLOBALS['microtime_ini'], 2);
        }

        $microtime_diff = empty($microtime_diff) ? '0.00' : $microtime_diff;

        $print = '[' . date(DATE_ATOM) . '|' . $microtime_diff . '] ' . $data;

        $GLOBALS['microtime_ini'] = microtime(true);

        if ($rewrite) {
            $writed = file_put_contents($filename, $print . PHP_EOL);
        } else {
            $writed = file_put_contents($filename, $print . PHP_EOL, FILE_APPEND);
        }
        if ($writed === false) {
            exit('Error al escribir en archivo ' . $filename);
            //throw new Exception('Error al escribir en archivo ' . $filename, E_WARNING);
        }
    }

    public static function dump_file($var, $filename = 'dump_file.log', $rewrite = false)
    {
        if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], 'keepalive')) {
            $filename = 'keepalive.log';
        } else {
            $filename = empty($filename) ? 'dump_file.log' : $filename;
        }

        $filename = (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd(
            )) . DIRECTORY_SEPARATOR . $filename;

        $print = print_r(check_var($var), true);

        if ($rewrite) {
            $writed = file_put_contents($filename, $print . PHP_EOL . str_repeat('-', 80) . PHP_EOL);
        } else {
            $writed = file_put_contents($filename, $print . PHP_EOL . str_repeat('-', 80) . PHP_EOL, FILE_APPEND);
        }
        if ($writed === false) {
            exit('Error al escribir en archivo ' . $filename);
            //throw new Exception('Error al escribir en archivo ' . $filename, E_WARNING);
        }
    }

    public static function check_var($var = null, $recursive = true)
    {
        $newVar = null;
        if (is_array($var)) {
            if (count($var) > 0) {
                foreach ($var as $key => $value) {
                    if (is_object($value)) {
                        $newVar[$key] = self::check_var($value);
                    } elseif (is_array($value) && $recursive) {
                        $newVar[$key] = self::check_var($value, false);
                    } elseif (is_array($value) && !$recursive) {
                        $newVar[$key] = 'Array(' . count($value) . ')';
                    } else {
                        $newVar[$key] = $value;
                    }
                }
            } else {
                $newVar = $var;
            }
        } elseif (is_object($var)) {
            $classname = get_class($var);
            $properties = get_class_vars($classname);
            $properties = (array)$var;
            $methods = get_class_methods($var);
            $newVar[$classname]['parents'] = class_parents($var);
            foreach ($properties as $property => $value) {
                $newVar[$classname]['properties'][$property] = is_object($value) ? 'Object of class: ' . get_class(
                        $value
                    ) : (is_array($value) ? 'Array(' . count($value) . ')' : $value);
            }
            $newVar[$classname]['methods'] = $methods;
        } elseif (is_bool($var)) {
            $newVar = ($var === false) ? 'false' : 'true';
        } else {
            $newVar = is_null($var) ? 'null' : $var;
        }
        return $newVar;
    }

    public static function str_remove_spaces($string, $space = ' ')
    {
        return trim(preg_replace('/\s+/', $space, $string));
    }

    public static function str_remove_accents($string, $remove_virgulilla = true)
    {
        $a = array(
            'À',
            'Á',
            'Â',
            'Ã',
            'Ä',
            'Å',
            'Æ',
            'Ç',
            'È',
            'É',
            'Ê',
            'Ë',
            'Ì',
            'Í',
            'Î',
            'Ï',
            'Ð',
            'Ò',
            'Ó',
            'Ô',
            'Õ',
            'Ö',
            'Ø',
            'Ù',
            'Ú',
            'Û',
            'Ü',
            'Ý',
            'ß',
            'à',
            'á',
            'â',
            'ã',
            'ä',
            'å',
            'æ',
            'ç',
            'è',
            'é',
            'ê',
            'ë',
            'ì',
            'í',
            'î',
            'ï',
            'ò',
            'ó',
            'ô',
            'õ',
            'ö',
            'ø',
            'ù',
            'ú',
            'û',
            'ü',
            'ý',
            'ÿ',
            'Ā',
            'ā',
            'Ă',
            'ă',
            'Ą',
            'ą',
            'Ć',
            'ć',
            'Ĉ',
            'ĉ',
            'Ċ',
            'ċ',
            'Č',
            'č',
            'Ď',
            'ď',
            'Đ',
            'đ',
            'Ē',
            'ē',
            'Ĕ',
            'ĕ',
            'Ė',
            'ė',
            'Ę',
            'ę',
            'Ě',
            'ě',
            'Ĝ',
            'ĝ',
            'Ğ',
            'ğ',
            'Ġ',
            'ġ',
            'Ģ',
            'ģ',
            'Ĥ',
            'ĥ',
            'Ħ',
            'ħ',
            'Ĩ',
            'ĩ',
            'Ī',
            'ī',
            'Ĭ',
            'ĭ',
            'Į',
            'į',
            'İ',
            'ı',
            'Ĳ',
            'ĳ',
            'Ĵ',
            'ĵ',
            'Ķ',
            'ķ',
            'Ĺ',
            'ĺ',
            'Ļ',
            'ļ',
            'Ľ',
            'ľ',
            'Ŀ',
            'ŀ',
            'Ł',
            'ł',
            'Ń',
            'ń',
            'Ņ',
            'ņ',
            'Ň',
            'ň',
            'ŉ',
            'Ō',
            'ō',
            'Ŏ',
            'ŏ',
            'Ő',
            'ő',
            'Œ',
            'œ',
            'Ŕ',
            'ŕ',
            'Ŗ',
            'ŗ',
            'Ř',
            'ř',
            'Ś',
            'ś',
            'Ŝ',
            'ŝ',
            'Ş',
            'ş',
            'Š',
            'š',
            'Ţ',
            'ţ',
            'Ť',
            'ť',
            'Ŧ',
            'ŧ',
            'Ũ',
            'ũ',
            'Ū',
            'ū',
            'Ŭ',
            'ŭ',
            'Ů',
            'ů',
            'Ű',
            'ű',
            'Ų',
            'ų',
            'Ŵ',
            'ŵ',
            'Ŷ',
            'ŷ',
            'Ÿ',
            'Ź',
            'ź',
            'Ż',
            'ż',
            'Ž',
            'ž',
            'ſ',
            'ƒ',
            'Ơ',
            'ơ',
            'Ư',
            'ư',
            'Ǎ',
            'ǎ',
            'Ǐ',
            'ǐ',
            'Ǒ',
            'ǒ',
            'Ǔ',
            'ǔ',
            'Ǖ',
            'ǖ',
            'Ǘ',
            'ǘ',
            'Ǚ',
            'ǚ',
            'Ǜ',
            'ǜ',
            'Ǻ',
            'ǻ',
            'Ǽ',
            'ǽ',
            'Ǿ',
            'ǿ',
            'Ά',
            'ά',
            'Έ',
            'έ',
            'Ό',
            'ό',
            'Ώ',
            'ώ',
            'Ί',
            'ί',
            'ϊ',
            'ΐ',
            'Ύ',
            'ύ',
            'ϋ',
            'ΰ',
            'Ή',
            'ή'
        );
        $b = array(
            'A',
            'A',
            'A',
            'A',
            'A',
            'A',
            'AE',
            'C',
            'E',
            'E',
            'E',
            'E',
            'I',
            'I',
            'I',
            'I',
            'D',
            'O',
            'O',
            'O',
            'O',
            'O',
            'O',
            'U',
            'U',
            'U',
            'U',
            'Y',
            's',
            'a',
            'a',
            'a',
            'a',
            'a',
            'a',
            'ae',
            'c',
            'e',
            'e',
            'e',
            'e',
            'i',
            'i',
            'i',
            'i',
            'o',
            'o',
            'o',
            'o',
            'o',
            'o',
            'u',
            'u',
            'u',
            'u',
            'y',
            'y',
            'A',
            'a',
            'A',
            'a',
            'A',
            'a',
            'C',
            'c',
            'C',
            'c',
            'C',
            'c',
            'C',
            'c',
            'D',
            'd',
            'D',
            'd',
            'E',
            'e',
            'E',
            'e',
            'E',
            'e',
            'E',
            'e',
            'E',
            'e',
            'G',
            'g',
            'G',
            'g',
            'G',
            'g',
            'G',
            'g',
            'H',
            'h',
            'H',
            'h',
            'I',
            'i',
            'I',
            'i',
            'I',
            'i',
            'I',
            'i',
            'I',
            'i',
            'IJ',
            'ij',
            'J',
            'j',
            'K',
            'k',
            'L',
            'l',
            'L',
            'l',
            'L',
            'l',
            'L',
            'l',
            'l',
            'l',
            'N',
            'n',
            'N',
            'n',
            'N',
            'n',
            'n',
            'O',
            'o',
            'O',
            'o',
            'O',
            'o',
            'OE',
            'oe',
            'R',
            'r',
            'R',
            'r',
            'R',
            'r',
            'S',
            's',
            'S',
            's',
            'S',
            's',
            'S',
            's',
            'T',
            't',
            'T',
            't',
            'T',
            't',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'W',
            'w',
            'Y',
            'y',
            'Y',
            'Z',
            'z',
            'Z',
            'z',
            'Z',
            'z',
            's',
            'f',
            'O',
            'o',
            'U',
            'u',
            'A',
            'a',
            'I',
            'i',
            'O',
            'o',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'U',
            'u',
            'A',
            'a',
            'AE',
            'ae',
            'O',
            'o',
            'Α',
            'α',
            'Ε',
            'ε',
            'Ο',
            'ο',
            'Ω',
            'ω',
            'Ι',
            'ι',
            'ι',
            'ι',
            'Υ',
            'υ',
            'υ',
            'υ',
            'Η',
            'η'
        );
        if ($remove_virgulilla) {
            array_push($a, 'Ñ', 'ñ');
            array_push($b, 'N', 'n');
        }
        return str_replace($a, $b, $string);
    }

    public static function to_number($value, $dec = 4)
    {
        return round((float)$value, $dec);
    }

    public static function hours_to_time($num)
    {
        $hrs = floor($num);
        $dec = $num - $hrs;
        $min = ($dec * 60);
        return sprintf("%02d:%02d:00", $hrs, $min);
    }

    public static function min_to_time($num)
    {
        return hours_to_time($num / 60);
    }

    public static function shorten_string($string, $max = 80)
    {
        $stripped = trim(preg_replace('/\s+/', ' ', html_entity_decode($string)));
        if ($max > 0) {
            return strlen($stripped) <= $max ? $stripped : substr($stripped, 0, $max - 3) . '...';
        }
        return $stripped;
    }

    public static function flatten_string($string)
    {
        return shorten_string($string, -1);
    }

    public static function clean_html($html)
    {
        $allowed_tags = '<html><body><div><p><a><ul><li><table><tr><th><td><span><h1><h2><h3><strong><b><br><img><style>';

        return strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'), $allowed_tags);
    }

    public static function is_json($value)
    {
        if (is_string($value)) {
            return ($value == 'null') ? true : !is_null(json_decode($value, true));
        } elseif (is_integer($value) || is_double($value) || is_float($value)) {
            return true;
        } else {
            return false;
        }
    }

    public static function convert_from_json($var)
    {
        return is_null($result = json_decode($var)) ? json_last_error_msg() : $result;
    }

    public static function array_to_table(array|ArrayObject $result_array = [], string $table_attributes = '')
    {
        $result_array = is_object($result_array) ? $result_array->getArrayCopy() : $result_array;
        $headers = array_keys(array_values($result_array)[0]);
        $table = "<table {$table_attributes}><thead><tr>[header]</tr></thead><tbody>[body]</tbody></table>";
        $header = '';
        foreach ($headers as $idx => $col) {
            $header .= '<th>' . mb_strtoupper($col) . '</th>';
        }
        $body = '';
        foreach ($result_array as $index => $row) {
            $body .= "<tr style='border-collapse: collapse; border-top: 1px solid #000'>";
            foreach ($row as $col => $value) {
                $value = (is_object($value) or is_array($value)) ? json_encode($value) : $value;
                $aling = is_numeric($value) ? "style='text-align: right;'" : '';
                $body .= "<td {$aling}>{$value}</td>";
            }
            $body .= "</tr>";
        }
        $table = str_replace('[header]', $header, $table);
        $table = str_replace('[body]', $body, $table);
        return $table;
    }

    public static function array_to_table_list($array, $table_attributes = '')
    {
        $tabla = "<table {$table_attributes} width='100%'><tbody>";
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = array_to_table_list($value, $table_attributes);
            }
            $tabla .= "<tr><td width='30%'>{$key}</td><td>{$value}</td></tr>";
        }
        $tabla .= "</tbody></table>";
        return $tabla;
    }

    public static function get_trace($num_lines = 0, $offset = 2)
    {
        $trace = [];
        $path = dirname($_SERVER['DOCUMENT_ROOT']);
        $debugBacktrace = debug_backtrace();
        $backtrace = array_slice($debugBacktrace, $offset, $num_lines ?: count($debugBacktrace));
        foreach ($backtrace as $key => $point) {
            $file = ltrim(str_ireplace($path, '', $point['file'] ?? ''), '/');
            $class = !empty($point['class']) ? $point['class'] . '::' : '';
            $func = !empty($point['function']) ? $point['function'] . '()' : '';
            $code = $class . $func;
            $trace[str_pad($key, 2, '0', STR_PAD_LEFT)] = (!empty($file) ? "{$file}({$point['line']}): " : "") . $code;
        }
        return $trace;
    }

    public static function get_trace_string($num_lines = 0, $offset = 2)
    {
        $trace = "";
        foreach (get_trace($num_lines, $offset) as $key => $point) {
            $trace .= (empty($trace) ? $trace : "\n") . "$key | $point";
        }
        return $trace;
    }

    public static function array_to_csv_file($file_name, $resultados, $delimiter = ',', $enclosure = '"')
    {
        /** force download */
        header("Content-Type: text/plain; charset=utf-8");
        //header("Content-Type: application/force-download");
        //header("Content-Type: application/octet-stream");
        //header("Content-Type: application/download");
        header("Content-Disposition: attachment; filename=\"{$file_name}.csv\"");

        if (empty($resultados)) {
            echo "No se encontraron registros con los parámetros ingresados.";
        }

        if (!empty($resultados)) {
            $output = fopen("php://output", "w");
            fputcsv($output, array_keys(reset($resultados)), $delimiter, $enclosure);
            foreach ($resultados as $row) {
                fputcsv($output, $row, $delimiter, $enclosure);
            }
            return fclose($output);
        } else {
            return null;
        }
    }

    public static function strtodate($string = '')
    {
        /**
         * d y j  Día del mes, 2 dígitos con o sin ceros iniciales  01 a 31 o 1 a 31
         * m y n  Representación numérica de un mes, con o sin ceros iniciales  01 hasta 12 o 1 hasta 12
         * Y  Una representación numérica completa de un año, 4 dígitos  Ejemplos: 1999 o 2003
         */
        $arry = explode('-', $string);
        array_walk($arry, function ($val, $key) {
            if ($key >= 1) {
                $arry[$key] = str_pad($val, 2, '0', STR_PAD_LEFT);
            }
        });
        $date = DateTime::createFromFormat('Y-m-d', $string);
        if (empty($date)) {
            $date = DateTime::createFromFormat('Y-n-j', $string);
        }
        return $date;
    }

    public static function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public static function get_request()
    {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        return [
            'method' => $_SERVER['REQUEST_METHOD'],
            // 'url' => get_site_url(),
            'uri' => str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']),
            'query' => $_SERVER['QUERY_STRING'],
            'script' => $_SERVER['SCRIPT_NAME'],
            'headers' => $headers,
            'data' => [
                'get' => $_GET,
                'post' => $_POST,
                'json' => json_decode(file_get_contents('php://input'), true)
            ],
            'files' => $_FILES
        ];
    }

    public static function get_curl($params)
    {
        $curl = "curl --location --request {$params['method']} '{$params['url']}'";
        $params['headers']['Content-Type'] = 'application/json';
        foreach ($params['headers'] as $key => $header) {
            $curl .= " \\\n--header '{$key}: {$header}'";
        }
        if (!empty($params['body'])) {
            $params['body'] = !is_string($params['body']) ? json_encode($params['body']) : $params['body'];
            $curl .= " \\\n--data-raw '{$params['body']}'";
        }
        return $curl;
    }

    public static function getDirectoryTree(string $directory)
    {
        $win = '\\';  // separador windows
        $lin = '/';   // separador linux
        $directory = rtrim($directory, '/');
        // verificación y definición del serparador enviado en $string
        if (stripos($directory, $win) !== false) {
            $directory = str_replace($win, DIRECTORY_SEPARATOR, $directory);
        } elseif (stripos($directory, $lin) !== false) {
            $directory = str_replace($lin, DIRECTORY_SEPARATOR, $directory);
        }
        $tree = [];
        // verificación de la existencia del directorio
        if (file_exists($directory)) {
            // list
            $list = scandir($directory, SCANDIR_SORT_ASCENDING);
            unset($list[array_search('.', $list)], $list[array_search('..', $list)]);
            sort($list);
            // iteración ó recorrido del array $list con el contenido del directorio
            // $item es el nombre del fichero
            foreach ($list as $i => $item) {
                $path = $directory . DIRECTORY_SEPARATOR . $item;
                $type = filetype($path);
                $tree[$i] = array_merge(
                    ['name' => $item, 'type' => $type, 'date' => date(DateTime::ATOM, filemtime($path))],
                    pathinfo($path)
                );
                switch ($type) {
                    case 'dir':
                        $tree[$i]['items'] = getDirectoryTree($path);
                        break;
                    default:
                        break;
                }
            }
        }
        return $tree;
    }

    public static function flattenDirectoryTree(string $directory, array $tree, $relative = false)
    {
        $flattened = [];
        $directory = rtrim($directory, '/');
        foreach ($tree as $i => $element) {
            $path = ($relative ? '' : $directory) . DIRECTORY_SEPARATOR . $element['name'];
            if ($element['type'] == 'dir') {
                $flattened = array_merge($flattened, flattenDirectoryTree($path, $element['items']));
            } else {
                $flattened[] = $path;
            }
        }
        return $flattened;
    }

    public static function reemplazarSecuencial($cadena, $caracter, array $valores, $envoltura = '')
    {
        $resultado = '';
        $contador = 0;

        for ($i = 0; $i < strlen($cadena); $i++) {
            if ($cadena[$i] === $caracter && isset($valores[$contador])) {
                $resultado .= ($envoltura . $valores[$contador] . $envoltura);
                $contador++;
            } else {
                $resultado .= $cadena[$i];
            }
        }

        return $resultado;
    }
}
