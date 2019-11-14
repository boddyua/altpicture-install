
## Ubuntu: steps used to prepare server
1. Install and base setup for internet access
2. Run ```sudo ./install-altpicture.sh```
3. That's all. Open ```http://your_server_address``` from another Chrome-client and setup AltPicture. You can use kiosk mode with parameters, e.g. ```chrome http://192.168.10.242 --kiosk --disable-extensions --disable-notifications --disable-sync --disable-translate=1``` And dont forget to use network shares: ```smb://your_server_address/spOrders``` for access to orders (RO) and ```smb://your_server_address/spNetSource``` for uploads 

## Windows: oh boy
1. install python & PILLOW;
2. install web-server+php, recommended nginx+php-fpm;
3. make sure the webserver not allow access for .json files - for security reason; 
4. set webserver for upload e.g. (nginx ```client_max_body_size 1024m;``` php ```upload_max_filesize=1024M``` and ```post_max_size=1024M```);
5. setup network shares, ```server\orders``` as read-only access, and ```server\src-net``` as read-write access;
6. you should start time from time ```your-site/server/index.php checkFailedJobs```, e.g. by Php in the task scheduler
7. That's all. Open http://your_server_address from another Chrome-client and setup AltPicture. You can use kiosk mode with parameters, e.g. ```chrome.exe http://192.168.10.242 --kiosk --disable-extensions --disable-notifications --disable-sync --disable-translate=1``` And you should use network shares, created on step 5
