# long-polling-for-owncloud
An app for ownCloud providing change notifications via Long-Polling

How to test long-polling
------------------------

1. Install and activate this app in your ownCloud

  - cd /var/www/owncloud/apps/
  - wget https://github.com/LukeOwncloud/long-polling-for-owncloud/archive/master.zip
  - unzip master.zip
  - mv long-polling-for-owncloud-master long_polling
  - Browse to https://[yourOwnCloud]/index.php/settings/apps and activate Long-Polling app
  
2. Browse to https://[yourOwnCloud]/index.php/apps/long_polling/poll

3. See the change information arrive

Linux only, as it requires System V IPC, refer to http://www.tldp.org/LDP/lpg/node21.html and http://php.net/manual/en/sem.installation.php
