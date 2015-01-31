<?php
/*=============================================================================
#  Author:			Caber Chu - clearcubic.com
#  FileName:		ccPicasaUploader.php
#  Description:		Upload photos from your computer to your Picasa account
#  Version:         0.1
=============================================================================*/

define('CC_UPLOADER_CONFIG_FILE', 'ccPicasaUploaderConfig.json');
define('CC_DEFAULT_USER_TIMEZONE', 'America/New_York');
define('CC_DIR_LIB', 'lib');
define('CC_ZEND_GDATA_DIR', CC_DIR_LIB . DIRECTORY_SEPARATOR . 'ZendGdata' .
	DIRECTORY_SEPARATOR . 'library');
define('CC_PHP_JPEG_METADATA_TOOLKIT_DIR', CC_DIR_LIB . DIRECTORY_SEPARATOR .
	'PHP_JPEG_Metadata_Toolkit');

set_include_path(
	get_include_path() . PATH_SEPARATOR .
	CC_ZEND_GDATA_DIR . PATH_SEPARATOR .
	CC_PHP_JPEG_METADATA_TOOLKIT_DIR
);

require_once 'Zend/Loader.php';
require_once 'Zend/Gdata.php';

// Loading other required Zend classes.
$requiredClasses = array(
	'Zend_Gdata',
	'Zend_Gdata_AuthSub',
	'Zend_Gdata_ClientLogin',
	'Zend_Gdata_Photos',
	'Zend_Gdata_Photos_UserQuery',
	'Zend_Gdata_Photos_AlbumQuery',
	'Zend_Gdata_Photos_PhotoQuery',
	'Zend_Gdata_App_Extension_Category',
);
foreach ($requiredClasses as $className) {
	Zend_Loader::loadClass($className);
}

// Incude JPEG metadata toolkit.
require_once 'Toolkit_Version.php';
require_once 'JPEG.php';
require_once 'XMP.php';
require_once 'Photoshop_IRB.php';
require_once 'EXIF.php';
require_once 'JFIF.php';
require_once 'PictureInfo.php';

/**
 * Class to upload local photos to Google Picasa.
 */
class PicasaUploader
{
	// Google picasa service.
	protected $gp;

	protected $user;
	protected $password;

	// User provided album id/name.
	protected $albumName;
	protected $uploadFolder;

	// Google album id.
	protected $aid;

	protected $moveToFolderWhenDone = '';

	public function __construct()
	{
	}

	/**
	 * Log in to google service using provided user name and password.
	 *
	 * @param string $user	user id
	 * @param string $password	password
	 */
	public function login($user, $password)
	{
		$this->setUser($user);
		$this->setPassword($password);

		// Create an authenticated HTTP client
		$client = Zend_Gdata_ClientLogin::getHttpClient(
			$user, $password, Zend_Gdata_Photos::AUTH_SERVICE_NAME
		);

		$this->gp = new Zend_Gdata_Photos($client, 'Google-Dev-1.1');
	}

	/**
	 * Upload a folder to picasa.
	 *
	 * @param string $albumName		album name
	 * @param string $uploadFolder	upload folder
	 *
	 * @return boolean
	 */
	public function uploadTo($albumName, $uploadFolder)
	{
		$this->setAlbumName($albumName);
		$this->setUploadFolder($uploadFolder);

		$gp = $this->gp;

		// See if album exists
		$albumId = static::getAlbumId($gp, $albumName);
		if (!$albumId) {
			static::listAllAlbums($gp);
			$albumId = $this->confirmWithUserOnCreatingAlbum();
			if (!$albumId) {
				echo "Album not created.  Exit!\n";
				return false;
			}
		}
		$this->aid = $albumId;

		$baseFolderName = basename($uploadFolder);
		echo "Album: " . $albumName . "\n";
		echo "Album ID: " . $albumId . "\n";
		echo "Dir: " . $baseFolderName . "\n";
		echo str_repeat("=", 50) . "\n";

		// Move the file after it's copied if the _done folder is present
		if (is_dir($uploadFolder . '_done')) {
			$this->moveToFolderWhenDone = $folder . '_done';
		} else {
			$this->moveToFolderWhenDone = '';
		}

		// Start uploading photos
		static::uploadPhotoFromFolder(
			$gp, $albumId, $uploadFolder, $this->moveToFolderWhenDone
		);

		return true;
	}

