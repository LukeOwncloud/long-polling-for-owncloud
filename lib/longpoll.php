<?php
namespace OCA\Long_Polling;

class LongPoll
{

    const CLASSNAME = 'OCA\Long_Polling\LongPoll';

    private static $preStorageInfo;

    private static $deleteFileInfo = array();

    private static $supportsPostUpdate = false;

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
        
        $queueKey = hash("md5", \OCP\User::getUser());
        
        if (msg_queue_exists($queueKey)) {
            $this->out("removing queue");
            msg_remove_queue($queueKey);
        }
        $queue = msg_get_queue($queueKey);
        
        while (true) {
            $receivedType;
            $receivedMsg;
            $msgType = 1;
            $this->out("receiving...");
            msg_receive($queue, $msgType, $receivedType, 1000, $receivedMsg);
            $this->out("received: " . $rec_msg);
        }
    }

    private function out ($s)
    {
        echo ($s . "<br>");
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
        ) // draft-nottingham-http-new-status
;
        
        return 'HTTP/' . $httpVersion . ' ' . $code . ' ' . $msg[$code];
    }
}
