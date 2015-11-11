picasa-uploader
===============
Upload photos from your computer to your Picasa account


Requirements
===============
* PHP5.
* ZendGdata library (tested with version 1.10).
  Source: http://framework.zend.com/downloads/latest
* PHP JPEG metadata toolkit (tested with version 1.12).
  Source: http://www.ozhiker.com/electronics/pjmt/
* And a picasa account obviously.


Before Use
===============
* Make sure the ZEND_GDATA_DIR and PHP_JPEG_METADATA_TOOLKIT_DIR defines
  in the script are updated based on your system setup.
* You may want to create the ccPicasaUploaderConfig.json file and fill it with
  your information.  I place my user email and password in it. Please see the
  template file, ccPicasaUploaderConfig.json.tmpl.
* (As of 10/13/2014) The ZendGData download was missing the 'library/Zend/Xml'
  folder.  One can get this missing folder from the Zend Framework 1.12.9
  Minimal install.
  [5/29/2015] The ZendGData download was missing the 'library/Zend/Http' folder.
  Got it from ZendFramework 1.12.13 minimal install.
* (As of 10/13/2014) The PHP JPEG metadata toolkit is a fairly old library.
  I had to make a change to the following two files in the library:
  - Photoshop_IRB.php
  - EXIF.php

  The thing I did was to delete the opening PHP script opening tag '<?PHP',
  retype it in and save the file.


Usage
===============
php ccPicasaUploader.php
	--user=picasaEmail
	--password=picasaPassword
	--album=albumName
	--folder=uploadFolder

* If ccPicasaUploaderConfig.json is found in the same directory as the script,
  it will be read and its information extracted.


References
===============
* http://code.google.com/apis/picasaweb/docs/1.0/developers_guide_php.html