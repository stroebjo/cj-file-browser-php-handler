<?php 

/* **

Copyright (c) 2011, Jonathan Stroebele
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
	  
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 

** */

$h = new handler();

$h->returnFormat = (isset($_GET['returnFormat'])) ? $_GET['returnFormat'] : 'JSON';
$h->version = (isset($_GET['version'])) ? $_GET['version'] : 0;

if(isset($_GET['method'])){
	switch($_GET['method']){

		case 'isHandlerReady':
			$h->isHandlerReady();
		break;
		
		case 'getDirectoryList':
			$h->getDirectoryList($_GET['baseUrl'], $_GET['fileExts']);
		break;
		
		case 'doDeleteFile':
			$h->doDeleteFile($_GET['baseUrl'], $_GET['fileName']);
		break;
		
		case 'doDeleteDirectory':
			$h->doDeleteDirectory($_GET['baseUrl'], $_GET['dirName']);
		break;
		
		case 'doFileUpload':
			$h->doFileUpload();
		break;
	
		case 'doCreateNewDirectory':
			$h->doCreateNewDirectory($_GET['baseUrl'], $_GET['dirName']);
		break;
		
		case 'getImageThumb':
			$h->getImageThumb($_GET['baseUrl'], $_GET['elemID'], $_GET['fileName']);
		break;
	}
	$h->printResponse();
}


	/**
	 *	handler
	 *	
	 *	Differs from the spec (http://www.cjboco.com/blog.cfm/post/guide-to-creating-your-own-cj-file-browser-handler-engine-plug-in)
	 *	in the following ways:
	 *		- is*Valid functions have no second argument. The caching is done
	 *		  by getSecuritySettings()
	 *
	 *		- no timeOut implementation
	 *
	 *		- Security.xml format for the extension is atomic:
	 *			<fileExt>gif</fileExt>
	 *			<fileExt>png</fileExt>
	 *			...
	 */
class handler {

	public $returnFormat = 'JSON';
	
	public $version;
	
	private $settings = Array();
	
	private $response = Array();
	
	private $imageExtensions = Array('gif','jpg','jpeg','png');
	
	private $thumb_width = 120;
	
	private $thumb_height = 120;
			
		/**
		 *	Informs CJ File Browser that the handler exists
		 *	and also check to make sure the security.xml 
		 *	file is present and appears to be valid.
		 */
	public function isHandlerReady() {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
		
		/* Check securiy.xml version */
		$version = $this->getSecuritySettings('version');
		if ($version['ERROR'] === false) {
		
			if ($version['VERSION'] != $this->version) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Security.xml version does not match the CJ File Browser version.');
			}
		
		} else {
			$this->setResponse('ERROR', true);
			
			if (!empty($version['ERROR_MSG'])) {
				$this->addResponseMessage($version['ERROR_MSG']);
			} else {
				$this->addResponseMessage('Problems checking security.xml version.');
			}
		}
		
