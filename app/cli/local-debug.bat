"{app_php_path}\php.exe" -c "{app_absolute_path}\srv\app.local-cli\php.ini" -f "{app_absolute_path}\app\cli\index.php" -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9003 -dxdebug.start_with_request=yes %1