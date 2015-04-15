echo Press enter to remove all IPC message queues owned by www-data.
read
ipcrm msg `ipcs -q | grep www-data | awk '{print $2}'`
