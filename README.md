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

Notes
-----

- Linux only, as it requires System V IPC, refer to http://www.tldp.org/LDP/lpg/node21.html and http://php.net/manual/en/sem.installation.php

- This app is not used by any ownCloud client at this point (June 2015). There is a request for instantaneous server-to-client notifications (-> https://github.com/owncloud/client/issues/1075) but it obviously has no priority.

- As long-polling requires the server to keep open TPC connections and with this app for each connection even a PHP interpreter instance, it does not scale. That is at least what the theory says. Practically it has never been tested what number of users can be supported with this approach by an ownCloud server.
