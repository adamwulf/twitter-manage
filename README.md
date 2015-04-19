# twitter-manage

A simple twitter app that can help automatically manage followers


1. Clone this app to your server
2. setup the config.php to use a MySQL database, Twitter app, etc
3. add twitter users who's followers you'd like to autofollow
4. setup cron

Each time the cron runs, it will follow X number of followers from that account. It will also keep the list up to date so you can see who follows back, and out of those who follow back, who has also unfollowed.