	/**
	 * Confirm with client user on album creation.
	 *
	 * @return integer|boolean
	 */
	public function confirmWithUserOnCreatingAlbum()
	{
		$albumName = $this->getAlbumName();
		echo 'Album ID ' . $albumName . ' was not found'."\n";
		echo "Do you want to create an album named '{$albumName}' (Y/N)? ";
		$input = fgets(STDIN);

		if (strtolower(trim($input)) != 'y') {
			return false;
		}

		$baseFolderName = basename($this->getUploadFolder());

		$albumTitle = $albumName;
		$albumTime = static::getAlbumTSFromDirNa($baseFolderName);
		$albumSummary = '';

		// Add album.
		$aid = static::addAlbum($this->gp, $albumTitle, $albumTime, $albumSummary);
		if ($aid) {
			return $aid;
		} else {
			echo "Error creating an album\n";
			return false;
		}
	}

	/**
	 * Uploads images from a folder.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 * @param string $albumId	Album id string.
	 * @param string $uploadFolder	Source upload folder path string.
	 * @param string $moveToFolderWhenDone	(Optional) Move uploaded photo to
	 * 										this directory if specified.
	 */
	public static function uploadPhotoFromFolder(
		$gp, $albumId, $uploadFolder, $moveToFolderWhenDone = ''
	) {
		$baseFolderName = basename($uploadFolder);
		$albumTSFromDir = static::getAlbumTSFromDirNa($baseFolderName);

		$currentDate = new DateTime();

		$dirit = new DirectoryIterator($uploadFolder);
		$i = 0;
		foreach ($dirit as $fileInfo) {
			// Ignore dot files, directories and hidden files.
			if (
				$fileInfo->isDot() ||
				$fileInfo->isDir() ||
				substr($fileInfo->getFilename(), 0, 1) == '.'
			) {
				continue;
			}

			$fileNa = $fileInfo->getFilename();
			$filePath = $fileInfo->getPathname();
			// Validate file is image.
			if (!exif_imagetype($filePath)) {
				echo "Non image file skipped: $fileNa\n";
				continue;
			}

			// Get photo timestamp from EXIF.  If not present, get timestamp
			// from file name.  If not present, use folder timestamp
			$fileTS = static::getPhotoDateFromFile($filePath) ?
				static::getPhotoDateFromFile($filePath) : $albumTSFromDir;

			if (!$fileTS) {
				// Get TS from file name.
				$fileTS = static::getAlbumTSFromFileNa($fileNa);

				// If filename doesn't have date.
				if (!$fileTS) {
					// Use folder date or current date.
					$fileTS = $albumTSFromDir ?
						$albumTSFromDir : $currentDate->getTimestamp();
				}

				// The + $i orders the photos.
				$fileTS += $i;
			}


			echo "File: $fileNa ($fileTS " . date('Ymd', $fileTS) . ")\n";
			// return;

			// Upload photo
			static::addPhoto($gp, $albumId, $filePath, $fileTS);

			// Move file if applicable when done
			if ($moveToFolderWhenDone) {
				$moveTo = $moveToFolderWhenDone . DIRECTORY_SEPARATOR . $fileNa;
				rename($filePath, $moveTo);
			}

			++$i;
		} // end for
	}

	/**
	 * Get album id by album name.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 * @param string $album		Album id or name.  If $album is all digits,
	 * 							verify that the album id is valid.  Otherwise,
	 * 							search for an album with the given album name.
	 *
	 * @return integer|boolean
	 */
	public static function getAlbumId($gp, $album)
	{
		// If $album is all digits, check if it's a valid one.
		if (ctype_digit("$album") && static::gAlbumExists($gp, $album)) {
			return $album;
		}

		// Attempt to search album with the given album name.
		$aid = static::getGAlbumByName($gp, $album);
		if ($aid) {
			return $aid;
		}

		return false;
	}

