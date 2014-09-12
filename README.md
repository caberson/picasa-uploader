picasa-uploader
===============
Upload photos from your computer to your Picasa account

Requirements
===============
* PHP5
* ZendGdata library (I am using version 1.10)
* PHP JPEG metadata toolkit (I am using version 1.12)
* And a picasa account obviously


Usage
===============
php ccPicasaUploader.php
	--user=picasaEmail
	--password=picasaPassword
	--album=albumName
	--folder=uploadFolder

* If ccPicasaUploaderConfig.json is found in the same directory as the script,
  it will be read and its information extracted.  I place my user email and
  password in it.


References
===============
* http://code.google.com/apis/picasaweb/docs/1.0/developers_guide_php.html
* http://ozhiker.com/electronics/pjmt/