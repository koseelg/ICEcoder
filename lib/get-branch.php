<?php
if (!isset($ICEcoder['root'])) {
	include("headers.php");
	include("settings.php");
}

if (!$_SESSION['loggedIn']) {
	header("Location: ../");
}

$text = $_SESSION['text'];
$t = $text['get-branch'];
?>
<!DOCTYPE html>
<html>
<head>
<title>ICEcoder v <?php echo $ICEcoder["versionNo"];?> get branch</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex, nofollow">
<script src="base64.js"></script>
<script src="github.js"></script>
</head>

<body>
<?php
// Function to sort given values alphabetically
function alphasort($a, $b) {
	return strcmp($a->getPathname(), $b->getPathname());
}

// Class to put forward the values for sorting
class SortingIterator implements IteratorAggregate {
	private $iterator = null;
	public function __construct(Traversable $iterator, $callback) {
		$array = iterator_to_array($iterator);
		usort($array, $callback);
		$this->iterator = new ArrayIterator($array);
	}
	public function getIterator() {
		return $this->iterator;
	}
}

// Get a full list of dirs & files and begin sorting using above class & function
$path = $docRoot.$iceRoot;
$objectList = new SortingIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST), 'alphasort');

// Iterator to get files
$iter = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
	RecursiveIteratorIterator::SELF_FIRST,
	RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
);

// Check if dir has .gitignore file
function hasGitignore($dir) {
	return is_file("$dir/.gitignore");
}

// Get a list of .gitignore files into $gi array
$gi = array();
if(hasGitignore($path)) {
	$gi[] = "$path/.gitignore";
}
foreach ($iter as $scanpath) {
    if (is_dir($scanpath) && strpos($scanpath,".git") == false) {
	$thisDir = str_replace("\\","/",$scanpath);
        if(hasGitignore($thisDir)) {
		$gi[] = $thisDir."/.gitignore";
	}
    }
}

// Get $matches array containing existing files listed in .gitignore
function parseGitignore($file) { # $file = '/absolute/path/to/.gitignore'
  $dir = dirname($file);
  $matches = array();
  $lines = file($file);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;                 # empty line
    if (substr($line, 0, 1) == '#') continue;   # a comment
    if (substr($line, 0, 1) == '!') {           # negated glob
      $line = substr($line, 1);
      $files = array_diff(glob("$dir/*"), glob("$dir/$line"));
    } else {                                    # normal glob
      $files = glob("$dir/$line");
    }
    $matches = array_merge($matches, $files);
  }
  return $matches;
}

// Cycle through all .gitignore files running above function to get a list of $excluded files
$excluded = array();
foreach ($gi as $scanpath) {
    $excludedTest = (parseGitignore($scanpath));
	if (count($excludedTest) > 0) {
		$excluded = array_merge($excluded, $excludedTest);
	}
}

$objectListArray = array();
foreach ($objectList as $objectRef) {
	$fileFolderName = @ltrim(substr(str_replace("\\","/",$objectRef->getPathname()), strlen($path)),"/");
	array_push($objectListArray,$fileFolderName);
}

// If we're just getting a branch, get that and set as the finalArray
$scanDir = $docRoot.$iceRoot;
$location = "";
echo '<div id="branch" style="display: none">';
$location = str_replace("|","/",$_GET['location']);
if ($location=="/") {$location = "";};

$dirArray = $filesArray = $finalArray = array();
$finalArray = scanDir($scanDir.$location);
foreach($finalArray as $entry) {
	$canAdd = true;
	for ($i=0;$i<count($_SESSION['bannedFiles']);$i++) {
		if($_SESSION['bannedFiles'][$i] != "" && strpos($entry,$_SESSION['bannedFiles'][$i])!==false) {$canAdd = false;}
	}
	if ("/".$entry == $ICEcoderDir) {
		$canAdd = false;
	}
	if ($entry != "." && $entry != ".." && $canAdd) {
		is_dir($docRoot.$iceRoot.$location."/".$entry)
		? array_push($dirArray,$location."/".$entry)
		: array_push($filesArray,$location."/".$entry);
	}
}
natcasesort($dirArray);
natcasesort($filesArray);

