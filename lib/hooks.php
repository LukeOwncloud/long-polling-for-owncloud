<?php
/**
 * Based on hooks.php from webhooks app by Thomas Müller.
 */
namespace OCA\Long_Polling;

class Hooks
{

    const CLASSNAME = 'OCA\Long_Polling\Hooks';

    private static $deleteFileInfo = array();

    private static $supportsPostUpdate = false;

    public static function register ()
    {
        $signals = array(
                \OC\Files\Filesystem::signal_post_create,
                \OC\Files\Filesystem::signal_delete,
                'post_delete',
                \OC\Files\Filesystem::signal_post_rename,
                \OC\Files\Filesystem::signal_post_write,
                \OC\Files\Filesystem::signal_write
        );
        
        if (defined('\OC\Files\Filesystem::signal_post_update')) {
            self::$supportsPostUpdate = true;
            $signals[] = \OC\Files\Filesystem::signal_post_update;
        }
        
        foreach ($signals as $signal) {
            \OCP\Util::connectHook(\OC\Files\Filesystem::CLASSNAME, $signal, 
                    self::CLASSNAME, $signal . 'Hook');
        }
    }

    public static function post_createHook ($arguments)
    {
        $h = new Publisher();
        $affectedUsers = self::getUserPathsFromPath($arguments['path']);
        $h->pushFileChange('new', $arguments['path'], $affectedUsers);
    }

    public static function post_updateHook ($arguments)
    {
        $h = new Publisher();
        $affectedUsers = self::getUserPathsFromPath($arguments['path']);
        $h->pushFileChange('changed', $arguments['path'], $affectedUsers);
    }

    public static function post_writeHook ($arguments)
    {
        $h = new Publisher();
        if (! self::$supportsPostUpdate) {
            $affectedUsers = self::getUserPathsFromPath($arguments['path']);
            $h->pushFileChange('changed', $arguments['path'], $affectedUsers);
        }
    }

    public static function writeHook ()
    {}

    public static function deleteHook ($arguments)
    {
        // save delete file info
        $view = \OC\Files\Filesystem::getView();
        if (! is_null($view)) {
            $path = $arguments['path'];
            $info = $view->getFileInfo($path);
            if ($info) {
                self::$deleteFileInfo[$path] = $info;
            }
            self::$deleteFileInfo[$path . "_users"] = self::getUserPathsFromPath(
                    $path);
        }
    }

    public static function post_deleteHook ($arguments)
    {
        $h = new Publisher();
        $path = $arguments['path'];
        $info = null;
        if (isset(self::$deleteFileInfo[$path])) {
            $info = self::$deleteFileInfo[$path];
        }
        if (isset(self::$deleteFileInfo[$path . "_users"])) {
            $affectedUsers = self::$deleteFileInfo[$path . "_users"];
        }
        $h->pushFileChange('deleted', $path, $affectedUsers, $info);
    }
    
    // post_rename
    public static function post_renameHook ($arguments)
    {
        $h = new Publisher();
        $affectedUsers = self::getUserPathsFromPath($arguments['newpath']);
        $h->pushFileChange('deleted', $arguments['oldpath'], $affectedUsers);
        $h->pushFileChange('new', $arguments['newpath'], $affectedUsers);
    }

    /**
     * Returns a "username => path" map for all affected users
     *
     * @param string $path            
     * @return array
     */
    protected static function getUserPathsFromPath ($path)
    {
        list ($file_path, $uidOwner) = self::getSourcePathAndOwner($path);
        return \OCP\Share::getUsersSharingFile($file_path, $uidOwner, true, 
                true);
    }

    /**
     * Return the source
     *
     * @param string $path            
     * @return array
     */
    protected static function getSourcePathAndOwner ($path)
    {
        $uidOwner = \OC\Files\Filesystem::getOwner($path);
        $currentUser = \OC::$server->getUserSession()
            ->getUser()
            ->getUID();
        if ($uidOwner != $currentUser) {
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
}
