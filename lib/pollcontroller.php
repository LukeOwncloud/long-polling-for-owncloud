<?php
namespace OCA\Long_Polling;

class PollController
{

    const CLASSNAME = 'OCA\Long_Polling\LongPoll';

    private static $preStorageInfo;

    private static $deleteFileInfo = array();

    private static $supportsPostUpdate = false;

    private static $appName = "long_polling";
    private static $myQueueKey = 0;

    private $newJsonLine = "\n";

    /**
     * Return an XML document about file changes for current user.
     */
    public function longPoll ()
    {

        
        // check access
        if (! \OCP\User::isLoggedIn()) {
            $this->respondError(401, "Bad credentials");
            return;
        }
        
        // check if supported by server
        if (! $this->isSysvshmSupported()) {
            $this->respondError(412, "Not supported by server");
            return;
        }

	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        
        // print status information
	$status = $this->getVar("status", null);
        if ($status != null) {
            $enabled = \OCP\App::isEnabled(self::$appName);
            $out["enabled"] = $enabled;
            $out["version"] = \OCP\App::getAppVersion(self::$appName);
            $this->outJson(json_encode($out));
		die();
        }

        // redirects to home if not enabled
        \OCP\App::checkAppEnabled(self::$appName);

        // $queueKey = crc32(\OCP\User::getUser());
        $queueKey = crc32(\OC::$server->getUserSession()
            ->getUser()
            ->getUID());

        \OC::$server->getSession()->close();

        
        $this->out("queuekey: ".$queueKey);
        $this->myQueueKey = microtime(true);
        $this->out("mykey: ".$this->myQueueKey);

        $queueStopSignal = "EXIT";
        
        $queue = $this->getOrCreateQueue($queueKey);

	$close = $this->getVar("close", null);
        if ($close != null) {
		if(msg_remove_queue($queue)) {
			$this->outJson("Queue removed.");
		} else {
			$this->outJson("Removing queue failed.");
		}
		die();
	}


	$json = $this->getVar("json", null);
	$help = $this->getVar("help", null);
//	$navigator_user_agent = ' ' . strtolower($_SERVER['HTTP_USER_AGENT']);
//	if (strpos($navigator_user_agent, "owncloud") !== false ) {
	if ($json != null) {
		//send correct header to owncloud.
		header("Content-type: application/json; boundary=NL");
	} else {
		//human-readable results for all other browsers.
		header('Content-Type: text/html; charset=utf-8');
		header('content-security-policy: ');
	        $this->outJson('<html><head><link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"></head><body>');
	        $this->outJson("<div class=\"container\">");
	        $this->outJson("<h1>Welcome to Long-Polling for ownCloud!</h1>");
	        $this->outJson("<div>This app relies on System V Inter-Process-Communication (IPC). For each polling user a new message queue is created. Further, for each polling user an PHP interpreter instance is occupied. Thus, this solution does not scale well for many users.</div>");
	        $this->outJson("<div>Each user can only open one poll connection at a time. An existing connection is closed when a new one is established.</div>");
	        $this->outJson("<h3>Available HTTP GET parameters</h3>");
	        $this->outJson("<div><a href=\"?status\">status</a>: Print status information about this app. Even works when not activated.</div>");
	        $this->outJson("<div><a href=\"?json\">json</a>: Return content type 'application/json'. Hide this help. Does not work well in browsers.</div>");
	        $this->outJson("<div><a href=\"?close\">close</a>: Close IPC for current user. Should be called when long-polling is not used anymore or does not work.</div>");
	        $this->outJson("<div><a href=\"?help\">help</a>: Only show this help. No long-polling.</div>");
	        $this->outJson("<div><a href=\"?\">[noparam]</a>: Show this help and long-polling results.</div>");
		if($help != null) {
	        $this->outJson("</div></html>");
die();
}
	        $this->outJson("<h3>See what's happening in your ownCloud!</h3>");
	        $this->outJson("<h4>Waiting for changes... Do not abort loading!</h4>");
	        $this->outJson("<pre>");
		$this->newJsonLine = "<br>";

	}

        
        while (true) {
            // print_r(msg_stat_queue($queue));
            $this->out("receiving...");
            $receivedType;
            $receivedMsg;
            $msg_error = 0;
            $msgType = 1;
            $result = msg_receive($queue, $msgType, $receivedType, 1000, 
                    $receivedMsg, true, 0, $msg_error);
            if ($result !== TRUE) {
                $this->out("receive failed. error: $msg_error");
		$this->queueInfo ($queue);
                $this->closeQueue();
		msg_remove_queue($queue);
                return;
            } else {
//                $this->out("receivedMsg: " . $receivedMsg);
                $o = json_decode($receivedMsg, true);
		if($o != null) {
//                $oo = print_r($o, true);
//                $this->out("payload1: " . $oo);
                $this->out("payload2: " . $o["path"]);
                //if ($this->exists($o["path"])) {
                //    $this->out("exists:" . $this->exists($o["path"]) . " TRUE!");
                //}
                
                $this->outJson($receivedMsg);
		}
            }
            
            // if($receivedMsg=="EXIT"){//$queueStopSignal){
            if (substr($receivedMsg, 0, 4) === "EXIT") { // $queueStopSignal){
                msg_remove_queue($queueKey);
                if (substr($receivedMsg, 4) !== "" . $this->myQueueKey) { // $queueStopSignal){
                    $this->out("not my key. exit. '" . substr($receivedMsg, 4) . "' != ".$this->myQueueKey);
                    
                    return;
                } else {
                    $this->out("my key. ignore.");
                }
            }
        }
    }