	/**
	 * Check album id exists.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 * @param string $albumId	Album id.
	 *
	 * @return stdObj|boolean
	 */
	public static function gAlbumExists($gp, $albumId)
	{
		$gAlbumQuery = new Zend_Gdata_Photos_AlbumQuery();
		$gAlbumQuery->setUser('default');
		$gAlbumQuery->setAlbumId($albumId);
		$gAlbumQuery->setType('entry');

		try {
			$gAlbumEntry = $gp->getAlbumEntry($gAlbumQuery);

			return $gAlbumEntry;
		} catch (Zend_Gdata_App_Exception $e) {
			echo $e->getMessage();
			return false;
		}
	}

	/**
	 * Get album id by name.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 * @param string $search	Album name to search for.
	 *
	 * @return integer|boolean
	 */
	public static function getGAlbumByName($gp, $search)
	{
		$userFeed = static::getGAlbums($gp);

		$output = array();
		foreach ($userFeed as $i => $userEntry) {
			$gAlbumName = $userEntry->title->text;
			$gAlbumId = $userEntry->gPhotoId->text;

			if (stristr($gAlbumName, $search)) {
				return $gAlbumId;
			}
		}

		return false;
	}

	/**
	 * List all albums for a Google Picasa account.
	 *
	 * @param stdObj $gp	Google Picasa service account object.
	 */
	public static function listAllAlbums($gp)
	{
		$userFeed = static::getGAlbums($gp);

		$output = [];
		$timestamps = [];
		foreach ($userFeed as $i => $userEntry) {
			$timestamp = $userEntry->getGphotoTimestamp()->getText() / 1000;
			$timestamps[] = $timestamp;
			$output[] = date('Ymd', $timestamp) . ' ' .
				$userEntry->getTitle()->getText() .
				' (' . $userEntry->getGphotoId()->getText() . ')';
		}

		// Sort by album date
		array_multisort(
			$timestamps, SORT_ASC,
			$output
		);

		// List albums if any.
		if (!count($output)) {
			return;
		}
		
		echo str_repeat('=', 50)."\n";
		echo "Existing Albums\n";
		echo implode("\n", $output);
		echo "\n";
	}

	public static function viewAlbum($gp, $album)
	{
		$userFeed = static::getGAlbums($gp);

		foreach ($userFeed as $i => $userEntry) {
			// Match album title or id.
			if (
				// $userEntry->gPhotoId->text != $album && 
				// $userEntry->title->text != $album
				$userEntry->getGphotoId() != $album &&
				$userEntry->getTitle() != $album
				// $userEntry->getGphotoName() != $album
			) {
				continue;
			}

			// echo $userEntry->title->text;
			echo "ID: " . $userEntry->getGphotoTimestamp();
		}

		echo "\n";
	}

	/**
	 * Get list of albums.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 *
	 * @return stdObj
	 */
	public static function getGAlbums($gp)
	{
		try {
			$userFeed = $gp->getUserFeed('default');
			return $userFeed;
		} catch (Zend_Gdata_App_HttpException $e) {
			echo $e->getMessage() . "\n";
			if ($e->getResponse() != null) {
				echo "Body:\n" . $e->getResponse()->getBody() . "\n";
			}
			// In new versions of Zend Framework, you also have the option
			// to print out the request that was made.  As the request
			// includes Auth credentials, it's not advised to print out
			// this data unless doing debugging
			// echo "Request: <br />\n" . $e->getRequest() . "<br />\n";
		} catch (Zend_Gdata_App_Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
		}

		return array();
	}

	/**
	 * Create picasa album
	 *
	 * @param stdObj $gp	Google picasa service.
	 * @param string $albumTitle	Album title.
	 * @param integer $albumTime	Album timestamp.
	 * @param string $albumSummary	Album summary.
	 * @param boolean $public	Public album or not.
	 *
	 * @return integer
	 */
	public static function addAlbum(
		$gp, $albumTitle, $albumTime = 0, $albumSummary = '', $public = false
	) {
		$entry = new Zend_Gdata_Photos_AlbumEntry();
		if ($public) {
			$entry->setGphotoAccess($gp->newAccess('public'));
		}
		$entry->setTitle($gp->newTitle($albumTitle));
		$entry->setSummary($gp->newSummary($albumSummary));
		$entry->setGphotoTimestamp($gp->newTimestamp($albumTime));

		$createdEntry = $gp->insertAlbumEntry($entry);

		$gPhotoId = $createdEntry->getGphotoId();

		return $gPhotoId;
	}

