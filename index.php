<?php
/**
 * Output a sequence diagram as a png
 * Uses https://github.com/schrepfler/sequence-diagram-generator
 * Acutally, this would probably be even easier in nodejs using the HTTP package,
 * since it acts as it's own http server, we wouldn't even need the rewrite rule...oh well, will do later
 *
 * IMPORTANT: Remember to make the file sequence-diagram-generator/.tmp.html writable by webserver. 
 * This should be changed sometime...but atm, just 777 it and bedone

 * Here's another UML generator... https://github.com/tmtk75/jumly 
*/

//Output & cache directory
define("DATA_PATH", "/var/www/uml/data");

//Full path to generate sequence diagram directory. It must be run within it's own folder to load npm modules
define("UML_DIR", "/var/www/uml/sequence-diagram-generator");

//Executable name, with leading ./, relative to above UML_DIR
define("UML_CMD", "./generate-sequence-diagram.js");

//All reqeuests not matching a file/dir are redirected here via .htaccess with QSA. 
$data = $_SERVER['REQUEST_URI'];

//Valid graph types...this switches the engine or just options. 
//This is new, default is to use 'simple' from the JS sequence diagram package
$valid_types = array('simple', 'hand');

if (strlen($data) > 1) {
	//Trim off leading slash
	$umldata = substr($data, 1);

	$umldata = urldecode($umldata);

	$hash = md5($umldata);

	list($type, $new_umldata) = explode('/', $umldata);

	if (is_null($new_umldata) || !in_array(strtolower($type), $valid_types)) { 
		$type = 'simple';
	} else {
		$umldata = $new_umldata;
		$type = strtolower($type);
	}

	$imagefile = DATA_PATH . "/{$hash}.png";

	if (file_exists($imagefile)) {
		outputImage($imagefile);
	} else {
		//Save the request to a txt file, then run it through sequence diagram generator
		$datafile = DATA_PATH . "/{$hash}.uml";
		if (file_exists($datafile)) {
			throw new Exception("Data file already exists, but image is missing.", 500);
		} else {
			if (!file_put_contents($datafile, $umldata)) {
				throw new exception("Failed to write UML data", 500);
			} else {
				//This method never returns
				generateJsSequenceDiagram($datafile, $imagefile, $type);
			}
		}
	}
}


/**
 * Generate the imagefile using JS sequence diagram.
 * This is the original & default method.  This handles types simple, and hand. 
 * At this point, all validation should be completed.  We will be calling UML_CMD and
 * writing to $imagefile
 *
 * @param $datafile string Path to UML data file
 * @param $imagefile string Path to output image file
 * @param $requested_type string Requested graph type. One of: simple, or hand. Default: simple
 * @return void This calls outputImage, which sends image to browser & exits. This method never returns.
*/
function generateJsSequenceDiagram($datafile, $imagefile, $requested_type='simple') { 
	$valid_types = ['simple', 'hand'];

	if (!in_array($requested_type, $valid_types)) {
		throw new exception("Invalid type requested. Valid types are: simple, or hand", 400);
	}

	$error_log = DATA_PATH . "/errors.log";
	$cmd = UML_CMD . " -f {$datafile} -o {$imagefile} -t '{$requested_type}' 2>> {$error_log} >> /dev/null";
	$pwd = getcwd();
	chdir(UML_DIR);
	$output = `$cmd`;
	chdir($pwd);
	if (!file_exists($imagefile) || !filesize($imagefile) > 0) {
		throw new exception("Could not generate UML image. Command: {$cmd} Errors logged to {$error_log}", 500);
	} else {
		outputImage($imagefile);
	}
}


/**
 * Send the requested image via passthru() and set appropriate headers
 * This function exit()'s and never returns
 * @param $imagefile string Path to image png
 * @return void never returns
*/
function outputImage($imagefile) {
	header("Content-type: image/png");
	passthru("cat {$imagefile}");
	exit;
}
