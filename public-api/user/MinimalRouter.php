<?php

class Router
{
    private $Routes;

    public $BasePath;

    public $Request;
    public $Response;

    public function __construct($route = false)
    {
        if ($route === false)
        {
            $this->BasePath = rtrim(__DIR__, "/") . "/controller";
        }
        else
        {
            $this->BasePath = rtrim($route, "/");
        }

        $this->Routes = array();

        $this->Request = new Request();
        $this->Response = new Response();
    }

    public function add_route($pattern, $controller)
    {
        $this->Routes[] = new Route($pattern, $controller);
    }

    public function handle_request()
    {
        $uri = $this->Request->Uri;

        for ($i = 0; $i < count($this->Routes); $i++)
        {
            $route = $this->Routes[$i];

            if ($route->matches($uri))
            {
                require_once $this->BasePath . $route->Controller;

                $controller = new $route->ClassName();

                $controller->execute($this->Request, $this->Response);

                $this->submit();

                return true;
            }
        }

        return false;
    }

    public function submit()
    {
        $this->Response->submit();
    }

    public function throw($errorCode = 404, $text = "")
    {
        $this->Response->StatusCode = $errorCode;
        $this->Response->Data = $text;
        $this->submit();
    }
}

class Route
{
    private $IsCompiled;

    public $Pattern;
    public $Controller;
    public $ClassName;

    public function __construct($pattern, $controller)
    {
        $this->Pattern = $pattern;
        $this->Controller = $controller;
        $this->ClassName = pathinfo($controller, PATHINFO_FILENAME);
    }

    public function matches($subject)
    {
        $regex = $this->compile_pattern();

        return preg_match($regex, $subject);
    }

    private function compile_pattern()
    {
        if ($this->IsCompiled) return $this->Pattern;

        $result = $this->Pattern;

        $result = str_replace(":index", "(^$|^\/$|^\/index$|\/index\.html|\/index\.php)", $result);

        $result = str_replace(":lower", "[a-z]+", $result);
        $result = str_replace(":upper", "[A-Z]+", $result);

        $result = str_replace(":i", "[0-9]+", $result);
        $result = str_replace(":a", "[0-9A-Za-z]+", $result);
        $result = str_replace(":h", "[0-9A-Fa-f]+", $result);
        $result = str_replace(":c", "[a-zA-Z0-9+_\-\.]+", $result);

        $result = "@" . $result . "@i";

        $this->Pattern = $result;
        $this->IsCompiled = true;

        return $result;
    }
}

class Template
{
    private $BasePath;

    private $Html;

    public function __construct($templates)
    {
        $this->BasePath = $templates;
    }

    public function load($template)
    {
        $this->Html = file_get_contents($this->join_paths($this->BasePath, $template));
    }

    public function replace($old, $new, $limit = -1)
    {
        $this->Html = preg_replace("@" . preg_quote($old, "@") . "@i", $new, $this->Html, $limit);
    }

    public function assign($name, $value)
    {
        $this->Html = preg_replace("@%" . preg_quote($name, "@") . "%@i", $value, $this->Html);
    }

    public function display()
    {
        return $this->Html;
    }

    private function join_paths()
    {
        $paths = array();
    
        foreach (func_get_args() as $arg) {
            if ($arg !== '') { $paths[] = $arg; }
        }
    
        return preg_replace('#/+#','/',join('/', $paths));
    }
}

class Request
{
    public $RemoteAddress;

    public $Uri;

    public $Method;
    public $UserAgent;

    public $Headers;

    /**
	* Cookies.
	*
	* @var mixed
	*/
    public $Cookies;

    /**
	* Request Data.
	*
	* @var mixed
	*/
    public $Data;

    public function __construct()
    {
        $this->RemoteAddress = $this->get_remote_address();

        $this->Uri = $_SERVER['REQUEST_URI'];
        $this->Method = $_SERVER['REQUEST_METHOD'];

        if (array_key_exists("HTTP_USER_AGENT", $_SERVER))
        {
            $this->UserAgent = $_SERVER['HTTP_USER_AGENT'];
        }
        else
        {
            $this->UserAgent = "";
        }

        $this->Headers = $this->get_http_headers();
        $this->Cookies = $_COOKIE;

        if ($this->Method == "GET")
        {
            $this->Data = $_GET;
        }
        else if ($this->Method == "POST")
        {
            $this->Data = $_POST;
        }
        else
        {
            $this->Data = "";
        }
    }

