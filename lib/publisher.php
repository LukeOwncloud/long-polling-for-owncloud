<?php
/**
 * Based on publisher.php from webhooks app by Thomas Müller.
 */
namespace OCA\Long_Polling;
use OC\Files\View;

class Publisher
{
    // supported topics below
    const TOPIC_FS_CHANGE = 'owncloud://filesystem-change';

    /**
     *
     * @param array $barriers
     *            of given quota barriers which are to be used to fire a quota
     *            change
     */
    public function __construct ($barriers = null, $view = null)
    {
        if (is_null($view)) {
            $view = \OC\Files\Filesystem::getView();
        }
        $this->view = $view;
    }

    private function out ($s)
    {
        echo ($s . "<br>");
        ob_flush();
        flush();
    }

    /**
     * Pushes filesystem change events
     *
     * @param string $action
     *            file change action to be pushed
     * @param string $path
     *            of the file name which has been changed
     * @param array|null $info            
     */
    public function pushFileChange ($action, $path, $affectedUsers, $info = null)
    {
        if (substr($path, - strlen('.part')) === '.part') {
            return;
        }
        
        if (is_null($info)) {
            $info = $this->view->getFileInfo($path);
        }
        
        // $this->out("pushFileChange: $action, $path, $info");
//        \OC_Log::write('longpoll', 
//                "pushFileChange: '$action', '$path', '$info', '" .
//                         print_r($affectedUsers, true) . "'", \OC_Log::ERROR);
        
        // $paths = $this->getUserPathsFromPath($path);
        // $i = print_r($paths, true);
        // \OC_Log::write('longpoll', "i '$i'", \OC_Log::ERROR);
        
        foreach ($affectedUsers as $user => $mypath) {
            
            $payload = array(
                    'action' => $action,
                    'path' => $mypath,
                    'editor' => \OCP\User::getUser(),
		    'utc' => time()
            );
            
            $this->pushNotification(self::TOPIC_FS_CHANGE, $user, $payload);
        }
    }

    /**
     * Returns a "username => path" map for all affected users
     *
     * @param string $path            
     * @return array
     */
    protected function getUserPathsFromPath ($path)
    {
        list ($file_path, $uidOwner) = $this->getSourcePathAndOwner($path);
        return \OCP\Share::getUsersSharingFile($file_path, $uidOwner, true, 
                true);
    }

    /**
     * Return the source
     *
     * @param string $path            
     * @return array
     */
    protected function getSourcePathAndOwner ($path)
    {
        $uidOwner = \OC\Files\Filesystem::getOwner($path);
        
        if ($uidOwner != $this->currentUser) {
            \OC\Files\Filesystem::initMountPoints($uidOwner);
            $info = \OC\Files\Filesystem::getFileInfo($path);
            $ownerView = new \OC\Files\View('/' . $uidOwner . '/files');
            $path = $ownerView->getPath($info['fileid']);
        }
        
        return array(
                $path,
                $uidOwner
        );
    }

    /**
     * Pushes a given notification to all queues.
     */
    private function pushNotification ($topic, $user, $payload)
    {
        $queueKey = crc32($user);
//        \OC_Log::write('longpoll', "push: $payload $topic for " . $user, 
//                \OC_Log::ERROR);
        
        if (msg_queue_exists($queueKey)) {
            // \OC_Log::write('longpoll', "getting queue for user $user",
            // \OC_Log::ERROR);
            if (($queue = msg_get_queue($queueKey)) !== FALSE) {
                // \OC_Log::write('longpoll', "about to push to user $user",
                // \OC_Log::ERROR);
                $msgType = 1;
                msg_send($queue, $msgType, json_encode($payload), true, false);
                // \OC_Log::write('longpoll', "pushed to user $user",
                // \OC_Log::ERROR);
            }
        }
        /* } */
        // \OC_Log::write('longpoll', "end push: $payload $topic for "
        // .implode(",",$users), \OC_Log::ERROR);
    }

    private function addUser ($payload, $user = null)
    {
        if (is_null($user)) {
            $user = \OCP\User::getUser();
        }
        if ($user !== false) {
            $payload['user'] = $user;
        }
        return $payload;
    }

    /**
     *
     * @param string $path            
     */
    private function addFileInfo ($payload, $info)
    {
        if (isset($info['fileid'])) {
            $payload['fileId'] = $info['fileid'];
        }
        if (isset($info['mimetype'])) {
            $payload['mimeType'] = $info['mimetype'];
        }
        
        return $payload;
    }

    private function resolveOwnerPath ($path, $info)
    {
        if (! $this->isShared($path)) {
            return array(
                    null,
                    null,
                    null
            );
        }
        
        if (isset($info['fileid'])) {
            $fileId = $info['fileid'];
            $owner = $this->view->getOwner($path);
            $ownerView = new View("/$owner/files");
            $path = $ownerView->getPath($fileId);
            return array(
                    $owner,
                    $path,
                    $info
            );
        }
        
        return array(
                null,
                null,
                null
        );
    }

    /**
     *
     * @param string $path            
     * @return bool
     */
    private function isShared ($path)
    {
        list ($storage, ) = $this->view->resolvePath($path);
        /**
         *
         * @var \OC\Files\Storage\Storage $storage
         */
        $sid = $storage->getId();
        if (! is_null($sid)) {
            $sid = explode(':', $sid);
            return ($sid[0] === 'shared');
        }
        
        return false;
    }
}