    private function out ($s){
return;
$this->outJson($s);
}
    private function outJson ($s)
    {
        echo ($s . $this->newJsonLine);
        ob_flush();
        flush();
    }

    private function isSysvshmSupported ()
    {
        return function_exists("msg_get_queue");
    }

    /**
     *
     * @param integer $statusCode            
     * @param string $message            
     */
    private function respondError ($statusCode, $message)
    {
        $data = array(
                'message' => $message
        );
        $this->respond($statusCode, $data);
    }

    /**
     *
     * @param integer $statusCode            
     * @param
     *            $data
     */
    private function respond ($statusCode, $data)
    {
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json');
        
        // add status header
        header($this->getStatusMessage($statusCode, '1.1'));
        
        $this->renderBody($data);
    }

    /**
     *
     * @param mixed $data            
     */
    private function renderBody ($data)
    {
        if (is_null($data)) {
            return;
        }
        
        // write json to buffer
        if (is_array($data)) {
            array_walk_recursive($data, 
                    function  (&$value)
                    {
                        if ($value instanceof OC_L10N_String) {
                            $value = (string) $value;
                        }
                    });
            echo json_encode($data);
        } else {
            echo $data;
        }
    }

    /**
     * Returns a full HTTP status message for an HTTP status code
     *
     * @param int $code            
     * @param string $httpVersion            
     * @return string
     */
    private function getStatusMessage ($code, $httpVersion = '1.1')
    {
        $msg = array(
                100 => 'Continue',
                101 => 'Switching Protocols',
                102 => 'Processing',
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authorative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                207 => 'Multi-Status', // RFC 4918
                208 => 'Already Reported', // RFC 5842
                226 => 'IM Used', // RFC 3229
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                306 => 'Reserved',
                307 => 'Temporary Redirect',
                400 => 'Bad request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                418 => 'I\'m a teapot', // RFC 2324
                422 => 'Unprocessable Entity', // RFC 4918
                423 => 'Locked', // RFC 4918
                424 => 'Failed Dependency', // RFC 4918
                426 => 'Upgrade required',
                428 => 'Precondition required', // draft-nottingham-http-new-status
                429 => 'Too Many Requests', // draft-nottingham-http-new-status
                431 => 'Request Header Fields Too Large', // draft-nottingham-http-new-status
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version not supported',
                506 => 'Variant Also Negotiates',
                507 => 'Insufficient Storage', // RFC 4918
                508 => 'Loop Detected', // RFC 5842
                509 => 'Bandwidth Limit Exceeded', // non-standard
                510 => 'Not extended',
                511 => 'Network Authentication Required'
        ); // draft-nottingham-http-new-status
        
        return 'HTTP/' . $httpVersion . ' ' . $code . ' ' . $msg[$code];
    }

    /**
     *
     * @param string $path            
     * @return bool
     */
    private function exists ($path)
    {
$this->out("exists(): " . $path);
$fileInfo = \OC\Files\Filesystem::getFileInfo($path); 
$this->out("fileInfo: " . $fileInfo);

$info = print_r($fileInfo, true);
                $this->out("file info: " . $info);


        $view = \OC\Files\Filesystem::getView();
        list ($storage, ) = $view->resolvePath($path);
        /**
         *
         * @var \OC\Files\Storage\Storage $storage
         */
        $sid = $storage->getId();
        if (! is_null($sid)) {

            return true;
        }
        
        return false;
    }

    private function getVar ($get, $default = NULL)
    {
        if (isset($_GET[$get])) {
            return trim($_GET[$get]) == "" ? true : $_GET[$get];
        } else {
            return $default;
        }
    }

    private function queueInfo ($queue) {
$info = print_r(msg_stat_queue($queue), true);
                $this->out("queue info: " . $info);
	}
    private function closeQueue ($queue)
    {
        $res = msg_remove_queue($queue);
        if ($res)
            $this->out("remove succeeded.");
        else
            $this->out("remove failed.");
    }

    private function getOrCreateQueue ($queueKey)
    {
            $this->out("myKey in getOr... " . $this->myQueueKey);

        if (msg_queue_exists($queueKey)) {
            $this->out("queue exists. send EXIT.");
            // send EXIT signal to existing queue.
            $queue = msg_get_queue($queueKey);
$msg_error = 0;
            if (! msg_send($queue, 1, "EXIT" . $this->myQueueKey, true, false, $msg_error)) {
                $this->out("send exit failed. msg_error: $msg_error");
 $this->queueInfo ($queue);
            }
            sleep(1);
        } else {
            $this->out("queue does not exist. create it.");
            $queue = msg_get_queue($queueKey);
        }
        return $queue;
    }
}