$finalArray = array_merge($dirArray,$filesArray);
for ($i=0;$i<count($finalArray);$i++) {
	$fileFolderName = str_replace("\\","/",$finalArray[$i]);
	$type = is_dir($docRoot.$iceRoot.$fileFolderName) ? "folder" : "file";
	if ($type=="file") {
		// Get extension (prefix 'ext-' to prevent invalid classes from extensions that begin with numbers)
		$ext = "ext-".pathinfo($docRoot.$iceRoot.$fileFolderName, PATHINFO_EXTENSION);
	}
	if ($i==0) {echo "<ul style=\"display: block\">\n";}
	if ($i==count($finalArray)-1 && isset($_GET['location'])) {
		echo "</ul>\n";
	}
	$type == "folder" ? $class = 'pft-directory' : $class = 'pft-file '.strtolower($ext);
	$loadParam = $type == "folder" ? "true" : "false";
	echo "<li class=\"".$class."\" draggable=\"true\" ondrag=\"top.ICEcoder.draggingWithKeyTest(event);if(top.ICEcoder.getcMInstance()){top.ICEcoder.getcMInstance().focus()}\" ondragend=\"top.ICEcoder.dropFile(this)\"><a nohref title=\"$fileFolderName\" onMouseOver=\"top.ICEcoder.overFileFolder('$type',this.childNodes[1].id)\" onMouseOut=\"top.ICEcoder.overFileFolder('$type','')\" onClick=\"if(!event.ctrlKey && !top.ICEcoder.cmdKey) {top.ICEcoder.openCloseDir(this,$loadParam); if (/Android|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent)) {top.ICEcoder.openFile()}}\" style=\"position: relative; left:-22px\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span id=\"".str_replace($docRoot,"",str_replace("/","|",$fileFolderName))."\">".xssClean(basename($fileFolderName),"html")."</span> ";
	echo '<span style="color: #888; font-size: 8px" id="'.str_replace($docRoot,"",str_replace("/","|",$fileFolderName)).'_perms">';
	echo $serverType=="Linux" ? substr(sprintf('%o', fileperms($docRoot.$iceRoot.$fileFolderName)), -3) : '';
	echo "</span></a></li>\n";
}

echo '	</div>';