		/* Check securiy.xml directories */
		$directories = $this->getSecuritySettings('directories');
		if ($directories['ERROR'] === true) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage($directories['ERROR_MSG'][0]);
		}
		
		/* Check securiy.xml actions */
		$actions = $this->getSecuritySettings('actions');
		if ($actions['ERROR'] === true) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage($actions['ERROR_MSG'][0]);
		}
		
		/* Check securiy.xml extension */
		$extensions = $this->getSecuritySettings('fileExts');
		if ($extensions['ERROR'] === true) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage($extensions['ERROR_MSG'][0]);
		}	
	}
	
		/**
		 *	Reads and returns the contents of a given directory.
		 *
		 *	@param string baseUrl directory to enumerate
		 *	@param string fileExts wich file extensions
		 */	
	public function getDirectoryList($baseUrl, $fileExts = '') {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
	
		$directories = $this->getSecuritySettings('directories');
		$actions = $this->getSecuritySettings('actions');
		$extensions = $this->getSecuritySettings('fileExts');
	
		/* validate "navigateDirectory" action and path */
		if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory access denied.');
			return;
		}
	
		/* validate that the "baseUrl" exists */
		if (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory does not exist.');
		} else {
		
			$directory = Array();
			
			$fileExts = explode(',', $fileExts);
			array_walk($fileExts, 'trim');
		
			if ($d = opendir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
				while (false !== ($f = readdir($d))) {
				
					if ($f != "." && $f != "..") {
						
						$path_parts = pathinfo($_SERVER['DOCUMENT_ROOT'].$baseUrl.$f);
						
						// all extensions are allowed OR
						// is a file and t has the allowed extension OR
						// is a directory						
						if ($fileExts[0] == '*' || (isset($path_parts['extension']) && in_array($path_parts['extension'], $fileExts)) || !isset($path_parts['extension'])) {
							$file = Array();
							
							if (isset($path_parts['extension'])) {
								$file['EXTENSION'] = strtoupper($path_parts['extension']);
								$file['TYPE'] = 'FILE';
							} else {
								$file['EXTENSION'] = null;
								$file['TYPE'] = 'DIR';
							}
							
							$file['NAME'] = $f;
							$file['SIZE'] = filesize($_SERVER['DOCUMENT_ROOT'].$baseUrl.$f);
							$file['ATTRIBUTES'] = null;
							$file['DATELASTMODIFIED'] = date('F, j, Y H:i:s', filemtime($_SERVER['DOCUMENT_ROOT'].$baseUrl.$f));
							$file['DIRECTORY'] = $baseUrl;
							
							$file['FULLPATH'] = $_SERVER['DOCUMENT_ROOT'].$baseUrl.$f;
							
							if (in_array($file['EXTENSION'], $this->imageExtensions) && $i = getimagesize($_SERVER['DOCUMENT_ROOT'].$baseUrl.$f)) {
								$file['WIDTH'] = $i[0];
								$file['HEIGHT'] = $i[1];
							} else {
								$file['WIDTH'] = null;
								$file['HEIGHT'] = null;
							}

							$directory[] = $file;						
						}
					}
				}
				closedir($d);
			}
			$this->response['DIRLISTING'] = $directory;
		}
	}
	
		/**
		 *	Returns a STRUCT of image data which can be used
		 *	to display an images scaled preview (or thumb).
		 *
		 *	@param string baseUrl directory
		 *	@param string elemID 
		 *	@param string fileName image name
		 */	
	public function getImageThumb($baseUrl, $elemID, $fileName) {
		$directories = $this->getSecuritySettings('directories');
		$actions = $this->getSecuritySettings('actions');
		$fileName = basename($fileName);
	
		if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory access denied.');
		} elseif (!$this->isActionValid('filePreviews')) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Image previews are not allowed.');
		} elseif (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory does not exist.');
		} else {
		
			if (is_file($_SERVER['DOCUMENT_ROOT'].$baseUrl.$fileName)) {
				
				$path_parts = pathinfo($_SERVER['DOCUMENT_ROOT'].$baseUrl.$fileName);
				
				if (isset($path_parts['extension']) && in_array($path_parts['extension'], $this->imageExtensions) && $i = getimagesize($_SERVER['DOCUMENT_ROOT'].$baseUrl.$fileName)) {
				
					$this->setResponse('ERROR', false);
					$this->setResponse('ERROR_MSG', Array());
					$this->setResponse('ELEMID', htmlspecialchars($elemID));
				
					if ($i[0] > $this->thumb_width || $i[1] > $this->thumb_height) {
						$t = $this->calcScaleInfo($i[0], $i[1], $this->thumb_width, $this->thumb_height);
						$this->setResponse('IMGSTR', '<img style="margin-top:'.$t['offset_y'].'px;margin-left:'.$t['offset_x'].'px" height="'.$t['height'].'" width="'.$t['width'].'" src="'.htmlspecialchars($baseUrl.$fileName).'" />');
					} else {
						$t = Array(
							'offset_x' => (int) ($this->thumb_width / 2) - ($i[0] / 2),
							'offset_x' => (int) ($this->thumb_height / 2) - ($i[1] / 2),
						);
						$this->setResponse('IMGSTR', '<img style="margin-top:'.$t['offset_y'].'px;margin-left:'.$t['offset_x'].'px" height="'.$i[1].'" width="'.$i[0].'" src="'.htmlspecialchars($baseUrl.$fileName).'" />');
					}
				
				} else {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Problems reading image thumbnail. Invalid path. ('.htmlspecialchars($baseUrl.$fileName).')');
				}

			} else {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Problems reading image thumbnail. Invalid path. ('.htmlspecialchars($baseUrl.$fileName).')');
			}
		}
	}
	
		/**
		 *	Calculates the new image dimensions.
		 *
		 *	@param int srcWidth
		 *	@param int srcHeight
		 *	@param int destWidth
		 *	@param int destHeight
		 *	@param string method
		 *	@return array
		 */	
	private function calcScaleInfo($srcWidth, $srcHeight, $destWidth, $destHeight, $method = 'fit') {
		$fits = false;
		$xscale = $destWidth / $srcWidth;
		$yscale = $destHeight / $srcHeight;
	
		if ($method == 'fit') {
			$scale = min($xscale, $yscale);
		} elseif( $method == 'fill') {
			$scale = max($xscale, $yscale);
		}
		
		if ($srcWidth >= $destWidth) {
			$fits = true;
		}
		
		return Array(
			'width' => round($srcWidth * $scale),
			'height' => round($srcHeight * $scale),
			'offset_x' => round(($destWidth - ($srcWidth * $scale)) / 2),
			'offset_y' => round(($destHeight - ($srcHeight * $scale)) / 2),
		);		
	}
	
		/**
		 *	Uploads a file to the server (Handles a form POST operation)
		 *
		 */	
	public function doFileUpload() {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
		
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('HTTP request method not allowed.');
		} elseif(
			!isset($_POST['baseUrl']) ||
			!isset($_POST['maxWidth']) ||
			!isset($_POST['maxHeight']) ||
			!isset($_POST['maxSize']) ||
			!isset($_POST['fileExts']) ||
			!isset($_FILES['fileUploadField'])
		){
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Could not complete upload. Required form variables missing.');
		} else {
			
			$directories = $this->getSecuritySettings('directories');
			$actions = $this->getSecuritySettings('actions');
			$extensions = $this->getSecuritySettings('fileExts');
		
			$baseUrl = $_POST['baseUrl'];
		
			if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Directory access denied.');
			} elseif (!$this->isActionValid('fileUpload')) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Not authorized to upload files.');
			} elseif (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Directory does not exist.');
			} elseif($_FILES['fileUploadField']['error'] != 0) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Problems encountered uploading the file.');			
			} elseif($_FILES['fileUploadField']['size'] > ($_POST['maxSize'] * 1024)) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('The file size of your upload excedes the allowable limit. Please upload a file smaller than '.(int) $_POST['maxSize'].'KB.');
			} else {
				/*if (preg_match('//', $_POST['baseUrl']) > 0) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Variable BASEURL not defined or invalid data.');
				} else*/
				if (!is_dir($_SERVER['DOCUMENT_ROOT'].$_POST['baseUrl'])) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('You must provide a valid UPLOAD DIRECTORY.<br /><small>(Could not find directory)</small>');
				}
			
				$fileExts = explode(',', $_POST['fileExts']);
				array_walk($fileExts, 'trim');
				if (count($fileExts) == 0) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Variable FILEEXTS not defined or invalid data.');
				}
				
				$maxSize = (int) $_POST['maxSize'];
				if ($maxSize < 1 || $maxSize > 9999999) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Variable MAXSIZE not defined or invalid data.');
				}
				
				$maxWidth = (int) $_POST['maxWidth'];
				if ($maxWidth < 1 || $maxWidth > 9999999) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Variable MAXWIDTH not defined or invalid data.');
				}
				
				$maxHeight = (int) $_POST['maxHeight'];
				if ($maxHeight < 1 || $maxHeight > 9999999) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Variable MAXHEIGHT not defined or invalid data.');
				}
				
				if (strlen($_FILES['fileUploadField']['name']) == 0) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('FILE INPUT FILED not defined or invalid data.');				
				}
				
				$filename = basename($_FILES['fileUploadField']['name']);
				$ext = substr($filename, strrpos($filename, '.') + 1);
				
				if ($fileExts[0] != '*' && strlen($ext) > 0 && !in_array($ext, $fileExts)) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Fileextension is not allowed. (client)');	
				}
				
				$i = preg_match('/^[\.a-zA-Z0-9_-]+$/', $filename);
			
				if ($i != 1) {
					$this->setResponse('ERROR', true);
					$this->addResponseMessage('Invalid characters detected in file name. (Valid [a-zA-Z0-9_-.])');
				}

				if (!$this->response['ERROR']) {
					if ($this->isFileExtValid($ext) || ($extensions['EXTLIST'][0] == '*')) {
						
						$newname = $_SERVER['DOCUMENT_ROOT'].$_POST['baseUrl'].$filename;
					
						if (!file_exists($newname)) {
							if ((move_uploaded_file($_FILES['fileUploadField']['tmp_name'], $newname))) {
								// all is okey
							} else {
								$this->setResponse('ERROR', true);
								$this->addResponseMessage('Problems encountered uploading the file.');	
							}

						} else {
							$this->setResponse('ERROR', true);
							$this->addResponseMessage('File allready exists.');	
						}
					} else {
						$this->setResponse('ERROR', true);
						$this->addResponseMessage('Fileextension is not allowed. (server)');	
					}
				}
			}
		}
	}
	
		/**
		 *	Creates a new directory on the server.
		 *
		 *	@param string baseUrl parent directory
		 *	@param string dirName name of the new directory
		 */	
	public function doCreateNewDirectory($baseUrl, $dirName) {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
		
		$dirName = basename($dirName);
		
		$directories = $this->getSecuritySettings('directories');
		$actions = $this->getSecuritySettings('actions');
	
		if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory access denied.');
		} elseif (!$this->isActionValid('createDirectory')) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Creating directories is not allowed.');
		} elseif (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory does not exist.');
		} else {
		
			$i = preg_match('/^[a-zA-Z0-9_-]+$/', $dirName);
			
			if ($i != 1) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Invalid characters detected in directory name. (Valid [a-zA-Z0-9_-])');
			} elseif (strlen($dirName) > 64) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Invalid directory name length. (Valid 1-64 characters)');
			} elseif(is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl.$dirName)) {
				$this->setResponse('ERROR', true);
				$this->addResponseMessage('Directory already exists.');
			} else {
				mkdir($_SERVER['DOCUMENT_ROOT'].$baseUrl.$dirName);
			}
		}
	}
	
		/**
		 *	Deletes a given file from the server.
		 *
		 *	@param string baseUrl parent directory
		 *	@param string fileName name of the file
		 */	
	public function doDeleteFile($baseUrl, $fileName) {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
		
		$fileName = basename($fileName);
		
		$directories = $this->getSecuritySettings('directories');
		$actions = $this->getSecuritySettings('actions');
		
		/* validate "navigateDirectory" action and path */
		if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory access denied.');
		} elseif (!$this->isActionValid('fileDelete')) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Not authorized to delete files.');
		} elseif (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory does not exist.');
		} elseif (!is_file($_SERVER['DOCUMENT_ROOT'].$baseUrl.$fileName)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('File does not exist.');
		} else {
			unlink($_SERVER['DOCUMENT_ROOT'].$baseUrl.$fileName);
		}
	}
	
		/**
		 *	Deletes a directory on the server.
		 *
		 *	@param string baseUrl parent directory
		 *	@param string dirName name of the directory
		 */	
	public function doDeleteDirectory($baseUrl, $dirName) {
		$this->setResponse('ERROR', false);
		$this->setResponse('ERROR_MSG', Array());
		
		$dirName = basename($dirName);
		
		$directories = $this->getSecuritySettings('directories');
		$actions = $this->getSecuritySettings('actions');
		
		/* validate "navigateDirectory" action and path */
		if (!$this->isActionValid('navigateDirectory') && !$this->isPathValid($baseUrl, true) || !$this->isPathValid($baseUrl, false)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory access denied.');
		} elseif (!$this->isActionValid('deleteDirectory')) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Deleting directories is not allowed.');
		} elseif (!is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl) || !is_dir($_SERVER['DOCUMENT_ROOT'].$baseUrl.$dirName)) {
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('Directory does not exist.');
		} elseif (strlen($dirName) == 0 || $dirName == '/' || $dirName == '\\' ) {
			/* make sure it's not the root directory!! */
			$this->setResponse('ERROR', true);
			$this->addResponseMessage('You cannot delete the root directory.');
		} else {
			$this->delete_directory($_SERVER['DOCUMENT_ROOT'].$baseUrl.$dirName);
		}
	}
	
		/**
		 *	Deletes a direcotry with all subdirectories.
		 *
		 *	@param string dirName directory to delete
		 */	
	private function delete_directory($dirName) {
		if (is_dir($dirName) && $dir_handle = opendir($dirName)) {
			while($file = readdir($dir_handle)) {
				if ($file != '.' && $file != '..') {
					if (!is_dir($dirName.'/'.$file)) {
						unlink($dirName.'/'.$file);
					} else {
						$this->delete_directory($dirName.'/'.$file);    
					}
				}
			}
			closedir($dir_handle);
			rmdir($dirName);
		}
	}
	
		/**
		 *	Validates that the given relative path is allowed
		 *	within the security settings.
		 *
		 *	@param string baseUrl directory to validate
		 *	@param bool exact if false: check also if dir is a subdir
		 *	@return bool
		 */	
	private function isPathValid($baseUrl, $exact = true) {
		$authDirs = $this->getSecuritySettings('directories');
		if ($authDirs['ERROR'] === true) {
			return false;
		}
		
		if ($exact) {
			return in_array($baseUrl, $authDirs['DIRLIST']);
		} else {	
			chdir($_SERVER['DOCUMENT_ROOT']);
			$requestPath = realpath($_SERVER['DOCUMENT_ROOT'].$baseUrl);
		
			foreach ($authDirs['DIRLIST'] as $dir) {
				$authPath =  realpath($_SERVER['DOCUMENT_ROOT'].$dir);
				if (substr($requestPath, 0, strlen($authPath)) == $authPath) {
					return true;
				}			
			}		
		}
	}
	
		/**
		 *	Validates that the given action is allowed
		 *	ithin the security settings.
		 *
		 *	@param string action action to validate
		 *	@return bool
		 */	
	private function isActionValid($action) {
		$authDirs = $this->getSecuritySettings('actions');
		if ($authDirs['ERROR'] === true) {
			return false;
		}
		return in_array($action, $authDirs['ACTIONLIST']);	
	}
	
		/**
		 *	Validates that the given file extension is allowed
		 *	ithin the security settings.
		 *
		 *	@param string ext extension to validate
		 *	@return bool
		 */	
	private function isFileExtValid($ext) {
		$authDirs = $this->getSecuritySettings('fileExts');
		if ($authDirs['ERROR'] === true) {
			return false;
		}
		return in_array($ext, $authDirs['EXTLIST']);	
	}
	
		/**
		 *	Reads the cjFileBrowser security.xml and caches all
		 *	settings.
		 */	
	private function getSecuritySettings($settingType) {
	
		if (isset($this->settings[$settingType])) {
			return $this->settings[$settingType];
		} elseif (file_exists('../../../security.xml')) {
			$xml = simplexml_load_file('../../../security.xml');
			
			 $this->settings['directories'] = $this->getSecuritySettings_directories($xml);
			 $this->settings['actions'] = $this->getSecuritySettings_actions($xml);
			 $this->settings['fileExts'] = $this->getSecuritySettings_fileExts($xml);
			 $this->settings['version'] = $this->getSecuritySettings_version($xml);

			if (isset($this->settings[$settingType])) {
				return $this->settings[$settingType];
			} else {
				$result['ERROR'] = true;
				$result['ERROR_MSG'][] = 'Unknown security parameter check.';
				return $result;
			}
			 
		} else {
			$result['ERROR'] = true;
			$result['ERROR_MSG'][] = 'Security.xml file could not be found.';
			return $result;
		}
	}

	
	private function getSecuritySettings_directories($xml) {
		$directories = Array();
		$result = Array(
			'ERROR' => false,
			'ERROR_MSG' => Array(),
		);
	
		foreach ($xml->directoriesAllowed->directory as $dir) {
			if (strlen(trim($dir)) > 0) {
				$directories[] = trim($dir);
			}
		}
		
		if (count($directories) > 0) {
			$result['DIRLIST'] = $directories;
		} else {
			$result['ERROR'] = true;
			$result['ERROR_MSG'][] = 'There are no authorized directories set in the security.xml file. (Cannot be blank)';
		}
		return $result;
	}
	
	private function getSecuritySettings_actions($xml) {
		$actions = Array();
		$result = Array(
			'ERROR' => false,
			'ERROR_MSG' => Array(),
		);
		
		foreach ($xml->actionsAllowed->action as $act) {
			if (strlen(trim($act)) > 0) {
				$actions[] = trim($act);
			}
		}
		
		if (count($actions) > 0) {
			$result['ACTIONLIST'] = $actions;
		} else {
			$result['ERROR'] = true;
			$result['ERROR_MSG'][] = 'There are no authorized actions set in the security.xml file. (No settings will not allow any action)';
		}
		return $result;
	}
	
	private function getSecuritySettings_fileExts($xml) {
		$extensions = Array();
		$result = Array(
			'ERROR' => false,
			'ERROR_MSG' => Array(),
		);
		
		foreach ($xml->fileExtsAllowed->fileExt as $ext) {
			if (strlen(trim($ext)) > 0) {
				$extensions[] = trim($ext);
			}
		}
		
		if (count($extensions) > 0) {
			$result['EXTLIST'] = $extensions;
		} else {
			$result['ERROR'] = true;
			$result['ERROR_MSG'][] = 'There are no authorized file extensions set in the security.xml file. (Cannot be blank)';
		}
		return $result;
	}
	
	private function getSecuritySettings_version($xml) {
		return Array(
			'ERROR' => false,
			'ERROR_MSG' => Array(),
			'VERSION' => (string) $xml->attributes()->version,
		);
	}
	
		/**
		 *	Sends the output to the client.
		 */	
	public function printResponse() {
		switch ($this->returnFormat) {
			case 'JSON':
			default:
				echo json_encode($this->response);
		}
	}
	
	private function addResponseMessage($message) {
		$this->response['ERROR_MSG'][] = $message;
	}
	
	private function setResponse($name, $data) {
		$this->response[$name] = $data;
	}
}
?>