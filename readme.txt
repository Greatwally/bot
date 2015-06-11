1. Download this PHP build for Windows - http://windows.php.net/downloads/releases/php-5.6.9-Win32-VC11-x86.zip
2. Extract archive into c:\php
3. Download this library http://windows.php.net/downloads/pecl/releases/pthreads/2.0.10/php_pthreads-2.0.10-5.6-ts-vc11-x86.zip
4. Add pthreadVC2.dll (from php_pthreads-2.0.10-5.6-ts-vc11-x86.zip) to the same directory as php.exe
5. Add pthreadVC2.dll to c:\windows\system32 directory
6. Add php_pthreads.dll (from php_pthreads-2.0.10-5.6-ts-vc11-x86.zip) to PHP extension folder eg. c:\php\ext
7. Find in c:\php file "php.ini-development", and rename it to php.ini
8. At the end of php.ini, add following line:
     extension_dir = "ext"
     extension=php_pthreads.dll
     extension=php_curl.dll
9. Copy all bot files into c:\php
10. Open command line in windows and perform commands:
    cd c:\php
    php bot.php