if ($_SESSION['githubDiff']) {
	// Show the loading screen until we're done comparing files with GitHub
	echo "<script>setTimeout(function(){top.ICEcoder.showHide('show',top.get('loadingMask'));},4)</script>";
	$i=0;
	$dirListArray = $dirSHAArray = $dirTypeArray = array();
	// For each of the files in our local path...
	for ($i=0; $i<count($objectListArray); $i++) {
		$fileFolderName = "/".$objectListArray[$i];

		// If we're not looking at a .git dir, it's not a .gitignore excluded path and not a dir
		if (strpos($fileFolderName,".git/") == false && !in_array($docRoot.$iceRoot.$fileFolderName, $excluded) && !is_dir($docRoot.$iceRoot.$fileFolderName)) {
			// Get contents of file
			$contents = file_get_contents($docRoot.$iceRoot.$fileFolderName);

			$finfo = "text";
			// Determine if we should remove \r line endings based on mime type (text files yes, others no)
			if (function_exists('finfo_open')) {
				$finfoMIME = finfo_open(FILEINFO_MIME);
				$finfo = finfo_file($finfoMIME, $docRoot.$iceRoot.$fileFolderName);
				finfo_close($finfoMIME);
			} else {
				$fileExt = explode(" ",pathinfo($docRoot.$iceRoot.$fileFolderName, PATHINFO_EXTENSION));
				$fileExt = $fileExt[0];
				if (array_search($fileExt,array("gif","jpg","jpeg","png"))!==false) {$finfo = "image";};
				if (array_search($fileExt,array("doc","docx","ppt","rtf","pdf","zip","tar","gz","swf","asx","asf","midi","mp3","wav","aiff","mov","qt","wmv","mp4","odt","odg","odp"))!==false) {$finfo = "other";};
			}
			if (strpos($finfo,"text")===0 || strpos($finfo,"empty")!==false) {
				$contents = str_replace("\r","",$contents);
			};
			// Establish the blob SHA contents and push name, SHA and type into 3 arrays
			$store = "blob ".strlen($contents)."\000".$contents;
			array_push($dirListArray,ltrim($fileFolderName,"/"));
			array_push($dirSHAArray,sha1($store));
			array_push($dirTypeArray,"file");
		}
	}

	// Get our GitHub relative site path
	$ghRemoteURLPos = array_search($ICEcoder["root"],$ICEcoder['githubLocalPaths']);
	$ghRemoteURLPaths = $ICEcoder['githubRemotePaths'];
	$ghRemoteURL = $ghRemoteURLPaths[$ghRemoteURLPos];
	$ghRemoteURL = str_replace("https://github.com/","",$ghRemoteURL);

	// Reduce absolute excluded paths to relative
	for ($i=0; $i<count($excluded); $i++) {
		$excluded[$i] = str_replace($docRoot.$iceRoot,"",$excluded[$i]);
	}
	?>
	<script>
	top.repo = '<?php echo $ghRemoteURL;?>';
	top.path = '<?php echo $path;?>';
	dirListArray =  [<?php echo "'".implode("','", $dirListArray)."'";?>];
	dirSHAArray  =  [<?php echo "'".implode("','", $dirSHAArray)."'";?>];
	dirTypeArray =  [<?php echo "'".implode("','", $dirTypeArray)."'";?>];
	excludedArray = [<?php echo "'".implode("','", $excluded)."'";?>];
	// Start our github object
	var github = new Github({token: "<?php echo $_SESSION['githubAuthToken'];?>", auth: "oauth"});
	repoListArray = [];
	repoSHAArray = [];

	// Set our repo and get the tree recursively
	var repo = github.getRepo(top.repo.split("/")[0], top.repo.split("/")[1]);
	repo.getTree('master?recursive=true', function(err, tree) {
		if(!err) {
			treePaths = [];
			diffPaths = [];
			deletedPaths = [];
			// ==========================================================
			// NEW FILES are not compared for diffs in this loop, so kept
			// ==========================================================
			for (var i=0; i<tree.length; i++) {
				// compare files (when tree types are blobs)
				if (tree[i].type == "blob") {
					// ===========================
					// UNCHANGED FILES are removed
					// ===========================
					if (tree[i].sha == dirSHAArray[dirListArray.indexOf(tree[i].path)]) {
						thatNode = document.getElementById("|"+tree[i].path.replace("/","|"));
						if (document.getElementById("|"+tree[i].path.replace("/","|")+"_perms")) {
							thatNode = document.getElementById("|"+tree[i].path.replace("/","|")+"_perms").parentNode.parentNode;
							thatNode.parentNode.removeChild(thatNode);
						}
					} else {
						// ======================
						// CHANGED FILES are kept
						// ======================
						if ("undefined" != typeof dirSHAArray[dirListArray.indexOf(tree[i].path)]) {
							diffPaths.push(tree[i].path);
						// ======================
						// DELETED FILES are kept
						// ======================
						} else {
							diffPaths.push(tree[i].path);
							deletedPaths.push(tree[i].path);
						}
					}
				} else {
					treePaths.push(tree[i].path);
				}
			}
			// Now we are only showing new, changed and deleted files from our GitHub tree list
			// in short, we have removed unchanged files from what would be visible

			// However, we should now consider dirs that the user hasn't opened yet as we can
			// maybe remove closed dirs that contain no changes
			for (var i=0; i<treePaths.length; i++) {
				canShowDir = false;
				for (j=0; j<diffPaths.length; j++) {
					if (diffPaths[j].indexOf(treePaths[i]+"/") === 0) {
						canShowDir = true;
					}
				}
				// Remove dirs that contain no changes in them
				if (!canShowDir) {
					if (document.getElementById("|"+treePaths[i].replace("/","|")+"_perms")) {
						thatNode = document.getElementById("|"+treePaths[i].replace("/","|")+"_perms").parentNode.parentNode;
						thatNode.parentNode.removeChild(thatNode);
					}
				}
			}

			// Finally, remove any excluded files as specified in the .gitignore file
			for (var i=0; i<excludedArray.length; i++) {
				if (document.getElementById(excludedArray[i].replace(/\//g,"|")+"_perms")) {
					thatNode = document.getElementById(excludedArray[i].replace(/\//g,"|")+"_perms").parentNode.parentNode;
					thatNode.parentNode.removeChild(thatNode);
				}
			}

			// With everything done, we can now set folderContent, animate those into view and when done, hide the loading screen
			setTimeout(function(){
				folderContent = document.getElementById('branch').innerHTML;
				showFiles();
				// If there are no diffs, ask user if they want to switch back to regular mode
				setTimeout(function(){
					if (parent.document.getElementById('|').parentNode.parentNode.parentNode.childNodes[2].childNodes.length==1) {
						if(top.ICEcoder.ask('<?php echo $t['There are no...'];?>')) {
							top.ICEcoder.githubDiffToggle();
						} else {
							top.ICEcoder.showHide('hide',top.get('loadingMask'));
						}
					} else {
						top.ICEcoder.showHide('hide',top.get('loadingMask'));
					}
				},40);
			},4);
		} else {
			// There was an error, display HTTP error code and response message
			top.ICEcoder.message('<?php echo $t['Sorry, there was...'];?> '+err.error+'\n\n'+err.request.response);
			top.ICEcoder.showHide('hide',top.get('loadingMask'));
		}
	});
	</script>
	<?php
}
?>
	<script>
	targetElem = top.ICEcoder.filesFrame.contentWindow.document.getElementById('<?php echo $_GET['location'];?>');
	newUL = document.createElement("ul");
	newUL.style = "display: block";
	locNest = targetElem.parentNode.parentNode;
	if(locNest.nextSibling && locNest.nextSibling.tagName=="UL") {
		x = locNest.nextSibling;
		x.parentNode.removeChild(x);
	}
	folderContent = document.getElementById('branch').innerHTML;

	showFiles = function () {
		// Now animate folders & files into view
		i=0;
		animFolders = setInterval(function() {
			i++;
			showContent = "";
			folderItems = folderContent.split("\n");
			for (j=0; j<=i; j++) {
				showContent += folderItems[j];
				if (j<i) {showContent += "\n";};
			}
			showContent = showContent.slice(28);
			if (j==folderItems.length) {
				clearInterval(animFolders);
				showContent = showContent.slice(0,-2);
				// If we've got some deleted files (as we're in GitHub diff mode), add those into the file manager
				if ("undefined" != typeof deletedPaths && deletedPaths.length > 0) {
					for (var j=0; j<deletedPaths.length; j++) {
						fSplit = deletedPaths[j].lastIndexOf("/");
						thePath = deletedPaths[j].substr(0,fSplit);
						theFile = deletedPaths[j].substr(fSplit+1);
						// If we're opening a dir that contains a deleted dir and it's not excluded, inject into the file manager
						if ("<?php echo $location;?>" == "/"+thePath && excludedArray.indexOf("/"+thePath+"/"+theFile) == -1) {
							setTimeout(function(){top.ICEcoder.updateFileManagerList('add','/'+thePath,theFile,false,false,false,'file');},4);
						}
					}
				}
				setTimeout(function(){top.ICEcoder.redoTabHighlight(top.ICEcoder.selectedTab);},4);
				if (!top.ICEcoder.fmReady) {top.ICEcoder.fmReady=true;};
			}
			newUL.innerHTML = showContent;
			locNest.parentNode.insertBefore(newUL,locNest.nextSibling);
		},4);
	}

	// If we're not in githubDiff mode, show files here
	if (folderContent.indexOf('<ul')>-1 || folderContent.indexOf('<li')>-1) {
		<?php if (!$_SESSION['githubDiff']) {echo 'showFiles();';};?>
	} else {
		<?php
		$iceGithubLocalPaths = $ICEcoder["githubLocalPaths"];
		$iceGithubRemotePaths = $ICEcoder["githubRemotePaths"];
		$pathPos = array_search($iceRoot,$iceGithubLocalPaths);
		if ($pathPos !== false) {
		?>
			if (top.ICEcoder.ask("<?php echo $t['Your local folder...'];?> <?php echo $iceGithubRemotePaths[$pathPos];?>?")) {
				setTimeout(function() {
					top.ICEcoder.showHide('show',top.get('loadingMask'));
					top.ICEcoder.filesFrame.contentWindow.frames['fileControl'].location.href = "github.php?action=clone&csrf="+top.ICEcoder.csrf;
				},4);
			}
		<?php ;}; ?>
	}
	</script>
</body>
</html>