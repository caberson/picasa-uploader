picasa-uploader
===============
Upload photos from your computer to your Picasa account


Requirements
===============
* PHP5.
* Composer.
* Zend v1 library (tested with version 1.12).
  Source: http://framework.zend.com/downloads/latest
* PHP JPEG metadata toolkit (tested with version 1.12).
  Source: http://www.ozhiker.com/electronics/pjmt/
* And a picasa account obviously.


Before Use
===============
* Have composer installed.
* Make sure the ZEND_GDATA_DIR and PHP_JPEG_METADATA_TOOLKIT_DIR defines
  in the script are updated based on your system setup.
* (As of 10/13/2014) The PHP JPEG metadata toolkit is a fairly old library.
  I had to make a change to the following two files in the library:
  - Photoshop_IRB.php
  - EXIF.php

  The thing I did was to delete the opening PHP script opening tag '<?PHP',
  retype it in and save the file.


Usage
===============
php ccPicasaUploader.php --list

php ccPicasaUploader.php
	--album=albumName
	--folder=uploadFolder

References
===============
* http://code.google.com/apis/picasaweb/docs/1.0/developers_guide_php.html