    public function get_raw_data()
    {
        if ($this->Method == "POST")
        {
            return file_get_contents("php://input");
        }
        else
        {
            return parse_url($this->Uri, PHP_URL_QUERY);
        }
        
    }

    public function get_data($name)
    {
        if (!empty($this->Data) && is_array($this->Data) && array_key_exists($name, $this->Data))
        {
            return $this->Data[$name];
        }
        else
        {
            return false;
        }
    }

    public function get_header($name)
    {
        if (!empty($this->Headers) && is_array($this->Headers) && array_key_exists($name, $this->Headers))
        {
            return $this->Headers[$name];
        }
        else
        {
            return false;
        }
    }

    public function get_cookie($name)
    {
        if (!empty($this->Cookies) && is_array($this->Cookies) && array_key_exists($name, $this->Cookies))
        {
            return $this->Cookies[$name];
        }
        else
        {
            return false;
        }
    }

    private function get_http_headers()
    {
        $headers = array();

        foreach ($_SERVER as $key => $value) {
            if ("HTTP_" != substr($key, 0, 5)) {
                continue;
            }

            $header = strtoupper(substr($key, 5));
            $headers[$header] = $value;
        }

        return $headers;
    }

    private function get_remote_address()
    {
        // cloudflare support
        // if (isset($_SERVER["HTTP_CF_CONNECTING_IP"]))
        // {
        //     $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        // }
        
        return $_SERVER["REMOTE_ADDR"];
    }
}

class Response
{
    private $Caching;
    private $CanSubmit;

    public $StatusCode;
    public $ContentType;

    public $Headers;

    /**
	* Response Data.
	*
	* @var mixed
	*/
    public $Data;

    public function __construct()
    {
        $this->CanSubmit = true;

        $this->StatusCode = StatusCode::OKAY;
        $this->ContentType = ContentType::TEXT_PLAIN;
        $this->Data = "";

        $this->Headers = array();
        $this->Caching = array();
    }

    public function add_header($header, $value = false)
    {
        if ($value === false)
        {
            $this->Headers[] = $header;
        }
        else
        {
            $this->Headers[] = $header . ": " . $value;
        }
        
    }

    public function add_data($key, $value = false)
    {
        if (!is_array($this->Data))
        {
            $this->Data = array();
        }

        if ($value === false)
        {
            $this->Data[] = $key;
        }
        else
        {
            $this->Data[$key] = $value;
        }
    }

    public function set_cookie($name, $value)
    {
        if ($value === false)
        {
            return setcookie($name);
        }
        else
        {
            return setcookie($name, $value);
        }
    }

    public function enable_caching($time = 86400, $isPublic = true)
    {
        if ($isPublic)
        {
            $this->Caching = ["Cache-Control: public, max-age=" . $time];
        }
        else
        {
            $this->Caching = ["Cache-Control: private, max-age=" . $time];
        }
    }

    public function disable_caching()
    {
        $this->Caching = ["Expires: 0", "Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0", "Pragma: no-cache"];
    }

