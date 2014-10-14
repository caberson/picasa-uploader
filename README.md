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