	/**
 	 * Add photo to album.
	 *
	 * @param stdObj $gp	Google Picasa service object.
	 * @param string $albumId	Album id.
	 * @param string $fileNa	Image file path to add to the album file.
	 * @param string $fileTimestamp		Timestamp to use for the file.
	 *
	 * @return stdObj
	 */
	public static function addPhoto(
		$gp, $albumId = 'default', $fileNa = '', $fileTimestamp = ''
	) {
		$username = 'default';

		$photoName = basename($fileNa);
		$photoTimestamp = $fileTimestamp;
		$photoCaption = '';

		// Keywords.
		$photoTags = implode(',', static::getKeywordsFromFile($fileNa));

		// Set image mime type.
		$fd = $gp->newMediaFileSource($fileNa);
		$imgMimeType = image_type_to_mime_type(exif_imagetype($fileNa));
		$fd->setContentType($imgMimeType);

		// Create a PhotoEntry.
		$photoEntry = $gp->newPhotoEntry();
		$photoEntry->setMediaSource($fd);
		$photoEntry->setTitle($gp->newTitle($photoName));
		$photoEntry->setSummary($gp->newSummary($photoCaption));
		$photoEntry->setGphotoTimestamp($gp->newTimestamp($photoTimestamp));

		// Add some tags.
		$keywords = new Zend_Gdata_Media_Extension_MediaKeywords();
		$keywords->setText($photoTags);
		$photoEntry->mediaGroup = new Zend_Gdata_Media_Extension_MediaGroup();
		$photoEntry->mediaGroup->keywords = $keywords;

		// We use the AlbumQuery class to generate the URL for the album.
		$albumQuery = $gp->newAlbumQuery();

		$albumQuery->setUser($username);
		$albumQuery->setAlbumId($albumId);

		// We insert the photo, and the server returns the entry representing
		// that photo after it is uploaded.
		$insertedEntry = $gp->insertPhotoEntry(
			$photoEntry, $albumQuery->getQueryUrl()
		);

		return $insertedEntry;
	}

	/**
	 * Utility methods
	 */

	/**
	 * Get default albums timestamp from directory name.
	 *
	 * @param string $dirNa		Directory name.
	 *
	 * @return integer
	 */
	public static function getAlbumTSFromDirNa($dirNa)
	{
		$dtFormatStr = '%Y%m%d';
		$dtFormat = 'Ymd';
		$dtz = new DateTimeZone(date_default_timezone_get());

		$dirInfo = explode('_', $dirNa);

		$dt = DateTime::createFromFormat($dtFormat, $dirInfo[0], $dtz);
		if (!$dt) {
			return false;
		}

		$dt->setTime(0, 0, 0);
		//$dt->add(new DateInterval('PT3H'));
		$aTime = $dt->getTimestamp() * 1000;

		return $aTime;
	}

	/**
	 * Get timestamp from file's name.
	 * 
	 * @parm string $fileNa		File name.
	 *
	 * @return integer
	 */
	public static function getAlbumTSFromFileNa($fileNa)
	{
		$dtFormatStr = "%Y%m%d";
		$dtFormat = 'Ymd';
		$dtz = new DateTimeZone(date_default_timezone_get());

		$fileInfo = explode('_', $fileNa);

		// check there's a date to parse in file name
		$fileDate = $fileInfo[0];
		if (!is_numeric($fileDate) || strlen($fileDate) != 8) {
			return false;
		}

		$dt = DateTime::createFromFormat($dtFormat, $fileDate, $dtz);
		$dt->setTime(0, 0, 0);
		// $dt->add(new DateInterval('PT0H'));	//P1D PT4H
		$aTime = $dt->getTimestamp() * 1000;

		return $aTime;
	}

	/**
	 * Get photo date from file's image path.
	 *
	 * @param string @imgPath	Image path.
	 *
	 * @return integer
	 */
	public static function getPhotoDateFromFile($imgPath)
	{
		$exif = exif_read_data($imgPath, 0, true);
		if (!$exif) {
			// throw new Exception("Error reading EXIF from $imgPath");
			echo "Error reading EXIF from $imgPath\n";
		}

		if (!array_key_exists('EXIF', $exif)) {
			return false;
		}
		$exifDateTime = $exif['EXIF']['DateTimeOriginal'];

		$dtFormat = 'Y:m:d H:i:s';
		$dtz = new DateTimeZone(date_default_timezone_get());

		$dt = DateTime::createFromFormat($dtFormat, $exifDateTime, $dtz);
		if (!$dt) {
			return false;
		}
		$aTime = $dt->getTimestamp() * 1000;

		return $aTime;
	}