    public function submit()
    {
        if ($this->CanSubmit)
        {
            $this->CanSubmit = false;

            http_response_code($this->StatusCode);

            header("Content-Type: " . $this->ContentType);

            if (!empty($this->Headers) && is_array($this->Headers))
            {
                for ($i = 0; $i < count($this->Headers); $i++)
                {
                    header($this->Headers[$i]);
                }
            }

            if (!empty($this->Caching) && is_array($this->Caching))
            {
                for ($i = 0; $i < count($this->Caching); $i++)
                {
                    header($this->Caching[$i]);
                }
            }

            if (is_array($this->Data) && count($this->Data) > 0)
            {
                if ($this->is_sequential_array($this->Data))
                {
                    for ($i = 0; $i < count($this->Data) - 1; $i++)
                    {
                        echo $i . "=" . $this->Data[$i] . "&";
                    }

                    $tmp = count($this->Data) - 1;

                    echo $tmp . "=" . $this->Data[$tmp];
                }
                else
                {
                    $keys = array_keys($this->Data);

                    for ($i = 0; $i < count($keys) - 1; $i++)
                    {
                        echo $keys[$i] . "=" . $this->Data[$keys[$i]] . "&";
                    }

                    $tmp = count($keys) - 1;

                    echo $keys[$tmp] . "=" . $this->Data[$keys[$tmp]];
                }
            }
            else if (is_array($this->Data))
            {
                echo "";
            }
            else
            {
                echo $this->Data;
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    private function is_sequential_array($array)
    {
        if (!empty($array) || !is_array($array) || count($array) == 0)
        {
            return true;
        }

        $keys = array_keys($array);
        $filter = array_filter($keys, "is_string");
        
        return count($filter) == 0;
    }
}

class StatusCode
{
    // informational
    const CONTINUE = 100;
    const SWITCHING_PROTOCOLS = 101;
    const PROCESSING = 102;
    const EARLY_HINTS = 103;

    // success
    const OKAY = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NON_AUTHORATIVE_INFORMATION = 203;
    const NO_CONTENT = 204;
    const RESET_CONTENT = 205;
    const PARTIAL_CONTENT = 206;
    const MULTI_STATUS = 207;
    const ALREADY_REPORTED = 208;
    const IM_USED = 226;

    // redirection
    const MULTIPLE_CHOICES = 300;
    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const MOVED_TEMPORARILY = 302;
    const SEE_OTHER = 303;
    const NOT_MODIFIED = 304;
    const USE_PROXY = 305;
    const SWITCH_PROXY = 306;
    const TEMPORARY_REDIRECT = 307;
    const PERMANENT_REDIRECT = 308;

    // client error
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const PROXY_AUTHENTICATION_REQUIRED = 407;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const LENGTH_REQUIRED = 411;
    const PRECONDITION_FAILED = 412;
    const PAYLOAD_TO_LARGE = 413;
    const URI_TOO_LONG = 414;
    const UNSUPPORTED_MEDIA_TYPE = 415;
    const RANGE_NOT_SATISFIABLE = 416;
    const EXCEPTION_FAILED = 417;
    const IM_A_TEAPOT = 418;
    const MISDIRECT_REQUEST = 421;
    const UNPROCESSABLE_ENTITY = 422;
    const LOCKED = 423;
    const FAILED_DEPENDENCY = 424;
    const UPGRADE_REQUIRED = 426;
    const PRECONDITION_REQUIRED = 428;
    const TOO_MANY_REQUESTS = 429;
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const UNAVAILABLE_FOR_LEGAL_REASONS = 451;

    // server error
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const VARIANT_ALSO_NEGOTIATES = 506;
    const INSUFFICIENT_STORAGE = 507;
    const LOOP_DETECTED = 508;
    const NOT_EXTENDED = 510;
    const NETWORK_AUTHENTIOCATION_REQUIRED = 511;
}

class ContentType
{
    const AUDIO_AAC = "audio/aac";
    const AUDIO_MP3 = "audio/mpeg";
    const AUDIO_WAV = "audio/wav";
    const AUDIO_WEBM = "audio/webm";

    const APPLICATION_OCTET_STREAM = "application/octet-stream";
    const APPLICATION_PDF = "application/pdf";
    const APPLICATION_JSON = "application/json";

    const IMAGE_BMP = "image/bmp";
    const IMAGE_GIF = "image/gif";
    const IMAGE_ICO = "image/x-icon";
    const IMAGE_JPG = "image/jpeg";
    const IMAGE_PNG = "image/png";
    const IMAGE_SVG = "image/svg+xml";

    const VIDEO_MPEG = "video/mpeg";
    const VIDEO_WEBM = "video/webm";

    const TEXT_PLAIN = "text/plain";
    const TEXT_CSS = "text/css";
    const TEXT_CSV = "text/csv";
    const TEXT_HTML = "text/html";
    const TEXT_JAVASCRIPT = "text/javascript";

    public static function FromFile($path)
    {
        return mime_content_type($path);
    }

    public static function IsAudio($mime_type)
    {
        return self::startsWith($mime_type, "audio");
    }

    public static function IsText($mime_type)
    {
        return self::startsWith($mime_type, "text");
    }

    public static function IsImage($mime_type)
    {
        return self::startsWith($mime_type, "image");
    }

    public static function IsVideo($mime_type)
    {
        return self::startsWith($mime_type, "video");
    }

    public static function IsJson($mime_type)
    {
        return self::endsWith($mime_type, "json");
    }

    public static function IsBinary($mime_type)
    {
        return self::startsWith($mime_type, "application") && !self::IsJson($mime_type);
    }

    public static function IsGif($mime_type)
    {
        return self::IsImage($mime_type) && self::endsWith($mime_type, "gif");
    }

    private static function startsWith($left, $right)
    {
        return preg_match('/^' . preg_quote($right, '/') . '/i', $left);
    }

    private static function endsWith($left, $right)
    {
        return preg_match('/' . preg_quote($right, '/') . '$/i', $left);
    }
}

?>