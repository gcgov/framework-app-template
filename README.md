# Framework App Template

App template repository to scaffold a new [gcgov/framework](https://github.com/gcgov/framework)

## Instructions

1. [Use this template](https://github.com/gcgov/framework-app-template/generate) to generate a new repository for your
   app
1. Replace app variables across configuration files:
    - Variables to replace:
        - `{app_guid}` -> unique guid (generate from https://www.guidgenerator.com/)
        - `{app_title}` -> human-readable title of app
        - `{app_url}` -> full url to root of app
        - `{app_absolute_path}` -> absolute path to app root directory
        - `{app_php_path}` -> absolute path to the PHP executable root directory
        - `{app_smtp_server}` -> smtp server address
        - `{app_smtp_sendmail_from_address}` -> default email address to send emails from
        - `{app_smtp_sendmail_from_name}` -> default human-readable name that will appear as the sender of emails
        - `{app_ssl_path}` -> absolute path to a current cacert.pem file for CURL and OpenSSL extensions
            - Global cacert.pem is available from Mozilla at https://curl.se/docs/caextract.html
            - Use behind a firewall with SSL decryption will require appending private
        - **Production Variables**:
            - `{prod_app_url}`
            - `{prod_app_absolute_path}`
            - `{prod_app_php_path}`
            - `{prod_app_ssl_path}`
    - Files to replace variables in:
        - `/srv/app.local/php.ini`
        - `/srv/app.local-cli/php.ini`
        - `/srv/app.prod/php.ini`
        - `/srv/app.prod-cli/php.ini`
        - `/app/config/app.json`
        - `/app/config/environment.json`
        - `/app/cli/local.bat`
        - `/app/cli/local-debug.bat`
        - `/app/cli/prod.bat`
1. Move `/composer-local.json` to `/composer.json`
1. Move `/app/config/environment-local.json` to `/app/config/environment.json`
1. Move `/www/web-local.config` to `/www/web.config`
1. Configure web server
   - Root directory must map to `/www/`
   - PHP instance must use `/srv/app.local/php.ini` for configuration
1. Test widget module.
1. Create your models, controllers, and services!