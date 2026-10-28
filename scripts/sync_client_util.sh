
# simple script to repeatedly call the sync script
# a cron dev thing - don't rely on this
while sleep 2; do cd ../www && php sync_client.php; done