	/**
	 * Get keywords from a file's metadata.
	 *
	 * @param string @imgPath	Image path.
	 *
	 * @return Array
	 */
	public static function getKeywordsFromFile($imgPath)
	{
		$aryOutput = Array();
		$jpegHeaderData = get_jpeg_header_data($imgPath);
		$strXMP = get_XMP_text($jpegHeaderData);

		if (!$strXMP) {
			return $aryOutput;
		}

		// Init xml obj.
		$xmlDoc = new DOMDocument();
		$xmlDoc->preserveWhiteSpace = true;
		$xmlDoc->loadXML($strXMP);

		// Init xPath.
		$objXPath = new DOMXPath($xmlDoc);
		$objXPath->registerNamespace(
			'dc', 'http://purl.org/dc/elements/1.1/'
		);
		$objXPath->registerNamespace(
			'rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#'
		);

		$strXPathQuery = '//dc:subject//rdf:li';
		$objEntries = $objXPath->query($strXPathQuery);
		foreach ($objEntries as $entry) {
			$aryOutput[] = $entry->nodeValue;
		}

		return $aryOutput;
	}

	/**
	 * Getters/setters
	 */

	public function getGP()
	{
		return $this->gp;
	}

	public function setUser($user)
	{
		$this->user = $user;
	}

	public function setPassword($password)
	{
		$this->password = $password;
	}

	public function setAlbumName($albumName)
	{
		$this->albumName = $albumName;
	}

	public function getAlbumName()
	{
		return $this->albumName;
	}

	public function setUploadFolder($uploadFolder)
	{
		$this->uploadFolder = $uploadFolder;
	}

	public function getUploadFolder()
	{
		return $this->uploadFolder;
	}
} // End class.


/**
 * Read script config file if present.
 *
 * @return Array
 */
function read_config_file()
{
	// See if we can find the config file.  If file exists, extract info.
	$configFile = dirname(__FILE__) . DIRECTORY_SEPARATOR .
		CC_UPLOADER_CONFIG_FILE;
	if (!file_exists($configFile)) {
		return array();
	}

	$config = file_get_contents($configFile);
	return json_decode($config, true);
}

/**
 * Get cmd line script usage.
 *
 * @return string
 */
function get_usage()
{
	$file = __FILE__;
	$usage = <<<TEXT
Usage: $file
	--album=album-name
	--folder=photo-folder
	--list
	--view
	--user=Google-Picasa-account-email (can be specified in config.)
	--password=Google-Picasa-account-password (can be specified in config.)

TEXT;
	return $usage;

}

////////////////////////////////////////////////////////////////
// Main code if run from command line
////////////////////////////////////////////////////////////////
if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
	$longOpts = array(
		'user::',
		'password::',
		'album::',
		'folder::',
		'list::',
		'view::',
	);
	$args = getopt(false, $longOpts);
	extract($args);

	$config = read_config_file();
	if ((!isset($user) || !isset($password)) && !empty($config)) {
		$user = isset($config['user']) ? $config['user'] : null;
		$password = isset($config['password']) ? $config['password'] : null;
	}

	if (!isset($user) || !isset($password)) {
		print get_usage();
		exit(128);
	}

	date_default_timezone_set(
		isset($config['timezone']) ?
			$config['timezone'] : CC_DEFAULT_USER_TIMEZONE
	);

	$uploader = new PicasaUploader();
	$uploader->login($user, $password);

	if (isset($list)) {
		PicasaUploader::listAllAlbums($uploader->getGP());
		return;
	}

	if (isset($view)) {
		echo "ViewAlbumId: $view\n";
		PicasaUploader::viewAlbum($uploader->getGP(), $view);
		return;
	}

	if (!isset($album) || !isset($folder)) {
		print get_usage();
		exit(128);
	}
	$uploader->uploadTo($album, $folder);
}

?>
